#!/usr/bin/env python3
"""StockFlash Work/OpenCode relay. Python >=3.10, standard library only.

Transport: comments on a dedicated, unmerged GitHub control pull request.
No LLM calls, no secrets in git, and no code execution unless explicitly enabled.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

PROTOCOL = "SF-WORK-1"
REPORT_PROTOCOL = "SF-OC-REPORT-1"
REPO = "marioromero/StockFlash"
SHA_RE = re.compile(r"^[0-9a-f]{40}$")
FEATURE_RE = re.compile(r"^[A-Z][A-Z0-9]*-[0-9]{3,5}$")
ID_RE = re.compile(r"^[A-Za-z0-9_.:-]{4,120}$")
FENCE = re.compile(r"\x60\x60\x60sf-work-instruction-v1\s*\n(\{.*?\})\s*\n\x60\x60\x60", re.S)
REQUIRED_CHECKS = {"PHP 8.3 test", "Node 24 typecheck + build", "Smoke GET / es-CL", "DOC contracts and hashes"}
ROOT = Path(__file__).resolve().parent.parent
STATE = ROOT / "docs" / "work" / ".run" / "sf-work-bridge-state.json"


class BridgeError(Exception):
    pass


def validate_instruction(payload: dict) -> dict:
    if not isinstance(payload, dict) or payload.get("protocol") != PROTOCOL:
        raise BridgeError("Protocolo de instrucción inválido")
    if payload.get("action") not in {"STOP", "OWNER_REQUIRED", "RUN"}:
        raise BridgeError("Acción no permitida")
    for key in ("instruction_id", "report_id"):
        if not isinstance(payload.get(key), str) or not ID_RE.fullmatch(payload[key]):
            raise BridgeError(f"Identificador inválido: {key}")
    for key in ("source_feature", "feature"):
        if not isinstance(payload.get(key), str) or not FEATURE_RE.fullmatch(payload[key]):
            raise BridgeError(f"Feature inválida: {key}")
    if not isinstance(payload.get("base_sha"), str) or not SHA_RE.fullmatch(payload["base_sha"]):
        raise BridgeError("SHA inválido")
    notes = payload.get("notes", "")
    if not isinstance(notes, str) or len(notes) > 8000:
        raise BridgeError("Notas inválidas o excesivas")
    if set(payload) - {"protocol", "action", "instruction_id", "report_id", "source_feature", "feature", "base_sha", "notes"}:
        raise BridgeError("Campos adicionales no autorizados")
    return payload


def parse_instruction(body: str) -> dict | None:
    match = FENCE.search(body or "")
    if not match:
        return None
    try:
        return validate_instruction(json.loads(match.group(1)))
    except (ValueError, TypeError) as exc:
        raise BridgeError(f"JSON inválido: {exc}") from exc


def get_token() -> str:
    token = os.environ.get("GH_TOKEN") or os.environ.get("GITHUB_TOKEN")
    if not token:
        try:
            token = subprocess.run(["gh", "auth", "token"], capture_output=True, text=True, timeout=10, check=True).stdout.strip()
        except (OSError, subprocess.SubprocessError):
            pass
    if not token:
        raise BridgeError("Falta GH_TOKEN/GITHUB_TOKEN o autenticación de GitHub CLI (gh auth login)")
    return token.strip()


class GitHub:
    def __init__(self, token: str, repo: str = REPO):
        if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repo):
            raise BridgeError("Repositorio inválido")
        self.token, self.repo = token, repo

    def request(self, method: str, path: str, body: dict | None = None):
        url = f"https://api.github.com/repos/{self.repo}/{path.lstrip('/')}"
        data = json.dumps(body).encode() if body is not None else None
        req = urllib.request.Request(url, data=data, method=method,
            headers={"Accept": "application/vnd.github+json", "Authorization": f"Bearer {self.token}",
                     "X-GitHub-Api-Version": "2022-11-28", "User-Agent": "StockFlash-Work-Bridge/1",
                     "Content-Type": "application/json"})
        try:
            with urllib.request.urlopen(req, timeout=20) as resp:
                return json.load(resp)
        except urllib.error.HTTPError as exc:
            detail = exc.read(300).decode("utf-8", errors="replace")
            raise BridgeError(f"GitHub HTTP {exc.code}: {detail}") from exc
        except urllib.error.URLError as exc:
            raise BridgeError(f"GitHub no disponible: {exc.reason}") from exc

    def main_sha(self):
        sha = self.request("GET", "git/ref/heads/main")["object"]["sha"]
        if not SHA_RE.fullmatch(sha):
            raise BridgeError("SHA de main incorrecto")
        return sha

    def comments(self, pr: int):
        self.verify_control_pr(pr)
        comments = []
        for page in range(1, 21):
            batch = self.request("GET", f"issues/{pr}/comments?per_page=100&page={page}")
            comments.extend(batch)
            if len(batch) < 100:
                return comments
        raise BridgeError("Demasiados comentarios: requiere paginación ampliada")

    def verify_control_pr(self, pr: int):
        obj = self.request("GET", f"pulls/{pr}")
        if obj.get("state") != "open" or "[SF-WORK-CONTROL]" not in obj.get("title", ""):
            raise BridgeError("El PR debe estar abierto y marcado [SF-WORK-CONTROL]")
        if obj.get("base", {}).get("ref") != "main":
            raise BridgeError("El PR de control no apunta a main")

    def post_comment(self, pr: int, body: str):
        self.verify_control_pr(pr)
        return self.request("POST", f"issues/{pr}/comments", {"body": body})["html_url"]

    def ci_green(self, sha: str) -> bool:
        data = self.request("GET", f"commits/{sha}/check-runs?per_page=100")
        found = {}
        for c in data.get("check_runs", []):
            if c.get("name") in REQUIRED_CHECKS:
                prev = found.get(c["name"])
                if not prev or c.get("id", 0) > prev.get("id", 0):
                    found[c["name"]] = c
        return all(found.get(k, {}).get("status") == "completed" and
                   found[k].get("conclusion") == "success" for k in REQUIRED_CHECKS)


def read_state() -> dict:
    if STATE.exists():
        data = json.loads(STATE.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {}
    return {"processed": {}}


def save_state(state: dict):
    STATE.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", dir=STATE.parent, delete=False) as fh:
        json.dump(state, fh, ensure_ascii=False, indent=2, sort_keys=True)
        name = fh.name
    os.replace(name, STATE)


def git(*args: str):
    r = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True, timeout=30)
    if r.returncode:
        raise BridgeError(f"Git {args[0]} falló: {r.stderr[:250]}")
    return r.stdout.strip()


def approved_active(feature: str):
    packet = ROOT / "docs" / "work" / "features" / f"{feature}.md"
    current = ROOT / "docs" / "work" / "CURRENT.md"
    if not packet.is_file() or not current.is_file():
        raise BridgeError("No están disponibles packet y CURRENT")
    if not re.search(r"(?im)^status:\s*APPROVED\s*$", packet.read_text(encoding="utf-8")):
        raise BridgeError(f"{feature} no está APPROVED")
    lines = current.read_text(encoding="utf-8").splitlines()
    if not any(feature in line and re.search(r"(?i)\b(activa|activo|active)\b", line) for line in lines):
        raise BridgeError(f"{feature} no figura como activo en CURRENT")


def execute_instruction(data: dict, timeout: int):
    feature = data["feature"]
    approved_active(feature)
    if git("branch", "--show-current") != "main" or git("status", "--porcelain"):
        raise BridgeError("Worktree debe estar limpio y en main")
    local = git("rev-parse", "HEAD")
    remote = git("ls-remote", "origin", "refs/heads/main").split()[0]
    if local != remote or local != data["base_sha"]:
        raise BridgeError("SHA local/remoto/instrucción no coincide; sin ejecución")
    target = ROOT / "docs" / "work" / ".run" / f"work-instruction-{data['instruction_id']}.json"
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    cmd = ["opencode", "run", "--file", str(target), "--command", "sf-run", feature]
    print(f"Ejecutando /sf-run {feature}; instrucción local: {target}", flush=True)
    try:
        result = subprocess.run(cmd, cwd=ROOT, timeout=timeout, check=False)
    except FileNotFoundError as exc:
        raise BridgeError("OpenCode CLI no está en PATH") from exc
    except subprocess.TimeoutExpired as exc:
        raise BridgeError("OpenCode superó el timeout; verificar proceso antes de reintentar") from exc
    if result.returncode:
        raise BridgeError(f"OpenCode terminó con código {result.returncode}")
    print("OpenCode terminó; comprobar evidencia real. NO se declara VERIFIED ni se envía nuevo reporte automáticamente.")


def handle_comment(comment: dict, state: dict, gh: GitHub, args) -> bool:
    if comment.get("user", {}).get("login", "").lower() != args.trusted_login.lower():
        return False
    data = parse_instruction(comment.get("body", ""))
    if data is None or str(comment["id"]) in state.setdefault("processed", {}):
        return False
    if data["base_sha"] != gh.main_sha():
        raise BridgeError(f"Instrucción {data['instruction_id']} desactualizada: SHA distinto a main")
    matching_report = any(
        c.get("id", 0) < comment["id"] and
        c.get("body", "").find("\x60\x60\x60sf-oc-report-v1") >= 0 and
        f'"report_id": "{data["report_id"]}"' in c.get("body", "") and
        f'"sha": "{data["base_sha"]}"' in c.get("body", "") and
        f'"feature": "{data["source_feature"]}"' in c.get("body", "")
        for c in args.all_comments
    )
    if not matching_report:
        raise BridgeError("No hay reporte previo de OpenCode asociado a la instrucción")
    key = str(comment["id"])
    state["processed"][key] = {"instruction_id": data["instruction_id"], "status": "RECEIVED", "action": data["action"]}
    save_state(state)
    if data["action"] in {"STOP", "OWNER_REQUIRED"}:
        state["processed"][key]["status"] = data["action"]
        save_state(state)
        print(f"{data['action']} {data['feature']}: {data.get('notes', '')[:350]}")
        return True
    if not args.execute:
        state["processed"][key]["status"] = "DRY_RUN"
        save_state(state)
        print(f"DRY_RUN: instrucción RUN válida {data['instruction_id']}. No se invocó OpenCode.")
        return True
    if os.getenv("SF_WORK_ALLOW_EXECUTE") != "YES":
        raise BridgeError("Para ejecutar RUN se requiere SF_WORK_ALLOW_EXECUTE=YES")
    state["processed"][key]["status"] = "RUNNING"
    save_state(state)
    try:
        execute_instruction(data, args.timeout)
    except BridgeError:
        state["processed"][key]["status"] = "BLOCKED"
        save_state(state)
        raise
    state["processed"][key]["status"] = "FINISHED_UNVERIFIED"
    save_state(state)
    return True


def cmd_watch(args, gh: GitHub):
    while True:
        comments = gh.comments(args.pr)
        args.all_comments = comments
        state = read_state()
        handled = 0
        for c in comments:
            if handle_comment(c, state, gh, args):
                handled += 1
        print(f"Consulta completada: {len(comments)} comentarios, {handled} instrucción(es) nueva(s).", flush=True)
        if args.once:
            return
        time.sleep(args.interval)


def cmd_signal(args, gh: GitHub):
    if not FEATURE_RE.fullmatch(args.feature) or not ID_RE.fullmatch(args.report_id):
        raise BridgeError("Feature o report_id incorrecto")
    sha = gh.main_sha()
    if args.smoke:
        if args.report_path:
            raise BridgeError("SMOKE no puede tener report_path")
    else:
        if not args.report_path or not re.fullmatch(r"docs/work/[A-Za-z0-9_./-]+\.md", args.report_path) or ".." in args.report_path:
            raise BridgeError("Se requiere ruta versionada docs/work/...md")
        gh.request("GET", f"contents/{urllib.parse.quote(args.report_path, safe='/')}?ref={sha}")
        if not gh.ci_green(sha):
            raise BridgeError("No hay CI completa y verde para el HEAD actual; no enviar RUN")
    payload = {"protocol": REPORT_PROTOCOL, "report_id": args.report_id,
               "feature": args.feature, "sha": sha, "kind": "SMOKE" if args.smoke else "RUN",
               "report_path": args.report_path or ""}
    content = "\x60\x60\x60sf-oc-report-v1\n" + json.dumps(payload, indent=2, ensure_ascii=False) + "\n\x60\x60\x60"
    print("Reporte señalado:", gh.post_comment(args.pr, content))
    print("Ahora Work debe responder en el PR. Si no tiene permiso de escritura, el ciclo queda bloqueado.")


def main():
    p = argparse.ArgumentParser(description="Puente GitHub PR ↔ ChatGPT Work ↔ OpenCode; sin llamadas a API de IA")
    p.add_argument("--repo", default=REPO)
    sp = p.add_subparsers(dest="mode", required=True)
    w = sp.add_parser("watch", help="Escuchar instrucciones de Work en un PR de control")
    w.add_argument("--pr", type=int, required=True)
    w.add_argument("--trusted-login", required=True)
    w.add_argument("--interval", type=int, default=30)
    w.add_argument("--timeout", type=int, default=3600)
    w.add_argument("--once", action="store_true")
    w.add_argument("--execute", action="store_true", help="Además ejecutar RUN; requiere SF_WORK_ALLOW_EXECUTE=YES")
    s = sp.add_parser("signal", help="Publicar un aviso de OpenCode para activar Work")
    s.add_argument("--pr", type=int, required=True)
    s.add_argument("--feature", required=True)
    s.add_argument("--report-id", required=True)
    s.add_argument("--report-path")
    s.add_argument("--smoke", action="store_true", help="Prueba sin CI ni código, Work solo debe responder STOP")
    a = p.parse_args()
    if not 0 < a.pr < 1000000:
        p.error("PR inválido")
    if a.mode == "watch" and (a.interval < 15 or a.timeout < 60):
        p.error("Intervalo mínimo 15s y timeout mínimo 60s")
    try:
        gh = GitHub(get_token(), a.repo)
        if a.mode == "watch":
            cmd_watch(a, gh)
        else:
            cmd_signal(a, gh)
    except (BridgeError, ValueError, OSError) as exc:
        print(f"BRIDGE_BLOCKED: {exc}", file=sys.stderr)
        return 2
    except KeyboardInterrupt:
        print("Supervisor detenido por el usuario")
    return 0


if __name__ == "__main__":
    sys.exit(main())
