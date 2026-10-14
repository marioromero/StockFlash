#!/usr/bin/env python3
"""Verificador determinista de gates por Feature Packet (harness QA StockFlash).

Uso:
    py -3 scripts/sf_verify.py BOOT-001 [--port 18733] [--no-build]

Gates BOOT-001 (todos obligatorios, en orden):
    G1 composer validate --no-check-publish --no-check-all --strict
    G2 php artisan test
    G3 npm run build
    G4 servidor temporal php artisan serve 127.0.0.1:<puerto> + GET / -> 200
        con cierre garantizado del servidor (try/finally + kill).

Extensible: agregar entradas a GATES_BY_PACKET con lista de gates
declarativos {id, kind: shell|http_serve, cmd, mandatory, timeout_s}.

Reglas:
- Falla (exit 1) ante cualquier gate obligatorio fallido.
- Nunca declara VERIFIED: el veredicto es GATES_PASS / GATES_FAIL.
- No ejecuta migraciones/seeders, no lee ni registra .env/secretos,
  no hace commit/push/deploy, no modifica contratos v2.2.
- Registra comandos, exit codes y extractos acotados de salida
  en docs/work/<PACKET>-VERIFY-<timestamp>.md (sin secretos).
"""
from __future__ import annotations

import argparse
import datetime
import os
import re
import shutil
import socket
import subprocess
import sys
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPORT_DIR = ROOT / "docs" / "work"

# Patrones a redactar si aparecieran en salidas (defensa en profundidad;
# el verificador nunca lee .env ni vuelca entorno).
REDACT_PATTERNS = [
    r"(?i)(api[_-]?key\s*[:=]\s*)([^\s\"']+)",
    r"(?i)(secret\s*[:=]\s*)([^\s\"']+)",
    r"(?i)(token\s*[:=]\s*)([^\s\"']+)",
    r"(?i)(password\s*[:=]\s*)([^\s\"']+)",
    r"(?i)(APP_KEY\s*=\s*)([^\s]+)",
]

FORBIDDEN_SUBSTRINGS = ("migrate", "db:seed", "db:", "tinker")


def redact(text: str) -> str:
    out = text
    for pat in REDACT_PATTERNS:
        out = re.sub(pat, r"\1***", out)
    return out


def tail(text: str, limit: int = 4000) -> str:
    text = text or ""
    if len(text) <= limit:
        return text
    return "...[truncado]...\n" + text[-limit:]


def clean_extract(text: str) -> str:
    """Normaliza extractos del reporte: sin trailing whitespace por linea.

    Higiene determinista (BOOT-001): evita `git diff --check` en reportes
    generados sin alterar el contenido informativo.
    """
    return "\n".join(line.rstrip() for line in (text or "").split("\n"))


def assert_not_forbidden(cmd: list[str]) -> None:
    joined = " ".join(cmd).lower()
    for bad in FORBIDDEN_SUBSTRINGS:
        if bad in joined:
            raise SystemExit(f"BLOQUEADO: comando prohibido en verificador: {' '.join(cmd)}")


def resolve_exec(cmd: list[str]) -> list[str]:
    """Resuelve el ejecutable via PATH; en Windows los .bat/.cmd/.ps1
    se invocan con cmd /c porque CreateProcess no los ejecuta directo."""
    resolved = shutil.which(cmd[0])
    if resolved is None:
        return cmd  # el error FileNotFound quedara registrado en el gate
    if os.name == "nt" and resolved.lower().endswith((".bat", ".cmd", ".ps1")):
        return ["cmd", "/c", resolved, *cmd[1:]]
    return [resolved, *cmd[1:]]


def run_shell(cmd: list[str], timeout_s: int) -> dict:
    assert_not_forbidden(cmd)
    argv = resolve_exec(cmd)
    started = time.time()
    try:
        proc = subprocess.run(
            argv,
            cwd=ROOT,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=timeout_s,
            check=False,
        )
        ok = proc.returncode == 0
        return {
            "cmd": " ".join(cmd),
            "exit": proc.returncode,
            "ok": ok,
            "elapsed_s": round(time.time() - started, 1),
            "stdout": redact(tail(proc.stdout or "")),
            "stderr": redact(tail(proc.stderr or "")),
        }
    except subprocess.TimeoutExpired as exc:
        out = ""
        err = ""
        if exc.stdout:
            out = exc.stdout.decode("utf-8", "replace") if isinstance(exc.stdout, bytes) else str(exc.stdout)
        if exc.stderr:
            err = exc.stderr.decode("utf-8", "replace") if isinstance(exc.stderr, bytes) else str(exc.stderr)
        return {
            "cmd": " ".join(cmd),
            "exit": 124,
            "ok": False,
            "elapsed_s": round(time.time() - started, 1),
            "stdout": redact(tail(out)),
            "stderr": redact(tail("TIMEOUT %ss\n" % timeout_s + err)),
        }


def find_free_port(preferred: int) -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        try:
            s.bind(("127.0.0.1", preferred))
            return preferred
        except OSError:
            s.bind(("127.0.0.1", 0))
            return s.getsockname()[1]


def gate_http_serve(port: int, timeout_s: int = 90) -> dict:
    """Levanta php artisan serve temporal, hace GET / y lo cierra siempre."""
    cmd = ["php", "artisan", "serve", f"--host=127.0.0.1", f"--port={port}"]
    assert_not_forbidden(cmd)
    started = time.time()
    proc = subprocess.Popen(
        resolve_exec(cmd),
        cwd=ROOT,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    url = f"http://127.0.0.1:{port}/"
    status: int | None = None
    body_head = ""
    err_note = ""
    deadline = time.time() + timeout_s
    try:
        # Espera activa hasta que el puerto responde o expira el timeout.
        last_exc = ""
        while time.time() < deadline:
            if proc.poll() is not None:
                rest = ""
                try:
                    rest = proc.communicate(timeout=5)[0] or ""
                except Exception:
                    rest = ""
                err_note = f"servidor termino prematuramente (exit={proc.returncode}). {tail(rest, 1500)}"
                break
            try:
                with urllib.request.urlopen(url, timeout=5) as resp:
                    status = resp.status
                    raw = resp.read(200000).decode("utf-8", "replace")
                    body_head = raw[:2000]
                break
            except Exception as exc:  # puerto aun no listo
                last_exc = str(exc)[:300]
                time.sleep(1.0)
        else:
            err_note = "TIMEOUT esperando respuesta del servidor temporal."
        if status is None and not err_note:
            err_note = f"sin respuesta HTTP. Ultimo error: {last_exc[:300]}"
        checks = {
            "http_200": status == 200,
            "has_stockflash": "StockFlash" in body_head,
        }
        ok = all(checks.values())
        if not ok and not err_note:
            err_note = f"checks={checks} status={status}"
        return {
            "cmd": " ".join(cmd) + "  +  GET /",
            "exit": 0 if ok else 1,
            "ok": ok,
            "elapsed_s": round(time.time() - started, 1),
            "stdout": redact(f"GET {url} -> status={status} checks={checks}\nbody[:1200]={body_head[:1200]}"),
            "stderr": redact(tail(err_note, 2000)),
        }
    finally:
        # Cierre garantizado del servidor temporal.
        try:
            proc.terminate()
            try:
                proc.wait(timeout=10)
            except subprocess.TimeoutExpired:
                proc.kill()
                proc.wait(timeout=10)
        except Exception:
            try:
                proc.kill()
            except Exception:
                pass
        try:
            if proc.stdout:
                proc.stdout.close()
        except Exception:
            pass


# Definicion declarativa de gates por packet. Extender aqui para proximas features.
GATES_BY_PACKET: dict[str, list[dict]] = {
    "BOOT-001": [
        {"id": "G1-composer-validate", "kind": "shell",
         "cmd": ["composer", "validate", "--no-check-publish", "--no-check-all", "--strict"],
         "mandatory": True, "timeout_s": 120},
        {"id": "G2-artisan-test", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 600},
        {"id": "G3-npm-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "G4-http-root", "kind": "http_serve",
         "mandatory": True, "timeout_s": 90},
    ],
    "HARNESS-001": [
        {"id": "H1-harness-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 120},
        {"id": "H2-protected-intact", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "opencode.json", "AGENTS.md", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding/brand-contract.md", "docs/branding/module-identity-map.md"],
         "mandatory": True, "timeout_s": 120},
        {"id": "H3-harness-present", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys;R=P.Path('.');req=['.opencode/agents/stockflash-orchestrator.md','.opencode/commands/sf-run.md'];miss=[f for f in req if not (R/f).exists()];t=(R/'docs/work/features/BRAND-001.md').read_text(encoding='utf-8');ok=not miss and 'status: DRAFT' in t;sys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
    ],
    "BRAND-001": [
        {"id": "B1-svg-valid", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,re,xml.etree.ElementTree as ET\nR=P.Path('.')\nF=['stockflash','stockflash-pos','stockflash-bodega','stockflash-syncwp','stockflash-fiscal','stockflash-compras','stockflash-analitica','stockflash-control']\nA={'#303846','#0856ff','#03c6be','#ffffff','#64748b','#f5f7fa','#e2e8f0'}\nC=re.compile(r'#[0-9A-Fa-f]{3,8}')\nok=True\nfor n in F:\n p=R/('public/images/brand/'+n+'.svg')\n if not p.exists():\n  ok=False\n  continue\n t=p.read_text(encoding='utf-8')\n tl=t.lower()\n wo=re.sub(r'xmlns=\"[^\"]+\"', '', tl)\n bad=('<script' in wo or 'foreignobject' in wo or 'http' in wo or 'data:' in wo or 'base64' in wo or '<image' in wo)\n if bad:\n  ok=False\n  continue\n try:\n  r=ET.fromstring(t)\n except Exception:\n  ok=False\n  continue\n has_xmlns='xmlns=' in tl and 'http://www.w3.org/2000/svg' in t\n has_vb='viewBox' in r.attrib\n has_title=('<title' in tl and '</title>' in tl)\n cols=[c.lower() for c in C.findall(t)]\n cols_ok=all(c in A for c in cols)\n nz=(len(t.strip())>0)\n if not (has_xmlns and has_vb and has_title and cols_ok and nz):\n  ok=False\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "B2-docs-present", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nb=R/'docs/branding/brand-assets.md'\ns1=R/'.agents/skills/stockflash-brand-system/SKILL.md'\ns2=R/'.agents/skills/stockflash-no-brand-drift/SKILL.md'\nok=b.exists() and 'provisional' in b.read_text(encoding='utf-8').lower() and 'brand-assets.md' in s1.read_text(encoding='utf-8') and 'brand-assets.md' in s2.read_text(encoding='utf-8')\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "B3-harness-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 120},
    ],
}


def write_report(packet: str, results: list[dict], verdict: str) -> Path:
    ts = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    path = REPORT_DIR / f"{packet}-VERIFY-{ts}.md"
    lines = [
        f"# {packet} — Reporte de verificacion de gates ({ts})",
        "",
        f"Veredicto: **{verdict}** (GATES_PASS/GATES_FAIL; nunca VERIFIED).",
        "Ejecutor: `scripts/sf_verify.py` (determinista, separado del reviewer).",
        "Nota: resultados EJECUTADOS ahora; no se aceptan valores solo reportados.",
        "",
        "## Gates",
    ]
    for r in results:
        mark = "PASS" if r["ok"] else "FAIL"
        lines += [
            f"### {r['id']} — {mark} (exit={r['exit']}, {r['elapsed_s']}s)",
            f"Comando: `{r['cmd']}`",
            "stdout (extracto):",
            "```",
            clean_extract(r["stdout"][:4000]) or "(vacio)",
            "```",
            "stderr/nota (extracto):",
            "```",
            clean_extract(r["stderr"][:2000]) or "(vacio)",
            "```",
            "",
        ]
    lines += [
        "## Restricciones respetadas",
        "- Sin migraciones/seeders, sin lectura de .env, sin secretos registrados.",
        "- Sin commit/push/deploy, sin cambios a contratos v2.2.",
        "- Servidor temporal cerrado al terminar (try/finally).",
    ]
    path.write_text("\n".join(lines), encoding="utf-8")
    return path


def main() -> int:
    ap = argparse.ArgumentParser(description="Verificador determinista de gates StockFlash.")
    ap.add_argument("packet", help="ID de Feature Packet (p. ej. BOOT-001)")
    ap.add_argument("--port", type=int, default=18733)
    ap.add_argument("--no-build", action="store_true", help="Omitir G3 (solo depuracion; el gate queda como SKIP=FAIL si es obligatorio)")
    args = ap.parse_args()

    packet = args.packet.strip().upper()
    if packet not in GATES_BY_PACKET:
        print(f"FAIL: packet sin gates definidos: {packet}. Definir en GATES_BY_PACKET antes de verificar.")
        return 1
    if packet != "BOOT-001":
        print(f"Nota: verificando {packet} con definicion declarativa correspondiente.")

    results: list[dict] = []
    for gate in GATES_BY_PACKET[packet]:
        gid = gate["id"]
        print(f"== {gid} :: {' '.join(gate['cmd']) if gate['kind']=='shell' else 'serve-temporal + GET /'}")
        if args.no_build and gid.startswith("G3-"):
            results.append({"id": gid, "cmd": "npm run build (omitido por --no-build)",
                            "exit": 99, "ok": False, "elapsed_s": 0.0,
                            "stdout": "", "stderr": "omitido; gate obligatorio => FAIL"})
            print(f"-- {gid} SKIP (tratado como FAIL por ser obligatorio)")
            continue
        if gate["kind"] == "shell":
            r = run_shell(gate["cmd"], gate["timeout_s"])
        elif gate["kind"] == "http_serve":
            port = find_free_port(args.port)
            if port != args.port:
                print(f"-- puerto {args.port} ocupado, usando {port}")
            r = gate_http_serve(port, gate["timeout_s"])
        else:
            r = {"cmd": gid, "exit": 99, "ok": False, "elapsed_s": 0.0,
                 "stdout": "", "stderr": f"kind desconocido: {gate['kind']}"}
        r["id"] = gid
        r["mandatory"] = gate["mandatory"]
        results.append(r)
        print(f"-- {gid} {'PASS' if r['ok'] else 'FAIL'} (exit={r['exit']})")

    mandatory_fail = [r for r in results if r.get("mandatory") and not r["ok"]]
    verdict = "GATES_PASS" if not mandatory_fail else "GATES_FAIL"
    report = write_report(packet, results, verdict)
    print(f"Reporte: {report}")
    print(f"Veredicto: {verdict}")
    if verdict != "GATES_PASS":
        print("FAIL: al menos un gate obligatorio fallo; no presentar como PASS.")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
