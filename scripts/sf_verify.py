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

# Evitar UnicodeEncodeError en consolas Windows (cp1252) al imprimir handoff.
try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

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


def gate_http_serve(port: int, timeout_s: int = 90, path: str = "/", expect: tuple[str, ...] = ("StockFlash",)) -> dict:
    """Levanta php artisan serve temporal, hace GET <path> y lo cierra siempre."""
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
    url = f"http://127.0.0.1:{port}{path}"
    status: int | None = None
    body = ""
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
                    body = raw
                    body_head = raw[:2000]
                break
            except Exception as exc:  # puerto aun no listo
                last_exc = str(exc)[:300]
                time.sleep(1.0)
        else:
            err_note = "TIMEOUT esperando respuesta del servidor temporal."
        if status is None and not err_note:
            err_note = f"sin respuesta HTTP. Ultimo error: {last_exc[:300]}"
        checks = {"http_200": status == 200}
        for marker in expect:
            checks[f"has_{marker}"] = marker in body
        ok = all(checks.values())
        if not ok and not err_note:
            err_note = f"checks={checks} status={status}"
        return {
            "cmd": " ".join(cmd) + f"  +  GET {path}",
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
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,re,xml.etree.ElementTree as ET\nR=P.Path('.')\nF=['stockflash','stockflash-pos','stockflash-bodega','stockflash-syncwp','stockflash-fiscal','stockflash-compras','stockflash-analitica','stockflash-control']\nA={'#303846','#0856ff','#03c6be','#ffffff','#64748b','#f5f7fa','#e2e8f0','#1e7a4c','#4e53a8','#8c5400','#b03a2e','#6c3fa3'}\nC=re.compile(r'#[0-9A-Fa-f]{3,8}')\nok=True\nfor n in F:\n p=R/('public/images/brand/'+n+'.svg')\n if not p.exists():\n  ok=False\n  continue\n t=p.read_text(encoding='utf-8')\n tl=t.lower()\n wo=re.sub(r'xmlns=\"[^\"]+\"', '', tl)\n bad=('<script' in wo or 'foreignobject' in wo or 'http' in wo or 'data:' in wo or 'base64' in wo or '<image' in wo)\n if bad:\n  ok=False\n  continue\n try:\n  r=ET.fromstring(t)\n except Exception:\n  ok=False\n  continue\n has_xmlns='xmlns=' in tl and 'http://www.w3.org/2000/svg' in t\n has_vb='viewBox' in r.attrib\n has_title=('<title' in tl and '</title>' in tl)\n cols=[c.lower() for c in C.findall(t)]\n cols_ok=all(c in A for c in cols)\n nz=(len(t.strip())>0)\n if not (has_xmlns and has_vb and has_title and cols_ok and nz):\n  ok=False\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "B2-docs-present", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nb=R/'docs/branding/brand-assets.md'\ns1=R/'.agents/skills/stockflash-brand-system/SKILL.md'\ns2=R/'.agents/skills/stockflash-no-brand-drift/SKILL.md'\nok=b.exists() and 'provisional' in b.read_text(encoding='utf-8').lower() and 'brand-assets.md' in s1.read_text(encoding='utf-8') and 'brand-assets.md' in s2.read_text(encoding='utf-8')\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "B3-harness-diff-check", "kind": "shell",
          "cmd": ["git", "diff", "--check", "HEAD"],
          "mandatory": True, "timeout_s": 120},
    ],
    "CI-001": [
        {"id": "C1-workflow-yaml", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=R/'.github/workflows/ci.yml'\nif not p.exists():\n sys.exit(1)\nt=p.read_text(encoding='utf-8')\ntl=t.lower()\ntrig=('pull_request' in tl and 'push' in tl and 'workflow_dispatch' in tl and 'main' in tl)\nperm=('permissions:' in tl and 'contents: read' in tl and 'contents: write' not in tl)\nconc=('concurrency:' in tl and 'cancel-in-progress: false' in tl)\nforbid=all(s not in tl for s in ['ssh','deploy','mig'+'rate','db'+':seed','db'+':','mariadb','mysql','secrets.'])\njobs=('setup-php' in tl and 'setup-node' in tl and 'php artisan test' in tl and 'npm ci' in tl and 'npm run typecheck' in tl and 'npm run build' in tl and 'artisan serve' in tl and 'es-cl' in tl)\nvers=('8.3' in t and '24' in t)\nok=trig and perm and conc and forbid and jobs and vers\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "C2-local-ci-equivalente", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,json\nR=P.Path('.')\nok=(R/'composer.lock').exists() and (R/'package-lock.json').exists()\ncj=json.loads((R/'composer.json').read_text(encoding='utf-8'))\npj=json.loads((R/'package.json').read_text(encoding='utf-8'))\nok=ok and 'test' in cj.get('scripts',{}) and 'php artisan test' in json.dumps(cj.get('scripts',{}))\nok=ok and 'typecheck' in pj.get('scripts',{}) and 'build' in pj.get('scripts',{}) and 'vue-tsc --noEmit' in pj['scripts'].get('typecheck','')\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "C3-smoke-config", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nx=(R/'phpunit.xml').read_text(encoding='utf-8')\nci=(R/'.github/workflows/ci.yml').read_text(encoding='utf-8')\nok=('value=\"sqlite\"' in x and ':memory:' in x and 'APP_KEY' in ci and 'es-CL' in ci)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
    ],
    "STAGING-001": [
        {"id": "S1-staging-approved", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfe=(R/'docs/work/features/STAGING-001.md').read_text(encoding='utf-8')\ncu=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=('status: APPROVED' in fe and 'STAGING-001: APPROVED' in cu)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S2-paquete-determinista", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nlock=(R/'composer.lock').exists() and (R/'package-lock.json').exists()\nven=(R/'vendor/autoload.php').exists()\nman=R/'public/build/manifest.json'\nbm=man.exists() and 'resources/js/app.ts' in man.read_text(encoding='utf-8')\nnoenv=not (R/'docs/work/STAGING-001-PACKAGE.md').exists() or ('APP_KEY=' not in (R/'docs/work/STAGING-001-PACKAGE.md').read_text(encoding='utf-8'))\nok=lock and ven and bm and noenv\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S3-transportador-seguro", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=R/'scripts/staging_ftps_one_shot.py'\nif not p.exists():\n sys.exit(1)\nt=p.read_text(encoding='utf-8')\ntl=t.lower()\nhas=('FTP_TLS' in t and 'STAGING_FTPS_PASSWORD' in t and 'stockflash-dev-app' in t and 'AUTH TLS' in t or 'auth tls' in tl)\nforbid=any(s in tl for s in ['rm -rf','shutil.rmtree','remove_recursivo','mig'+'rate','db'+':seed','db'+':','password = \\'','password = \"'])\nnohard=(t.count('admin@stockflash-dev')<=2 and 'getpass' in tl or 'getenv' in t)\nok=has and not forbid and nohard\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S4-marcador-publico", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,glob\nR=P.Path('.')\nfs=glob.glob(str(R/'docs/work/STAGING-001-MARKER-*.md'))\nok=False\nfor f in fs:\n t=P.Path(f).read_text(encoding='utf-8')\n if 'MARKER_PASS' in t and 'https://stockflash-dev.tusitioexpress.cl/' in t and 'retirado' in t.lower():\n  ok=True\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S5-manifest-rollback", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,glob\nR=P.Path('.')\nfs=glob.glob(str(R/'docs/work/STAGING-001-MANIFEST-*.md'))\nok=False\nfor f in fs:\n t=P.Path(f).read_text(encoding='utf-8')\n if 'ROLLBACK_READY' in t and 'inventario' in t.lower() and '.env' in t:\n  ok=True\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S6-smoke-https", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,glob\nR=P.Path('.')\nfs=glob.glob(str(R/'docs/work/STAGING-001-SMOKE-*.md'))\nok=False\nfor f in fs:\n t=P.Path(f).read_text(encoding='utf-8')\n if 'SMOKE_PASS_200' in t and 'StockFlash' in t and 'https://stockflash-dev.tusitioexpress.cl/' in t:\n  ok=True\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S7-reporte-tecnico", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,glob\nR=P.Path('.')\nfs=glob.glob(str(R/'docs/work/STAGING-001-REPORT-*.md'))\nok=False\nfor f in fs:\n t=P.Path(f).read_text(encoding='utf-8')\n if all(k in t for k in ['S1','S2','S3','S4','S5','S6','S7','SHA']) and ('BLOCKED' in t or 'GATES_PASS' in t):\n  ok=True\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
    ],
    "AUTH-001": [
        {"id": "A1-auth-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfe=(R/'docs/work/features/AUTH-001.md').read_text(encoding='utf-8')\ncu=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in fe or 'status: VERIFIED' in fe) and 'AUTH-001' in cu)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "A2-modelo-user-schema-db001", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,re\nR=P.Path('.')\np=R/'app/Models/User.php'\nt=p.read_text(encoding='utf-8')\nreq=['email','password','global_role_id','default_branch_id','is_active','failed_login_attempts','locked_until','password_changed_at','last_login_at','last_login_ip','mfa_enabled','pin_hash']\nok=all(r in t for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a3-controladores-aut", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/Auth/LoginController.php','app/Http/Controllers/Auth/LogoutController.php','app/Http/Controllers/Auth/PasswordController.php']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a4-comando-admin-inicial", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Console/Commands/AdminCreateCommand.php']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a5-rutas-auth", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'routes/web.php').read_text(encoding='utf-8')\nreq=['login','logout','password','dashboard']\nok=all(r in t for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a6-middleware-rbac", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=R/'app/Http/Middleware/EnsureBranchAccess.php'\nok=p.exists()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a7-dashboard-protegido", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['resources/js/Pages/Dashboard.vue','resources/js/Pages/Auth/Login.vue']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a8-tests-auth", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['tests/Feature/Auth/LoginTest.php','tests/Feature/Auth/PasswordTest.php','tests/Feature/Auth/RbacTest.php']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a9-documentacion-usuario", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['documentacion/general/acceso.md','documentacion/general/cambio-contrasena.md','documentacion/README.md']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "a10-pruebas-php-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 300},
        {"id": "a11-typecheck-vue", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "a12-build-vite", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
    ],
    "USERS-001": [
        {"id": "U1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/USERS-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'execution_mode: ONE_WAVE_ALL_16' in p and 'publication_mode: AUTO_GIT' in p and ('USERS-001: APPROVED' in c or 'USERS-001: VERIFIED' in c))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "U2-archivos-backend-ui", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/UserController.php','app/Support/UserAccessFingerprint.php','app/Support/AuditLogger.php','app/Models/User.php','resources/js/Pages/Users/Index.vue','resources/js/Pages/Users/Form.vue','documentacion/modulos/usuarios/administracion-usuarios.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "U3-archivos-pruebas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nnames=['UserCrudTest','UserAuthorizationTest','UserBranchAssignmentTest','UserStateTransitionTest','UserLastAdminTest','UserSessionInvalidationTest','UserAuditTest','UserLimitTest']\nreq=['tests/Feature/Users/'+n+'.php' for n in names]+['tests/Feature/Branches/BranchLicenseGuardTest.php']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "U4-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "U5-pruebas-usuarios-16-decisiones", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "U6-regresion-sucursales-licencia", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "U7-regresion-autenticacion", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "U8-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "U9-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "U10-vite-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "U11-guia-indice", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\na=R/'documentacion/modulos/usuarios/administracion-usuarios.md'\ni=R/'documentacion/README.md'\nsys.exit(0 if a.exists() and i.exists() and 'usuarios' in i.read_text(encoding='utf-8').lower() else 1)"],
         "mandatory": True, "timeout_s": 60},
    ],
    "BRANCH-001": [
        {"id": "BN1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfe=(R/'docs/work/features/BRANCH-001.md').read_text(encoding='utf-8')\ncu=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=('status: APPROVED' in fe and 'BRANCH-001: APPROVED' in cu)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "BN2-archivos-crud", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/BranchController.php','app/Http/Requests/BranchRequest.php','app/Support/AuditLogger.php','resources/js/Pages/Branches/Index.vue','resources/js/Pages/Branches/Form.vue']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "BN3-rutas-permiso-y-sucursal-activa", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nm=(R/'app/Http/Middleware/EnsureBranchAccess.php').read_text(encoding='utf-8')\nok=('/branches' in w and 'can.permission:settings.manage' in w and 'is_active' in m and 'abort(403' in m)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "BN4-auditoria-transaccional", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nc=(R/'app/Http/Controllers/BranchController.php').read_text(encoding='utf-8')\nok=(c.count('transaction(') >= 5 and 'AuditLogger' in c and c.index('transaction(') < c.index('auditLogger->log'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "BN5-tests-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "BN6-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "BN7-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "BN8-typecheck-vue", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "BN9-build-vite", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "BN10-documentacion-usuario", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nok=(R/'documentacion/modulos/sucursales/administracion-sucursales.md').exists() and 'sucursales' in (R/'documentacion/README.md').read_text(encoding='utf-8').lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
    ],
    "DOCS-001": [
        {"id": "D1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfe=(R/'docs/work/features/DOCS-001.md').read_text(encoding='utf-8')\ncu=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=('status: APPROVED' in fe and 'DOCS-001' in cu)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D2-archivos-pr2-presentes", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['public/docs/index.html','docs/harness/GUIA-HTML-USUARIO.md','docs/harness/CONTEXTO-CHATGPT.md','docs/work/GUIA-HTML-ESTADO.md']\nok=all((R/r).exists() for r in req)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D3-reglas-autoria-y-handoff", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\na=(R/'AGENTS.md').read_text(encoding='utf-8')\nrun=(R/'.opencode/commands/sf-run.md').read_text(encoding='utf-8')\npub=(R/'.opencode/commands/sf-publish.md').read_text(encoding='utf-8')\nrev=(R/'.opencode/commands/sf-review.md').read_text(encoding='utf-8')\nfeat=(R/'.opencode/commands/sf-feature.md').read_text(encoding='utf-8')\nhand=(R/'.opencode/commands/sf-handoff.md').read_text(encoding='utf-8')\nok=('Manual HTML de usuarios' in a and 'Continuidad ChatGPT' in a and 'GUIA_PENDIENTE_CHATGPT' in run and 'CONTEXTO-CHATGPT.md' in run and 'GUIA_ACTUALIZADA' in pub and 'GUIA_PENDIENTE_CHATGPT' in rev and 'Control de documentaci' in feat and 'GUIA_PENDIENTE_CHATGPT' in hand)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D4-ruta-docs-y-enlace-ayuda", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nok=('docs.index' in w and 'docs/index.html' in w and 'public_path' in w and 'href=\"/docs/\"' in d and 'Ayuda' in d)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D5-html-estructura-y-seguridad", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nh=(R/'public/docs/index.html').read_text(encoding='utf-8')\nhl=h.lower()\nok=('lang=\"es-CL\"' in h and 'id=\"search\"' in h and 'id=\"topics-nav\"' in h and 'class=\"sidebar\"' in h and 'details class=\"accordion\"' in h and 'aria-label' in h and '@media(max-width:820px)' in h and 'prefers-reduced-motion' in h and '<script src=' not in hl and 'fetch(' not in hl and 'xmlhttprequest' not in hl and 'type=\"password\"' not in hl and '<form' not in hl)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D6-bitacora-documental", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nok=('content_owner: ChatGPT' in s and 'guide_status: INTEGRADA_EN_MAIN' in s and 'pending_docs:' in s and 'USERS-001' in s)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D7-tests-docs", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Docs"],
         "mandatory": True, "timeout_s": 600},
        {"id": "D8-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "D9-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "D10-typecheck-vue", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "D11-build-vite", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "D12-http-docs", "kind": "http_serve",
         "path": "/docs/",
         "expect": ["id=\"search\"", "topics-nav"],
         "mandatory": True, "timeout_s": 120},
        {"id": "D13-automatizacion-estado-guia", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'scripts/sf_verify.py').read_text(encoding='utf-8')\nok=('guide_handoff' in s and 'GUIA_PENDIENTE_CHATGPT' in s and 'GUIA_ACTUALIZADA' in s)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "D14-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "D15-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
    ],
    "ROADMAP-001": [
        {"id": "RM1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfe=(R/'docs/work/features/ROADMAP-001.md').read_text(encoding='utf-8')\ncu=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in fe or 'status: VERIFIED' in fe) and 'ROADMAP-001' in cu)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "RM2-archivos-pr3-presentes", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['resources/roadmap/index.html','docs/harness/ROADMAP-HTML-PROYECTO.md','docs/work/ROADMAP-HTML-ESTADO.md']\nok=all((R/r).exists() for r in req) and not (R/'public/roadmap/index.html').exists()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "RM3-ruta-protegida-no-publica", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nok=('roadmap.index' in w and 'can.permission:settings.manage' in w and 'resource_path(' + chr(39) + 'roadmap/index.html' + chr(39) + ')' in w and 'public_path(' + chr(39) + 'roadmap' not in w)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "RM4-boton-dashboard-autorizado", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nok=('v-if=\"canViewRoadmap\"' in d and 'href=\"/roadmap\"' in d and 'Avance del proyecto' in d and 'rel=\"noopener noreferrer\"' in d)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "RM5-html-roadmap-integridad", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nh=(R/'resources/roadmap/index.html').read_text(encoding='utf-8')\nhl=h.lower()\nok=('lang=\"es-CL\"' in h and 'Avance del proyecto | StockFlash' in h and 'details class=\"step\"' in h and 'id=\"expand\"' in h and 'id=\"collapse\"' in h and '@media(max-width:640px)' in h and 'prefers-reduced-motion' in h and all(('R'+str(i)) in h for i in range(11)) and '<script src=' not in hl and 'fetch(' not in hl and 'xmlhttprequest' not in hl and 'type=\"password\"' not in hl and '<form' not in hl and 'http://' not in hl and 'https://' not in hl)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "RM6-tests-roadmap", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Roadmap"],
         "mandatory": True, "timeout_s": 600},
        {"id": "RM7-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "RM8-typecheck-vue", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "RM9-build-vite", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "RM10-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "RM11-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "RM12-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "RM13-regresion-docs", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Docs"],
         "mandatory": True, "timeout_s": 600},
        {"id": "RM14-automatizacion-estado-roadmap", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'scripts/sf_verify.py').read_text(encoding='utf-8')\nok=('roadmap_handoff' in s and 'ROADMAP_PENDIENTE_CHATGPT' in s and 'ROADMAP_ACTUALIZADA' in s)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "RM15-bitacora-roadmap", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nok=('content_owner: ChatGPT' in s and 'roadmap_content_status: INTEGRADA_EN_MAIN' in s and 'pending:' in s and 'R7' in s)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
    ],
    "HCTX-001": [
        {"id": "X1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfe=(R/'docs/work/features/HCTX-001.md').read_text(encoding='utf-8')\ncu=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in fe or 'status: VERIFIED' in fe) and 'publication_mode: AUTO_GIT' in fe and 'execution_mode: ONE_SMALL_WAVE' in fe and 'HCTX-001' in cu)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "X2-current-compacto", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nb=(R/'docs/work/CURRENT.md').read_bytes()\nt=b.decode('utf-8')\nok=(2000<=len(b)<=4096 and all(s in t for s in ['HCTX-001','USERS-001: VERIFIED','BOOT-001','STAGING-001: APPROVED','VERIFIED']) and len(t.splitlines())<=70)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "X3-mapa-selectivo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/harness/MAPA-DE-LECTURA.md').read_text(encoding='utf-8').lower()\nok=all(s in t for s in ['agents.md','current.md','index-requisitos.md','grep','secciones','por defecto','contexto-chatgpt.md','expandir'])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "X4-contradicciones-corregidas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nl=(R/'LEEME-PRIMERO.md').read_text(encoding='utf-8')\nw=(R/'docs/harness/WORKFLOW-SDD-HIBRIDO.md').read_text(encoding='utf-8')\nok=('aún no está en GitHub' not in l and 'aún NO VERIFIED' not in l and 'BOOT-001' in l and 'VERIFIED' in l and '`ask`' not in w and 'GITHUB-HANDOFF-COMPACT.md' in w and 'allow' in w and 'deny' in w)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "X5-alcance-sin-infraestructura-nueva", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain=v1','--untracked-files=all'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nallowed={'docs/work/CURRENT.md','docs/harness/MAPA-DE-LECTURA.md','LEEME-PRIMERO.md','docs/harness/WORKFLOW-SDD-HIBRIDO.md','scripts/sf_verify.py','.opencode/agents/stockflash-implementer.md'}\nbad=[]\nfor line in out.splitlines():\n    if len(line)<4:\n        continue\n    p=line[3:].strip().strip(chr(34))\n    if ' -> ' in p:\n        p=p.split(' -> ')[-1].strip()\n    if p.startswith('.run/'):\n        continue\n    if line[:2].strip()=='??':\n        if not p.startswith('docs/work/HCTX-001-'):\n            bad.append(p)\n    elif p not in allowed:\n        bad.append(p)\nsys.exit(0 if not bad else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "X6-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "X7-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "X8-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "X9-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "X10-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "database/migrations", "docs/branding", "docs/work/GUIA-HTML-ESTADO.md", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "X11-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CFG-001": [
        {"id": "CF1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CFG-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and ('CFG-001: APPROVED' in c or 'CFG-001: VERIFIED' in c))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CF2-archivos-backend-ui-tests", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/Settings/ReceiptSettingsController.php','app/Http/Requests/ReceiptSettingsRequest.php','routes/web.php','resources/js/Pages/Settings/Receipt.vue','resources/js/Pages/Dashboard.vue','tests/Feature/Settings/ReceiptSettingsTestCase.php','tests/Feature/Settings/ReceiptSettingsAuthorizationTest.php','tests/Feature/Settings/ReceiptSettingsTest.php']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CF3-rutas-middleware-enlace", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nok=(w.count('/settings/receipt')>=2 and 'settings.receipt.edit' in w and 'settings.receipt.update' in w and 'can.permission:settings.manage' in w and 'password.change' in w and 'verify.session' in w and '/settings/receipt' in d and 'Ajustes de comprobante' in d and 'settings.manage' in d)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CF4-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CF5-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CF6-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CF7-tests-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CF8-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CF9-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CF10-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CF11-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CF12-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CF13-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CF14-guia-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\np=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nok=('CFG-001' in s and p.upper() not in no_pending) or ('CFG-001 VERIFIED' in s and p.upper() in no_pending)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CF15-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CFG-002": [
        {"id": "CFG2-1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CFG-002.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'CFG-002' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-2-archivos-backend-ui-tests", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/Settings/BusinessSettingsController.php','app/Http/Requests/BusinessSettingsRequest.php','resources/js/Pages/Settings/Business.vue','resources/js/Pages/Dashboard.vue','tests/Feature/Settings/BusinessSettingsTestCase.php','tests/Feature/Settings/BusinessSettingsAuthorizationTest.php','tests/Feature/Settings/BusinessSettingsTest.php']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-3-rutas-middleware-enlace-sin-patch", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nok=(w.count('/settings/business')>=2 and 'settings.business.edit' in w and 'settings.business.update' in w and 'can.permission:settings.manage' in w and 'password.change' in w and 'verify.session' in w and '/settings/business' in d and 'Datos del comercio' in d and 'settings.manage' in d and 'v-if=\"canManageSettings\"' in d and \"Route::patch('/settings/business'\" not in w and \"Route::match(['put', 'patch'], '/settings/business'\" not in w and \"Route::match(['patch', 'put'], '/settings/business'\" not in w)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-4-allowlist-auditoria-estatica", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nc=(R/'app/Http/Controllers/Settings/BusinessSettingsController.php').read_text(encoding='utf-8')\nkeys=['business_name','fiscal_id','fiscal_name','commercial_activity','fiscal_address','fiscal_commune','responsible_name','responsible_email','responsible_phone']\nok=(all(k in c for k in keys) and 'recordRead' in c and \"whereIn('config_key', self::KEYS)\" in c and 'pluck(' in c and 'core.business_settings.pii_viewed' in c and 'core.business_settings.updated' in c and 'select' not in c.lower())\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-5-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-6-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CFG2-8-tests-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG2-9-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG2-10-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG2-11-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG2-12-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CFG2-13-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CFG2-14-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG2-15-guia-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\np=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nok=('CFG-002' in s and p.upper() not in no_pending) or ('CFG-002 VERIFIED' in s and p.upper() in no_pending)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG2-16-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CFG-003": [
        {"id": "CFG3-1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CFG-003.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'CFG-003' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-2-archivos-backend-ui-tests", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/Settings/GeneralSettingsController.php','app/Http/Requests/GeneralSettingsRequest.php','resources/js/Pages/Settings/General.vue','resources/js/Pages/Dashboard.vue','tests/Feature/Settings/GeneralSettingsTestCase.php','tests/Feature/Settings/GeneralSettingsAuthorizationTest.php','tests/Feature/Settings/GeneralSettingsTest.php']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-3-rutas-middleware-enlace-sin-patch", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nok=(w.count('/settings/general')>=2 and 'settings.general.edit' in w and 'settings.general.update' in w and 'can.permission:settings.manage' in w and 'password.change' in w and 'verify.session' in w and '/settings/general' in d and 'Preferencias generales' in d and 'settings.manage' in d and 'v-if=\"canManageSettings\"' in d and \"Route::patch('/settings/general'\" not in w and \"Route::match(['put', 'patch'], '/settings/general'\" not in w and \"Route::match(['patch', 'put'], '/settings/general'\" not in w)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-4-allowlist-auditoria-estatica", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nc=(R/'app/Http/Controllers/Settings/GeneralSettingsController.php').read_text(encoding='utf-8')\nkeys=['country','currency_code','currency_decimals','timezone','locale','prices_include_tax','cash_rounding_step']\nok=(all(k in c for k in keys) and \"whereIn('config_key', self::KEYS)\" in c and 'pluck(' in c and 'core.general_settings.updated' in c and 'transaction(' in c and 'select' not in c.lower())\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-5-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-6-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CFG3-8-tests-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG3-9-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG3-10-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG3-11-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG3-12-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CFG3-13-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CFG3-14-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CFG3-15-guia-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\np=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nok=('CFG-003' in s and p.upper() not in no_pending) or ('CFG-003 VERIFIED' in s and p.upper() in no_pending) or ('CFG-003 VERIFIED' in s and p.upper() in no_pending)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CFG3-16-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CORESEQ-001": [
        {"id": "CQ1", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CORESEQ-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'CORESEQ-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CQ2", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Support/DocumentSequenceAllocator.php','tests/Feature/Core/DocumentSequenceAllocatorTest.php','docs/work/CORESEQ-001-NOTES-20261116.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CQ3", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/DocumentSequenceAllocator.php').read_text(encoding='utf-8')\nneedle='DB'+'::transaction'\nreq=['insertOrIgnore','lockForUpdate']\nforbid=['Route::','Illuminate\\\\Http','beginTransaction','->commit(','->rollBack(']\nok=all(k in s for k in req) and needle in s and not any(f in s for f in forbid)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CQ4", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','app/Http','routes','resources/js','public'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CQ5", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CQ6", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CQ7", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CQ8", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CQ9", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CQ10", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CQ11", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CQ12", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CQ13", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CQ14", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CQ15", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CQ16", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CORELIC-001": [
        {"id": "CL1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CORELIC-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'CORELIC-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL2-archivos-contrato-stub-resolver-tests-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Contracts/LicenseValidator.php','app/Support/License/InstallationStatusReport.php','app/Support/License/LocalLicenseStub.php','app/Support/License/LicenseReadOnlyRule.php','app/Support/License/LicenseSnapshot.php','app/Support/License/LicenseStateResolver.php','app/Http/Controllers/DashboardController.php','resources/js/Pages/Dashboard.vue','tests/Feature/Core/LicenseValidatorTest.php','tests/Feature/Core/DashboardLicenseNoticeTest.php','docs/work/CORELIC-001-NOTES-20261116.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL3-contrato-y-stub-sin-salidas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ni=(R/'app/Contracts/LicenseValidator.php').read_text(encoding='utf-8')\ns=(R/'app/Support/License/LocalLicenseStub.php').read_text(encoding='utf-8')\nr=(R/'app/Support/License/LicenseStateResolver.php').read_text(encoding='utf-8')\nmethods=['isValid','getLicenseInfo','getLimits','hasModule','getDaysRemaining','reportStatus']\nforbid=['Http::','curl_','fsockopen','stream_socket_client','GuzzleHttp','shell_exec','proc_open','->update(','->insert(','->delete(','updateOrInsert']\nok=(all(m in i for m in methods) and 'implements LicenseValidator' in s and \"table('license_states')\" not in s and 'license_states' in r and not any(f in s or f in r for f in forbid))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL4-banner-sanitizado-sin-secretos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nc=(R/'app/Http/Controllers/DashboardController.php').read_text(encoding='utf-8')\nr=(R/'app/Support/License/LicenseStateResolver.php').read_text(encoding='utf-8')\nv=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nt=(R/'tests/Feature/Core/LicenseValidatorTest.php').read_text(encoding='utf-8')\nd=(R/'tests/Feature/Core/DashboardLicenseNoticeTest.php').read_text(encoding='utf-8')\nsecrets=['installation_key','signed_payload','signature']\nok=('license_notice' in c and 'LicenseStateResolver' in c and 'first(self::COLUMNS)' in r and \"'status', 'expires_at', 'grace_until'\" in r and 'sf-license-banner' in v and 'license_notice' in v and 'role=' in v and 'DashboardController' in w and 'Http::assertNothingSent' in t and 'SECRET-INSTALLATION-KEY' in d and not any(x in v for x in secrets))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL5-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL6-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CL8-tests-core", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CL9-tests-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CL10-tests-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CL11-tests-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CL12-tests-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CL13-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CL14-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CL15-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CL16-guia-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\np=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nok=('CORELIC-001' in s and p.upper() not in no_pending) or ('CORELIC-001 VERIFIED' in s and p.upper() in no_pending)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CL17-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "COREMOD-001": [
        {"id": "CM1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/COREMOD-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'COREMOD-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM2-servicio-tests-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Support/Modules/ModuleCatalog.php','app/Support/Modules/ModuleManager.php','tests/Feature/Core/ModuleManagerTest.php','docs/work/COREMOD-001-NOTES-20261116.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM3-catalogo-exacto", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,json,sys\ncode=\"require 'vendor/autoload.php'; echo json_encode(\\\\App\\\\Support\\\\Modules\\\\ModuleCatalog::catalog(), JSON_THROW_ON_ERROR);\"\nr=subprocess.run(['php','-r',code],capture_output=True,text=True,encoding='utf-8',errors='replace')\nout=(r.stdout or '').strip()\ntry:\n got=json.loads(out)\nexcept Exception:\n sys.exit(1)\nexpected={'core':{'hard':[],'soft':[]},'catalog':{'hard':['core'],'soft':[]},'inventory':{'hard':['catalog'],'soft':[]},'purchasing':{'hard':['inventory'],'soft':[]},'sales':{'hard':['catalog'],'soft':['inventory','customers','sellers','cash_control','channels']},'pos':{'hard':['sales'],'soft':['cash_control','customers','sellers']},'cash_control':{'hard':['sales'],'soft':['pos']},'customers':{'hard':['core'],'soft':[]},'sellers':{'hard':['core'],'soft':['sales']},'promotions':{'hard':['catalog'],'soft':['sales']},'combos':{'hard':['catalog'],'soft':['inventory']},'price_lists':{'hard':['catalog'],'soft':['customers','channels']},'fiscal':{'hard':['sales'],'soft':['customers','channels']},'channels':{'hard':['catalog'],'soft':['inventory','sales','customers']},'woo_sync':{'hard':['channels'],'soft':[]},'reports_basic':{'hard':['core'],'soft':['*']},'reports_advanced':{'hard':['reports_basic'],'soft':[]},'audit_log':{'hard':['core'],'soft':[]},'customer_credit':{'hard':['customers','sales'],'soft':[]},'loyalty':{'hard':['customers','sales'],'soft':[]},'offline':{'hard':['pos'],'soft':[]},'lots_serials':{'hard':['inventory'],'soft':[]}}\nsys.exit(0 if got==expected else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CM4-servicio-sin-rutas-ui-http", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['app/Support/Modules/ModuleCatalog.php','app/Support/Modules/ModuleManager.php']\nforbid=['Route::','Illuminate\\\\Http','Http::','response(','view(','redirect(','header(','curl_','fsockopen','GuzzleHttp','->json(']\nbad=[]\nfor f in files:\n t=(R/f).read_text(encoding='utf-8')\n bad += [f+':'+s for s in forbid if s in t]\nsys.exit(0 if not bad else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM5-transaccion-locks-license", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'app/Support/Modules/ModuleManager.php').read_text(encoding='utf-8')\nneedle='DB'+'::transaction'\nreq=['lockForUpdate','LicenseReadOnlyRule','AuditLogger',\"orderBy('module_code')\",\"orderBy('id')\"]\nforbid=['beginTransaction','->commit(','->rollBack(']\nok=needle in t and all(k in t for k in req) and not any(f in t for f in forbid)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM6-sin-cambios-http-rutas-ui-public-config", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','app/Http','routes','resources/js','public','database','config/modules.php'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM7-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM8-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CM9-tests-core", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM10-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM11-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM12-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM13-regresion-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM14-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CM15-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CM16-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM17-metadatos-internos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nline=next((l for l in s.splitlines() if l.startswith('internal_only_features:')), '')\nok=('COREMOD-001' in line and 'servicio interno invisible' in line and 'public/docs/index.html' not in line)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM18-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "COREPIN-001": [
        {"id": "CP1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/COREPIN-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'COREPIN-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP2-servicio-tests-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Support/Authorization/PinAuthorizationService.php','app/Support/Authorization/AuthorizationDeniedException.php','tests/Feature/Core/PinAuthorizationServiceTest.php','docs/work/COREPIN-001-NOTES-20261116.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP3-servicio-sin-rutas-ui-http", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'app/Support/Authorization/PinAuthorizationService.php').read_text(encoding='utf-8')\nforbid=['Route::','Illuminate\\\\Http','Http::','response(','view(','redirect(','header(','curl_','GuzzleHttp','->json(']\nbad=[s for s in forbid if s in t]\nsys.exit(0 if not bad else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP4-invariantes-servicio", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'app/Support/Authorization/PinAuthorizationService.php').read_text(encoding='utf-8')\nneedle='DB'+'::transaction'\nreq=['lockForUpdate','Hash::check','authorization_overrides','AuditLogger','is_security_event']\nforbid=[\"'pin_hash' =>\",'\"pin_hash\" =>']\nok=needle in t and all(k in t for k in req) and not any(f in t for f in forbid)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP5-sin-cambios-esquema-ui-config", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database/migrations','app/Http','routes','resources/js','public','config/modules.php'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP6-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CP8-tests-core", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CP9-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CP10-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CP11-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CP12-regresion-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CP13-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CP14-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CP15-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CP16-metadatos-internos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nline=next((l for l in s.splitlines() if l.startswith('internal_only_features:')), '')\nok=('COREPIN-001' in line and 'servicio interno invisible' in line and 'public/docs/index.html' not in line)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CP17-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CATDB-001": [
        {"id": "CD1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CATDB-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'CATDB-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CD2-migracion-tests-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['database/migrations/2026_11_16_000021_create_catalog_p0_tables.php','tests/Feature/Catalog/CatalogSchemaTest.php','tests/Feature/Catalog/CatalogConstraintsTest.php','tests/Feature/Catalog/CatalogMigrationSafetyTest.php','docs/work/CATDB-001-NOTES-20261116.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CD3-lote-core-intacto", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database/migrations','database/seeders'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nbad=[]\nfor l in out.splitlines():\n    if not l.strip():\n        continue\n    if not l.startswith('?? '):\n        bad.append(l)\n        continue\n    p=l[3:].strip()\n    if not (p.startswith('database/migrations/2026_11_16_') and p.endswith('.php')):\n        bad.append(l)\nsys.exit(0 if not bad else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CD4-test-schema", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog/CatalogSchemaTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CD5-test-constraints", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog/CatalogConstraintsTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CD6-test-migration-safety", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog/CatalogMigrationSafetyTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CD7-test-catalogo", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CD8-sqlite-local-sin-dsn", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nxml=(R/'phpunit.xml').read_text(encoding='utf-8')\ntests=[(R/'tests/Feature/Catalog'/n).read_text(encoding='utf-8') for n in ['CatalogSchemaTest.php','CatalogConstraintsTest.php','CatalogMigrationSafetyTest.php']]\nsrc=(R/'database/migrations/2026_11_16_000021_create_catalog_p0_tables.php').read_text(encoding='utf-8')\nforbid=['DB_HOST','mysql://','password']\nok=('DB_CONNECTION' in xml and 'sqlite' in xml and ':memory:' in xml and all(('getDriverName' in t and ':memory:' in t) for t in tests) and 'getDriverName' in src and 'in_array' in src and \"fullText('name')\" in src and 'mysql' in src and 'mariadb' in src and not any(f in s for s in tests+[src] for f in forbid))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CD9-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CD10-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CD11-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CD12-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CD13-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/ROADMAP-HTML-ESTADO.md", "docs/work/GUIA-HTML-ESTADO.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CD14-notas-maria-remota", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/CATDB-001-NOTES-20261116.md').read_text(encoding='utf-8')\ntables=['tax_rates','categories','brands','tags','attributes','attribute_values','products','product_variants','product_barcodes','product_tag','product_variant_attribute_values']\nok=('maria=NOT_RUN' in t and 'remote_db=UNTOUCHED' in t and all(x in t for x in tables) and 'MariaDB' in t)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CD15-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CATMAST-001": [
        {"id": "CM1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CATMAST-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'CATMAST-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM2-archivos-backend-ui-tests-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/CatalogMastersController.php','app/Http/Middleware/CheckPermission.php','app/Models/TaxRate.php','app/Models/Category.php','app/Models/Brand.php','app/Models/Tag.php','app/Models/Attribute.php','app/Models/AttributeValue.php','app/Http/Requests/Catalog/TaxRateRequest.php','app/Http/Requests/Catalog/CategoryRequest.php','app/Http/Requests/Catalog/BrandRequest.php','app/Http/Requests/Catalog/TagRequest.php','app/Http/Requests/Catalog/AttributeRequest.php','app/Http/Requests/Catalog/AttributeValueRequest.php','resources/js/Pages/Catalog/Masters.vue','resources/js/Pages/Dashboard.vue','tests/Feature/Catalog/CatalogMastersTestCase.php','tests/Feature/Catalog/CatalogMastersAuthorizationTest.php','tests/Feature/Catalog/CatalogMastersCrudTest.php','docs/work/CATMAST-001-NOTES-20261117.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM3-rutas-middleware-enlace-sin-branch-id", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nm=(R/'app/Http/Middleware/CheckPermission.php').read_text(encoding='utf-8')\nstart=w.index('Maestros del cat')\nend=w.index('global de sucursales', start)\nblock=w[start:end]\nrequired=['catalog.masters.index','catalog.masters.tax-rates.store','catalog.masters.categories.store','catalog.masters.brands.store','catalog.masters.tags.store','catalog.masters.attributes.store','catalog.masters.attribute-values.store']\nok=(all(x in w for x in required) and block.count('/catalog/masters')>=18 and 'can.permission:catalog.view' in block and 'can.permission:catalog.manage' in block and 'password.change' in w and 'verify.session' in w and \"->middleware('branch.access')\" not in block and '/catalog/masters' in d and 'Maestros de cat' in d and 'canViewCatalog' in d and 'catalog.view' in d and 'catalog.manage' in d and \"input('branch_id')\" not in m and \"route('branch')\" in m)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM4-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "config/modules.php", "database/seeders"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM5-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM6-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CM7-tests-catalog", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM8-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM9-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM10-regresion-core", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM11-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM12-regresion-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM13-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CM14-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CM15-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CM16-guia-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\np=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nok=('CATMAST-001' in s and p.upper() not in no_pending and 'CATMAST-001' in next((l for l in r.splitlines() if l.startswith('in_progress:')), ''))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CM17-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "CATPROD-001": [
        {"id": "CPRD1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/CATPROD-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'CATPROD-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD2-archivos-backend-ui-tests-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/Catalog/ProductController.php','app/Http/Controllers/Catalog/ProductVariantController.php','app/Http/Controllers/Catalog/ProductBarcodeController.php','app/Http/Requests/Catalog/ProductRequest.php','app/Http/Requests/Catalog/ProductVariantRequest.php','app/Http/Requests/Catalog/ProductBarcodeRequest.php','app/Models/Product.php','app/Models/ProductVariant.php','app/Models/ProductBarcode.php','app/Support/Catalog/ProductCatalogService.php','app/Support/Catalog/ProductPresenter.php','app/Support/Catalog/CatalogWriteGuard.php','app/Support/Catalog/CatalogUniqueViolation.php','resources/js/Pages/Catalog/Products.vue','resources/js/Pages/Dashboard.vue','tests/Feature/Catalog/ProductTestCase.php','tests/Feature/Catalog/ProductAuthorizationTest.php','tests/Feature/Catalog/ProductCrudTest.php','tests/Feature/Catalog/ProductVariantTest.php','tests/Feature/Catalog/ProductBarcodeTest.php','tests/Feature/Catalog/ProductSearchTest.php','tests/Feature/Catalog/ProductAuditRollbackTest.php','docs/work/CATPROD-001-NOTES-20261117.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD3-rutas-middleware-enlace-sin-branch-id", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\nm=(R/'app/Http/Middleware/CheckPermission.php').read_text(encoding='utf-8')\nstart=w.index('Productos, variantes y c')\nend=w.index('Administraci', start)\nblock=w[start:end]\nrequired=['catalog.products.index','catalog.products.show','catalog.products.store','catalog.products.update','catalog.products.destroy','catalog.products.variants.store','catalog.products.variants.update','catalog.products.variants.destroy','catalog.products.barcodes.store','catalog.products.barcodes.update','catalog.products.barcodes.destroy']\nok=(all(x in block for x in required) and block.count('/catalog/products')>=11 and 'can.permission:catalog.view' in block and 'can.permission:catalog.manage' in block and 'password.change' in w and 'verify.session' in w and \"->middleware('branch.access')\" not in block and '/catalog/products' in d and 'Productos' in d and 'canViewCatalog' in d and 'catalog.view' in d and \"input('branch_id')\" not in m and \"route('branch')\" in m)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD4-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD5-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD6-costo-protegido-estatico", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'app/Support/Catalog/ProductPresenter.php').read_text(encoding='utf-8')\nr1=(R/'app/Http/Requests/Catalog/ProductRequest.php').read_text(encoding='utf-8')\nr2=(R/'app/Http/Requests/Catalog/ProductVariantRequest.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Catalog/ProductController.php').read_text(encoding='utf-8')\nok=('if (! $canViewCost)' in p and \"'cost_price' =>\" in p and \"'last_cost' =>\" in p and 'if ($canViewCost)' in r1 and 'if ($canViewCost)' in r2 and \"'last_cost'\" not in r1 and \"'last_cost'\" not in r2 and 'can_view_cost' in c and 'catalog.view_cost' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "CPRD8-tests-catalog", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CPRD9-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CPRD10-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CPRD11-regresion-core", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CPRD12-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CPRD13-regresion-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CPRD14-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "CPRD15-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "CPRD16-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "CPRD17-guia-roadmap-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nok=('CATPROD-001' in s and pending.upper() not in no_pending and 'GUIA_PENDIENTE_CHATGPT' in s and 'CATPROD-001' in next_line and 'CATPROD-001' in road)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD18-notas-maria-remota", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/CATPROD-001-NOTES-20261117.md').read_text(encoding='utf-8')\nok=('maria=NOT_RUN' in t and 'remote_db=UNTOUCHED' in t and 'GUIA_PENDIENTE_CHATGPT' in t and 'CATPROD-001' in t and '/catalog/products' in t and 'catalog.view_cost' in t and 'catalog.manage' in t and 'last_cost' in t and '7889f5e990594bcdb8676060c915b5c9fb0305d1' in t)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "CPRD19-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "INVCORE-001": [
        {"id": "IV1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/INVCORE-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'INVCORE-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV2-archivos-implementacion", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['database/migrations/2026_11_18_000022_create_inventory_core_tables.php','app/Models/StockMovement.php','app/Support/Inventory/InventoryDecimal.php','app/Support/Inventory/StockLedger.php','app/Support/Inventory/StockLedgerException.php','app/Support/Inventory/DuplicateStockMovementException.php','app/Support/Inventory/InventoryWriteGuard.php','app/Support/Inventory/InventoryModuleGate.php','app/Support/Inventory/StockQueryService.php','app/Console/Commands/ReconcileInventoryCommand.php','app/Http/Controllers/Inventory/StockController.php','resources/js/Pages/Inventory/Stock.vue','tests/Feature/Inventory/InventoryTestCase.php','tests/Feature/Inventory/InventorySchemaTest.php','tests/Feature/Inventory/StockLedgerTest.php','tests/Feature/Inventory/StockReadAuthorizationTest.php','tests/Feature/Inventory/StockReconciliationTest.php','docs/work/INVCORE-001-NOTES-20261118.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV3-rutas-lectura-alcance-servidor", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/StockController.php').read_text(encoding='utf-8')\nd=(R/'resources/js/Pages/Dashboard.vue').read_text(encoding='utf-8')\ndc=(R/'app/Http/Controllers/DashboardController.php').read_text(encoding='utf-8')\nstart=w.index('Inventario f')\nend=w.index('Administraci', start)\nblock=w[start:end]\nok=(block.count('Route::get(')==2 and 'inventory.entry' in block and 'inventory.stock.index' in block and \"Route::get('/inventory'\" in block and \"Route::get('/inventory/stock/{branch}'\" in block and 'can.permission:inventory.view' in block and 'branch.access' in block and all(v not in block for v in ['Route::post(', 'Route::put(', 'Route::patch(', 'Route::delete(']) and \"input('branch_id')\" not in c and \"query('branch_id')\" not in c and '/inventory' in d and 'can_view_inventory' in d and 'can_view_inventory' in dc and 'inventory.view' in dc)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV4-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "docs/work/features/INVCORE-001.md"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV5-esquema-aprobado-minimo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\nm=(R/'database/migrations/2026_11_18_000022_create_inventory_core_tables.php').read_text(encoding='utf-8')\npaths=set()\nd=subprocess.run(['git','diff','--name-only','d6940dcdd0b6a88ff02171d6f465c67854bc1214','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip().splitlines()\n[paths.add(x.strip()) for x in d if x.strip()]\nst=subprocess.run(['git','status','--porcelain','--untracked-files=all','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip().splitlines()\nfor line in st:\n    p=line[3:].strip().strip(chr(34))\n    if ' -> ' in p:\n        p=p.split(' -> ')[-1].strip()\n    paths.add(p)\nok=(paths=={'database/migrations/2026_11_18_000022_create_inventory_core_tables.php'})\nok=ok and \"Schema::create('current_stock'\" in m and \"Schema::create('stock_movements'\" in m\nok=ok and \"Schema::create('stock_reservations'\" not in m and \"Schema::create('branch_variant_settings'\" not in m\nok=ok and \"virtualAs('on_hand - reserved')\" in m and \"primary(['branch_id', 'variant_id'])\" in m\nok=ok and \"decimal('on_hand', 12, 3)\" in m and \"decimal('reserved', 12, 3)\" in m and \"decimal('quantity', 12, 3)\" in m and \"decimal('balance_after', 12, 3)\" in m and \"decimal('unit_cost', 14, 4)\" in m\nok=ok and \"dateTime('occurred_at')\" in m and \"dateTime('updated_at');\" in m and \"dateTime('updated_at')->nullable\" not in m and '$table->timestamps()' not in m\nok=ok and \"->index(['branch_id', 'variant_id', 'id'])\" in m and \"->index(['variant_id', 'occurred_at'])\" in m and \"->index(['source_type', 'source_id'])\" in m and \"->index(['type', 'occurred_at'])\" in m\nok=ok and 'reservation_id' in m and \"references('id')->on('stock_reservations')\" not in m\nok=ok and all(t in m for t in ['initial','purchase_receipt','adjustment','count_adjustment','waste','sale_pos','sale_online','customer_return','supplier_return','transfer_out','transfer_in','reversal'])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV6-kardex-inmutable-reconciliacion-readonly", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/StockLedger.php').read_text(encoding='utf-8')\nmo=(R/'app/Models/StockMovement.php').read_text(encoding='utf-8')\ncmd=(R/'app/Console/Commands/ReconcileInventoryCommand.php').read_text(encoding='utf-8')\nok=all(k in s for k in ['transaction(function','lockForUpdate','insertOrIgnore','assertWritable','AuditLogger','reverses_movement_id',\"'type' => 'reversal'\",'DuplicateStockMovementException'])\nok=ok and \"stock_movements')->update(\" not in s and \"stock_movements')->delete(\" not in s\nok=ok and all(k in mo for k in ['LogicException','function save','function update','function delete','function forceDelete'])\nok=ok and not any(p in cmd for p in ['->insert(','->update(','->delete(','->truncate(','->increment(','->decrement(','->upsert('])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV7-sin-acoplamiento-catalogo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\nr=subprocess.run(['git','diff','--quiet','HEAD','--','app/Support/Catalog','app/Http/Controllers/Catalog','app/Http/Requests/Catalog','app/Models/Product.php','app/Models/ProductVariant.php','app/Models/ProductBarcode.php','app/Models/TaxRate.php'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace')\nt=(R/'tests/Feature/Inventory/StockLedgerTest.php').read_text(encoding='utf-8')\nok=(r.returncode==0 and 'test_guardar_un_producto_no_crea_ni_cambia_stock' in t and '/catalog/products' in t)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV8-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "IV9-tests-inventario", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Inventory"],
         "mandatory": True, "timeout_s": 900},
        {"id": "IV10-regresion-catalogo", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog"],
         "mandatory": True, "timeout_s": 900},
        {"id": "IV11-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "IV12-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "IV13-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "IV14-guia-roadmap-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nok=('INVCORE-001' in s and pending.upper() not in no_pending and 'GUIA_PENDIENTE_CHATGPT' in s and 'INVCORE-001' in next_line and 'INVCORE-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in r)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV15-notas-maria-remota", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/INVCORE-001-NOTES-20261118.md').read_text(encoding='utf-8')\nok=all(k in t for k in ['maria=NOT_RUN','remote_db=UNTOUCHED','GUIA_PENDIENTE_CHATGPT','INVCORE-001','d6940dcdd0b6a88ff02171d6f465c67854bc1214','current_stock','stock_movements','inventory:reconcile','/inventory/stock/{branch}','inventory.view','deferred','SQLite','reserved_check=deferred'])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV16-sin-secretos-en-fuentes-nuevas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['app/Models/StockMovement.php','app/Support/Inventory/InventoryDecimal.php','app/Support/Inventory/StockLedger.php','app/Support/Inventory/StockLedgerException.php','app/Support/Inventory/DuplicateStockMovementException.php','app/Support/Inventory/InventoryWriteGuard.php','app/Support/Inventory/InventoryModuleGate.php','app/Support/Inventory/StockQueryService.php','app/Console/Commands/ReconcileInventoryCommand.php','app/Http/Controllers/Inventory/StockController.php','resources/js/Pages/Inventory/Stock.vue','database/migrations/2026_11_18_000022_create_inventory_core_tables.php']\nbad=('BEGIN RSA PRIVATE KEY','BEGIN PRIVATE KEY','APP_KEY=','DB_PASSWORD','STAGING_FTPS_PASSWORD','AKIA')\nok=all(not any(b in (R/f).read_text(encoding='utf-8') for b in bad) for f in files)\nok=ok and not any(\"env(\" in (R/f).read_text(encoding='utf-8') for f in files if f.endswith('.php'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IV17-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "INVWRITE-001": [
        {"id": "IW1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/INVWRITE-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'INVWRITE-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW2-archivos-implementacion", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/Inventory/StockAdjustmentController.php','app/Http/Requests/Inventory/StockAdjustmentRequest.php','app/Http/Requests/Inventory/StockInitialRequest.php','app/Support/Inventory/StockWriteException.php','app/Support/Inventory/StockWriteService.php','app/Support/Inventory/StockAdjustmentPinPolicy.php','app/Support/Inventory/DatabaseStockAdjustmentPinPolicy.php','resources/js/Pages/Inventory/Stock.vue','tests/Feature/Inventory/InventoryWriteTestCase.php','tests/Feature/Inventory/InitialStockWriteTest.php','tests/Feature/Inventory/StockAdjustmentWriteTest.php','tests/Feature/Inventory/StockWritePinAuthorizationTest.php','tests/Feature/Inventory/StockAdjustmentPinPolicyTest.php','tests/Feature/Inventory/InventoryDecimalPrecisionTest.php','docs/work/INVWRITE-001-NOTES-20261119.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW3-rutas-post-allowlist-servidor", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/StockAdjustmentController.php').read_text(encoding='utf-8')\ni=(R/'app/Http/Requests/Inventory/StockInitialRequest.php').read_text(encoding='utf-8')\na=(R/'app/Http/Requests/Inventory/StockAdjustmentRequest.php').read_text(encoding='utf-8')\ns=w.index('INVWRITE-001')\ne=w.index('Administraci', s)\nb=w[s:e]\nok=(b.count('Route::post(')==2 and 'inventory.stock.initial' in b and 'inventory.stock.adjust' in b and b.count(\"'can.permission:inventory.adjust'\")>=2 and b.count(\"'branch.access'\")>=2 and all(v not in b for v in ['Route::put(', 'Route::patch(', 'Route::delete(']))\nok=ok and all(k in i for k in [\"'variant_id'\", \"'quantity'\"]) and all(k not in i for k in [\"'branch_id'\", \"'user_id'\", \"'on_hand'\", \"'cost'\"])\nok=ok and all(k in a for k in [\"'variant_id'\", \"'quantity'\", \"'reason_code_id'\", \"'request_token'\", \"'authorizer_id'\", \"'pin'\"]) and all(k not in a for k in [\"'branch_id'\", \"'user_id'\", \"'on_hand'\", \"'cost'\", \"'type' =>\"] )\nok=ok and \"input('branch_id')\" not in c and \"input('user_id')\" not in c and 'InventoryModuleGate' in c and 'abortIfModuleDisabled' in c\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW4-protegidos-intactos-sin-migraciones", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\nr=subprocess.run(['git','diff','--quiet','HEAD','--','AGENTS.md','opencode.json','docs/governance/CONSTITUTION.md','docs/product','docs/branding','docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md','docs/harness/CONTEXTO-CHATGPT.md','public/docs/index.html','resources/roadmap/index.html','docs/work/features/INVWRITE-001.md','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace')\nst=subprocess.run(['git','status','--porcelain','--untracked-files=all','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip()\nsys.exit(0 if r.returncode==0 and st=='' else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW5-solo-ledger-sin-escritura-directa", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/StockWriteService.php').read_text(encoding='utf-8')\nok=s.count('->recordMovement(')>=2 and 'StockLedger' in s and 'InventoryWriteGuard' in s\nok=ok and all(p not in s for p in [\"current_stock')->update(\",\"current_stock')->insert(\",\"current_stock')->delete(\",\"stock_movements')->insert(\",\"stock_movements')->update(\",\"stock_movements')->delete(\"])\nok=ok and 'type' in s and \"'initial'\" in s and \"'adjustment'\" in s\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW6-pin-autorizacion-atomica-sin-fugas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/StockWriteService.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/StockAdjustmentController.php').read_text(encoding='utf-8')\nkc=(R/'app/Http/Controllers/Inventory/StockController.php').read_text(encoding='utf-8')\nps=(R/'app/Support/Authorization/PinAuthorizationService.php').read_text(encoding='utf-8')\npol=(R/'app/Support/Inventory/DatabaseStockAdjustmentPinPolicy.php').read_text(encoding='utf-8')\nok=all(k in s for k in ['PinAuthorizationService','stock_adjustment','requiresPinUnderLock','request_token','sourceIdFromToken','onApproved'])\nok=ok and all(k in pol for k in ['requiresPinUnderLock','cost_price','last_cost','stock_adjust_pin_value_threshold','lockForUpdate','compareProductToThreshold'])\nok=ok and '$onApproved()' in ps and '?callable $onApproved' in ps and 'authorizerId === $userId' not in s\nok=ok and 'adjustment_token' in kc and 'AuthorizationDeniedException' in c and 'ValidationException' in c\nok=ok and 'cost_price' not in c and 'pin_hash' not in s and 'pin_hash' not in c\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW7-guards-inicial-authoritative-precision", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/StockWriteService.php').read_text(encoding='utf-8')\nl=(R/'app/Support/Inventory/StockLedger.php').read_text(encoding='utf-8')\npol=(R/'app/Support/Inventory/DatabaseStockAdjustmentPinPolicy.php').read_text(encoding='utf-8')\na=(R/'app/Http/Requests/Inventory/StockAdjustmentRequest.php').read_text(encoding='utf-8')\nd=(R/'tests/Feature/Inventory/InventoryDecimalPrecisionTest.php').read_text(encoding='utf-8')\nok=all(k in s for k in ['hasAnyMovement','DuplicateStockMovementException','formatQuantity','reason_code_id','requiresPinUnderLock'])\nok=ok and 'private function requiresPin(' not in s and 'thresholdValue' not in s\nok=ok and 'allow_negative_stock' not in s and '?callable $underLock' in l and '$underLock()' in l\nok=ok and 'A-Za-z0-9_-' in a and 'request_token' in a\nok=ok and 'compareProductToThreshold' in pol and 'multiply' in d.lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW8-sin-regresion-catalogo-lectura", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\nr=subprocess.run(['git','diff','--quiet','HEAD','--','app/Support/Catalog','app/Http/Controllers/Catalog','app/Http/Requests/Catalog','app/Models/Product.php','app/Models/ProductVariant.php','app/Models/ProductBarcode.php'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace')\nt=(R/'tests/Feature/Inventory/StockReadAuthorizationTest.php').read_text(encoding='utf-8')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nok=(r.returncode==0 and all(n in w for n in ['inventory.entry','inventory.stock.index','inventory.stock.initial','inventory.stock.adjust']) and 'inventory.stock.initial' in t and 'inventory.stock.adjust' in t)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW9-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "IW10-tests-inventory", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Inventory"],
         "mandatory": True, "timeout_s": 900},
        {"id": "IW11-tests-pin", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Core/PinAuthorizationServiceTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "IW12-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "IW13-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "IW14-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "IW15-guia-roadmap-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nok=('INVWRITE-001' in s and pending.upper() not in no_pending and 'GUIA_PENDIENTE_CHATGPT' in s and 'INVWRITE-001' in next_line and 'INVWRITE-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in r and 'INVCORE-001 VERIFIED' in r)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW16-notas-maria-remota-csv", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/INVWRITE-001-NOTES-20261119.md').read_text(encoding='utf-8')\nok=all(k in t for k in ['maria=NOT_RUN','remote_db=UNTOUCHED','GUIA_PENDIENTE_CHATGPT','INVWRITE-001','f890b7d15844dce32cb0b8bc47a2fe14aba37d82','stock_adjust_pin_value_threshold','authorization_overrides','cost_price','last_cost','SQLite','CSV','deferred'])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW17-sin-secretos-en-fuentes-nuevas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['app/Http/Controllers/Inventory/StockAdjustmentController.php','app/Http/Requests/Inventory/StockAdjustmentRequest.php','app/Http/Requests/Inventory/StockInitialRequest.php','app/Support/Inventory/StockWriteException.php','app/Support/Inventory/StockWriteService.php','app/Support/Inventory/InventoryDecimal.php','resources/js/Pages/Inventory/Stock.vue']\nbad=('BEGIN RSA PRIVATE KEY','BEGIN PRIVATE KEY','APP_KEY=','DB_PASSWORD','STAGING_FTPS_PASSWORD','AKIA')\nok=all(not any(b in (R/f).read_text(encoding='utf-8') for b in bad) for f in files)\nok=ok and not any('env(' in (R/f).read_text(encoding='utf-8') for f in files if f.endswith('.php'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IW18-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "INVKARDEX-001": [
        {"id": "IK1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/INVKARDEX-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'INVKARDEX-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK2-archivos-implementacion", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Support/Inventory/KardexQueryService.php','app/Http/Requests/Inventory/KardexFilterRequest.php','app/Http/Controllers/Inventory/KardexController.php','resources/js/Pages/Inventory/Kardex.vue','tests/Feature/Inventory/KardexReportTest.php','docs/work/INVKARDEX-001-NOTES-20261119.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK3-ruta-get-middleware-enlace", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nu=(R/'resources/js/Pages/Inventory/Stock.vue').read_text(encoding='utf-8')\ns=w.index('INVKARDEX-001')\ne=w.index('INVWRITE-001', s)\nb=w[s:e]\nok=(\"Route::get('/inventory/stock/{branch}/kardex'\" in b and 'inventory.kardex.index' in b and \"'can.permission:inventory.view'\" in b and \"'branch.access'\" in b and all(v not in b for v in ['Route::post(', 'Route::put(', 'Route::patch(', 'Route::delete(']) and 'kardex' in u.lower())\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK4-protegidos-intactos-sin-esquema", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\nr=subprocess.run(['git','diff','--quiet','HEAD','--','AGENTS.md','opencode.json','docs/governance/CONSTITUTION.md','docs/product','docs/branding','docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md','docs/harness/CONTEXTO-CHATGPT.md','public/docs/index.html','resources/roadmap/index.html','docs/work/features/INVKARDEX-001.md','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace')\nst=subprocess.run(['git','status','--porcelain','--untracked-files=all','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip()\nsys.exit(0 if r.returncode==0 and st=='' else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK5-solo-lectura-scope-y-sin-sensibles", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/KardexQueryService.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/KardexController.php').read_text(encoding='utf-8')\nr=(R/'app/Http/Requests/Inventory/KardexFilterRequest.php').read_text(encoding='utf-8')\nok=('stock_movements as sm' in s and \"orderBy('sm.id', 'desc')\" in s and \"where('sm.branch_id', $branchId)\" in s)\nok=ok and all(k in s for k in ['movement_id','occurred_at','balance_after','reverses_movement_id','sku','product_name','cursorPaginate'])\nok=ok and all(k not in s+c for k in ['unit_cost','cost_price','pin_hash','authorized_by_user_id','user_id','price','margin'])\nok=ok and not any(p in s+c for p in ['->insert(','->update(','->delete(','->truncate(','->increment(','->decrement('])\nok=ok and \"'branch_id'\" not in r and \"'cost'\" not in r and \"query('branch_id')\" not in c and \"input('branch_id')\" not in c\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK6-filtros-y-validacion-servidor", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nr=(R/'app/Http/Requests/Inventory/KardexFilterRequest.php').read_text(encoding='utf-8')\ns=(R/'app/Support/Inventory/KardexQueryService.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/KardexController.php').read_text(encoding='utf-8')\nt=(R/'tests/Feature/Inventory/KardexReportTest.php').read_text(encoding='utf-8')\nok=all(k in r for k in [\"'q'\", \"'variant_id'\", \"'from'\", \"'to'\", \"'per_page'\", \"'cursor'\", \"'max:100'\", \"after_or_equal:from\"])\nok=ok and all(k in s for k in ['startOfDay','endOfDay','assertValidCursor','ValidationException'])\nok=ok and 'is_numeric' in c and '1E0' in t\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "IK8-tests-inventory", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Inventory"],
         "mandatory": True, "timeout_s": 900},
        {"id": "IK9-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "IK10-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "IK11-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "IK12-guia-roadmap-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nroadpend=next((l for l in r.splitlines() if l.startswith('pending:')),'')\nok=('INVKARDEX-001' in s and pending.upper() not in no_pending and 'GUIA_PENDIENTE_CHATGPT' in s and 'INVKARDEX-001' in next_line and 'INVKARDEX-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in roadpend and 'INVKARDEX-001' in roadpend)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK13-notas-maria-remota", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/INVKARDEX-001-NOTES-20261119.md').read_text(encoding='utf-8')\nok=all(k in t for k in ['maria=NOT_RUN','remote_db=UNTOUCHED','GUIA_PENDIENTE_CHATGPT','INVKARDEX-001','671e215f57999d00964d18cd6d2d77f5db37bbcc','INV-08','stock_movements','inventory.kardex.index','cursor','no schema','SQLite']) and 'deferred' in t.lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK14-sin-secretos-en-fuentes-nuevas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['app/Support/Inventory/KardexQueryService.php','app/Http/Requests/Inventory/KardexFilterRequest.php','app/Http/Controllers/Inventory/KardexController.php','resources/js/Pages/Inventory/Kardex.vue']\nbad=('BEGIN RSA PRIVATE KEY','BEGIN PRIVATE KEY','APP_KEY=','DB_PASSWORD','STAGING_FTPS_PASSWORD','AKIA')\nok=all(not any(b in (R/f).read_text(encoding='utf-8') for b in bad) for f in files)\nok=ok and not any('env(' in (R/f).read_text(encoding='utf-8') for f in files if f.endswith('.php'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IK15-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "INVREORDER-001": [
        {"id": "IR1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/INVREORDER-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'INVREORDER-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR2-archivos-implementacion", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['database/migrations/2026_11_19_000023_create_branch_variant_settings_table.php','app/Support/Inventory/ReorderSettingsService.php','app/Support/Inventory/ReorderSettingsException.php','app/Support/Inventory/ReorderQueryService.php','app/Http/Controllers/Inventory/ReorderController.php','app/Http/Requests/Inventory/ReorderFilterRequest.php','app/Http/Requests/Inventory/ReorderSettingsRequest.php','resources/js/Pages/Inventory/Reorder.vue','tests/Feature/Inventory/ReorderSettingsWriteTest.php','tests/Feature/Inventory/ReorderDashboardTest.php','docs/work/INVREORDER-001-NOTES-20261119.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR3-rutas-y-resolucion-sucursal", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/ReorderController.php').read_text(encoding='utf-8')\nu=(R/'resources/js/Pages/Inventory/Stock.vue').read_text(encoding='utf-8')\nok=(\"Route::get('/inventory/stock/{branch}/reorder'\" in w and \"Route::put('/inventory/stock/{branch}/reorder/{variant}'\" in w and 'inventory.reorder.index' in w and 'inventory.reorder.update' in w and w.count(\"'can.permission:inventory.view'\")>=1 and w.count(\"'can.permission:inventory.adjust'\")>=1 and 'branch.access' in w)\nok=ok and \"Route::post('/inventory/stock/{branch}/reorder\" not in w and \"Route::patch('/inventory/stock/{branch}/reorder\" not in w and \"Route::delete('/inventory/stock/{branch}/reorder\" not in w\nok=ok and 'is_numeric' in c and 'whereKey((int) $identifier)' in c and 'reorder' in u.lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR4-una-migracion-nueva-sin-cambios-previos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\npaths=set()\nd=subprocess.run(['git','diff','--name-only','7665317c53b1af4a6f4cff1a30804b5d7e2d61c1','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip().splitlines()\n[paths.add(x.strip()) for x in d if x.strip()]\nst=subprocess.run(['git','status','--porcelain','--untracked-files=all','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip().splitlines()\nfor line in st:\n    p=line[3:].strip().strip(chr(34))\n    if ' -> ' in p:\n        p=p.split(' -> ')[-1].strip()\n    paths.add(p)\nok=(paths=={'database/migrations/2026_11_19_000023_create_branch_variant_settings_table.php'})\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR5-esquema-aprobado", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nm=(R/'database/migrations/2026_11_19_000023_create_branch_variant_settings_table.php').read_text(encoding='utf-8')\nok=m.count('Schema::create(')==1 and \"Schema::create('branch_variant_settings'\" in m\nok=ok and \"unsignedBigInteger('branch_id')\" in m and \"unsignedBigInteger('variant_id')\" in m\nok=ok and \"decimal('reorder_point', 12, 3)->default(0)\" in m and \"decimal('reorder_qty', 12, 3)->nullable()\" in m and \"decimal('max_stock', 12, 3)->nullable()\" in m and \"decimal('safety_stock', 12, 3)->default(0)\" in m and \"string('location_label', 50)->nullable()\" in m\nok=ok and \"primary(['branch_id', 'variant_id'])\" in m and \"cascadeOnDelete()\" in m and m.count('cascadeOnDelete()')==2\nok=ok and '$table->id(' not in m and '$table->timestamps(' not in m and '$table->index(' not in m and '$table->unique(' not in m\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR6-escritura-config-sin-stock-ni-campos-extra", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/ReorderSettingsService.php').read_text(encoding='utf-8')\nr=(R/'app/Http/Requests/Inventory/ReorderSettingsRequest.php').read_text(encoding='utf-8')\nok=all(k in s for k in ['branch_variant_settings','InventoryWriteGuard','AuditLogger','lockForUpdate','transaction(function','InventoryDecimal::quantity','safety_stock','location_label'])\nok=ok and all(p not in s for p in [\"current_stock')->insert(\",\"current_stock')->update(\",\"stock_movements')->insert(\",\"stock_movements')->update(\",\"stock_movements')->delete(\"])\nok=ok and all(k in r for k in [\"'reorder_point'\", \"'reorder_qty'\", \"'max_stock'\"]) and all(k not in r for k in [\"'safety_stock'\", \"'location_label'\", \"'branch_id'\", \"'variant_id'\", \"'user_id'\"])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR7-regla-critica-y-sin-sensibles", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nq=(R/'app/Support/Inventory/ReorderQueryService.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/ReorderController.php').read_text(encoding='utf-8')\nv=(R/'resources/js/Pages/Inventory/Reorder.vue').read_text(encoding='utf-8')\nfr=(R/'app/Http/Requests/Inventory/ReorderFilterRequest.php').read_text(encoding='utf-8')\nok=all(k in q for k in ['COALESCE(cs.available, 0) <= bvs.reorder_point',\"where('bvs.reorder_point', '>', 0)\",'is_critical','criticalCount','paginate'])\nok=ok and 'bvs.safety_stock' not in q and 'bvs.location_label' not in q\nok=ok and all(k not in q+c+v for k in ['unit_cost','cost_price','pin_hash','authorized_by_user_id','email'])\nok=ok and \"'max:100'\" in fr and \"'critical'\" in fr and \"'per_page'\" in fr\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR8-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "IR9-tests-inventory", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Inventory"],
         "mandatory": True, "timeout_s": 900},
        {"id": "IR10-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "IR11-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "IR12-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "IR13-guia-roadmap-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nroadpend=next((l for l in r.splitlines() if l.startswith('pending:')),'')\ncovered=next((l for l in r.splitlines() if l.startswith('covered_features:')),'')\nok=('INVREORDER-001' in s and pending.upper() not in no_pending and 'GUIA_PENDIENTE_CHATGPT' in s and 'INVREORDER-001' in next_line and 'INVREORDER-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in roadpend and 'INVREORDER-001' in roadpend and 'INVKARDEX-001' in covered)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR14-notas-maria-remota", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/INVREORDER-001-NOTES-20261119.md').read_text(encoding='utf-8')\nok=all(k in t for k in ['maria=NOT_RUN','remote_db=UNTOUCHED','GUIA_PENDIENTE_CHATGPT','INVREORDER-001','7665317c53b1af4a6f4cff1a30804b5d7e2d61c1','INV-07','branch_variant_settings','reorder_point','available','branch_variant_settings','NOT_RUN']) and 'deferred' in t.lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR15-sin-secretos-en-fuentes-nuevas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['app/Support/Inventory/ReorderSettingsService.php','app/Support/Inventory/ReorderSettingsException.php','app/Support/Inventory/ReorderQueryService.php','app/Http/Controllers/Inventory/ReorderController.php','app/Http/Requests/Inventory/ReorderFilterRequest.php','app/Http/Requests/Inventory/ReorderSettingsRequest.php','resources/js/Pages/Inventory/Reorder.vue','database/migrations/2026_11_19_000023_create_branch_variant_settings_table.php']\nbad=('BEGIN RSA PRIVATE KEY','BEGIN PRIVATE KEY','APP_KEY=','DB_PASSWORD','STAGING_FTPS_PASSWORD','AKIA')\nok=all(not any(b in (R/f).read_text(encoding='utf-8') for b in bad) for f in files) and not any('env(' in (R/f).read_text(encoding='utf-8') for f in files if f.endswith('.php'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IR16-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "INVCSV-001": [
        {"id": "IC1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/INVCSV-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'INVCSV-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC2-archivos-implementacion", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Support/Inventory/InitialStockCsvParser.php','app/Support/Inventory/InitialStockImportService.php','app/Http/Controllers/Inventory/InitialStockImportController.php','app/Http/Requests/Inventory/InitialStockImportRequest.php','resources/js/Pages/Inventory/Import.vue','tests/Feature/Inventory/InitialStockCsvImportTest.php','docs/work/INVCSV-001-NOTES-20261120.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC3-rutas-metodos-middleware-enlace", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/InitialStockImportController.php').read_text(encoding='utf-8')\nu=(R/'resources/js/Pages/Inventory/Stock.vue').read_text(encoding='utf-8')\nok=(\"Route::get('/inventory/stock/{branch}/import'\" in w and \"Route::get('/inventory/stock/{branch}/import/template'\" in w and \"Route::post('/inventory/stock/{branch}/import/preview'\" in w and \"Route::post('/inventory/stock/{branch}/import/commit'\" in w and 'inventory.import.index' in w and 'inventory.import.template' in w and 'inventory.import.preview' in w and 'inventory.import.commit' in w and \"'can.permission:inventory.view'\" in w and \"'can.permission:inventory.adjust'\" in w and 'branch.access' in w)\nok=ok and \"Route::put('/inventory/stock/{branch}/import\" not in w and \"Route::patch('/inventory/stock/{branch}/import\" not in w and \"Route::delete('/inventory/stock/{branch}/import\" not in w\nok=ok and 'is_numeric' in c and 'whereKey((int) $identifier)' in c and 'import' in u.lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC4-protegidos-intactos-sin-esquema", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,subprocess,sys\nR=P.Path('.')\nr=subprocess.run(['git','diff','--quiet','HEAD','--','AGENTS.md','opencode.json','docs/governance/CONSTITUTION.md','docs/product','docs/branding','docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md','docs/harness/CONTEXTO-CHATGPT.md','public/docs/index.html','resources/roadmap/index.html','docs/work/features/INVCSV-001.md','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace')\nst=subprocess.run(['git','status','--porcelain','--untracked-files=all','--','database/migrations'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout.strip()\nsys.exit(0 if r.returncode==0 and st=='' else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC5-sin-escritura-directa-ledger", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Support/Inventory/InitialStockImportService.php').read_text(encoding='utf-8')\nok=all(k in s for k in ['loadInitial','transaction(function','assertWritable','usort($items','variant_id'])\nok=ok and 'insertOrIgnore' not in s and all(p not in s for p in [\"current_stock')->insert(\",\"current_stock')->update(\",\"current_stock')->delete(\",\"stock_movements')->insert(\",\"stock_movements')->update(\",\"stock_movements')->delete(\"])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC6-parser-limites-y-plantilla-segura", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'app/Support/Inventory/InitialStockCsvParser.php').read_text(encoding='utf-8')\nr=(R/'app/Http/Requests/Inventory/InitialStockImportRequest.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/Inventory/InitialStockImportController.php').read_text(encoding='utf-8')\nok=all(k in p for k in ['MAX_FILE_BYTES = 524_288','MAX_DATA_ROWS = 500','MAX_SKU_LENGTH = 100','MAX_QUANTITY_LENGTH = 20','fgetcsv','mb_check_encoding','validateSku','validateQuantity','safeSku'])\nok=ok and \"mimes:csv,txt\" in r and 'extensions:csv,txt' in r and 'max:512' in r\nok=ok and 'streamDownload' in c and 'sku;cantidad' in c and 'text/csv' in c\nok=ok and 'Storage::' not in c+p and '->store(' not in c+p and '->move(' not in c+p\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC7-auth-atomicidad-previa-reparseo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nc=(R/'app/Http/Controllers/Inventory/InitialStockImportController.php').read_text(encoding='utf-8')\ns=(R/'app/Support/Inventory/InitialStockImportService.php').read_text(encoding='utf-8')\nt=(R/'tests/Feature/Inventory/InitialStockCsvImportTest.php').read_text(encoding='utf-8')\nok=all(k in c for k in ['InventoryModuleGate','abortIfReadOnly','is_numeric','whereKey((int)'])\nok=ok and 'transaction(function' in s and 'loadInitial' in s and 'usort($items' in s\nok=ok and 'rollback_atomico' in t and 'segundo_commit' in t and 'reparsea' in t and 'formula' in t\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC8-hqaeff-guard-y-sqlite-aislado", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nv=(R/'scripts/sf_verify.py').read_text(encoding='utf-8')\nphpu=(R/'phpunit.xml').read_text(encoding='utf-8')\nt=(R/'docs/work/INVCSV-001-NOTES-20261120.md').read_text(encoding='utf-8')\nok=(R/'scripts/sf_evidence.py').exists() and '--reuse-evidence' in v and 'name=\"DB_CONNECTION\" value=\"sqlite\"' in phpu and 'name=\"DB_DATABASE\" value=\":memory:\"' in phpu and 'HQAEFF' in t and 'NOT_RUN' in t\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC9-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 120},
        {"id": "IC10-tests-inventory", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Inventory"],
         "mandatory": True, "timeout_s": 900},
        {"id": "IC11-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "IC12-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "IC13-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "IC14-guia-roadmap-metadatos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nroadpend=next((l for l in r.splitlines() if l.startswith('pending:')),'')\ncovered=next((l for l in r.splitlines() if l.startswith('covered_features:')),'')\nok=('INVCSV-001' in s and pending.upper() not in no_pending and 'GUIA_PENDIENTE_CHATGPT' in s and 'INVCSV-001' in next_line and 'INVCSV-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in roadpend and 'INVCSV-001' in roadpend and 'INVREORDER-001 VERIFIED_TECH' in covered)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC15-notas-maria-remota", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'docs/work/INVCSV-001-NOTES-20261120.md').read_text(encoding='utf-8')\nok=all(k in t for k in ['maria=NOT_RUN','remote_db=KNOWN_HISTORICAL_EXCEPTION_NOT_UNTOUCHED','GUIA_PENDIENTE_CHATGPT','INVCSV-001','7c8b2f430c27966a8173453cb6045d2aa73d9ad5','INV-05','stock_movements','current_stock','initial','SQLite','HQAEFF','NOT_RUN']) and 'deferred' in t.lower()\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC16-sin-secretos-en-fuentes-nuevas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['app/Support/Inventory/InitialStockCsvParser.php','app/Support/Inventory/InitialStockImportService.php','app/Http/Controllers/Inventory/InitialStockImportController.php','app/Http/Requests/Inventory/InitialStockImportRequest.php','resources/js/Pages/Inventory/Import.vue']\nbad=('BEGIN RSA PRIVATE KEY','BEGIN PRIVATE KEY','APP_KEY=','DB_PASSWORD','STAGING_FTPS_PASSWORD','AKIA','tusitio','DB_HOST')\nok=all(not any(b in (R/f).read_text(encoding='utf-8') for b in bad) for f in files) and not any('env(' in (R/f).read_text(encoding='utf-8') for f in files if f.endswith('.php'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "IC17-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "SETUP-001": [
        {"id": "S1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/SETUP-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=(('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'SETUP-001' in c)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S2-archivos-backend", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['app/Http/Controllers/SetupController.php','app/Http/Requests/SetupRequest.php','app/Http/Middleware/EnsureSetupCompleted.php','app/Support/Setup/SetupService.php','app/Support/Setup/SetupEnvironmentPolicy.php','config/setup.php']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S3-ui-accesible", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'resources/js/Pages/Setup/Wizard.vue').read_text(encoding='utf-8')\nu=R/'resources/js/Pages/Setup/Unavailable.vue'\nok=(u.exists() and 'aria-' in w and 'label' in w and 'fieldset' in w and 'legend' in w and '@media' in w and 'setup_token' in w)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S4-guard-registrado", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nb=(R/'bootstrap/app.php').read_text(encoding='utf-8')\nm=(R/'app/Http/Middleware/EnsureSetupCompleted.php').read_text(encoding='utf-8')\nneed=['setup.show','login','password.','docs.index','home','is_setup_completed','423']\nok=('EnsureSetupCompleted' in b and all(x in m for x in need))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S5-seguridad-estatica", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nr=(R/'app/Http/Requests/SetupRequest.php').read_text(encoding='utf-8')\nc=(R/'app/Http/Controllers/SetupController.php').read_text(encoding='utf-8')\npol=(R/'app/Support/Setup/SetupEnvironmentPolicy.php').read_text(encoding='utf-8')\nmid=(R/'app/Http/Middleware/EnsureSetupCompleted.php').read_text(encoding='utf-8')\nsvc=(R/'app/Support/Setup/SetupService.php').read_text(encoding='utf-8')\nkeys=['business_name','fiscal_id','fiscal_name','commercial_activity','fiscal_address','fiscal_commune','responsible_name','responsible_email','responsible_phone','country','currency_code','currency_decimals','timezone','locale','prices_include_tax','cash_rounding_step']\ndbt='D'+'B::transaction'\nok=(all(k in r for k in keys) and all(x in r for x in ['admin_password','setup_token','array_diff']) and all(x in c for x in [dbt,'lockForUpdate','is_setup_completed','AuditLogger','Hash::make','central_admin']) and 'NOT_READY_FOR_PRODUCTION' in pol)\nneedle='mig'+'rate'\nok=ok and all(needle not in t.lower() for t in [c,mid,svc])\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S6-protegidos-intactos", "kind": "shell",
         "cmd": ["git", "diff", "--quiet", "HEAD", "--", "AGENTS.md", "opencode.json", "docs/governance/CONSTITUTION.md", "docs/product", "docs/branding", "docs/harness/CONTEXTO-CHATGPT.md", "public/docs/index.html", "resources/roadmap/index.html", "database/migrations", "database/seeders"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S7-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S8-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 180},
        {"id": "S9-tests-setup", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Setup"],
         "mandatory": True, "timeout_s": 600},
        {"id": "S10-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "S11-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "S12-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "S13-regresion-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "S14-regresion-catalog", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog"],
         "mandatory": True, "timeout_s": 900},
        {"id": "S15-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "S16-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "S17-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "S18-metadatos-guia-roadmap", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\nok=('SETUP-001' in s and 'GUIA_PENDIENTE_CHATGPT' in s and pending.upper() not in no_pending and 'SETUP-001' in next_line and 'SETUP-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in r)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "S19-diff-check", "kind": "shell",
          "cmd": ["git", "diff", "--check", "HEAD"],
          "mandatory": True, "timeout_s": 60},
    ],
    "INSTALL-001": [
        {"id": "I1-packet-approved-activo", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\np=(R/'docs/work/features/INSTALL-001.md').read_text(encoding='utf-8')\nc=(R/'docs/work/CURRENT.md').read_text(encoding='utf-8')\nok=('status: APPROVED' in p or 'status: VERIFIED' in p) and 'publication_mode: AUTO_GIT' in p and 'execution_mode: ONE_SMALL_WAVE' in p and 'new_business_decisions: NONE' in p and 'INSTALL-001' in c\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I2-archivos-backend-ui-tests", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nreq=['config/install.php','app/Http/Controllers/InstallController.php','app/Http/Middleware/EnsureInstallerEnabled.php','app/Http/Middleware/UseInstallStores.php','app/Http/Requests/Install/InstallBootstrapRequest.php','app/Http/Requests/Install/InstallConnectionRequest.php','app/Http/Requests/Install/InstallRunRequest.php','app/Support/Install/InstallService.php','app/Support/Install/InstallDatabaseConfig.php','app/Support/Install/InstallSecretGenerator.php','app/Support/Install/DefaultInstallPreflightChecker.php','app/Support/Install/PdoInstallConnectionTester.php','app/Support/Install/ArtisanInstallMigrator.php','app/Support/Install/AtomicEnvSecretWriter.php','app/Support/Install/RuntimeDatabaseConfigurator.php','app/Support/Install/Contracts/InstallPreflightChecker.php','app/Support/Install/Contracts/InstallConnectionTester.php','app/Support/Install/Contracts/InstallMigrator.php','app/Support/Install/Contracts/InstallSecretWriter.php','app/Support/Install/Contracts/InstallDatabaseConfigurator.php','app/Providers/AppServiceProvider.php','resources/js/Pages/Install/Wizard.vue','resources/js/Pages/Install/Unavailable.vue','tests/Feature/Install/InstallTestCase.php','tests/Feature/Install/InstallBootstrapSecurityTest.php','tests/Feature/Install/InstallStateTest.php','tests/Feature/Install/InstallPreflightTest.php','tests/Feature/Install/InstallConnectionTest.php','tests/Feature/Install/InstallRunTest.php','tests/Feature/Install/InstallSecretsTest.php','tests/Feature/Install/InstallGuardRegressionTest.php','tests/Feature/Install/InstallNoDestructiveTest.php','tests/Feature/Install/InstallWiringTest.php','tests/Feature/Install/InstallDefaultStoresTest.php','tests/Feature/Install/InstallDestinationStateTest.php','tests/Feature/Install/InstallSetupNoticeTest.php','tests/Feature/Install/InstallStorageFailureTest.php','docs/work/INSTALL-001-NOTES-20261117.md']\nsys.exit(0 if all((R/x).exists() for x in req) else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I3-seguridad-estatica", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,glob\nR=P.Path('.')\nfiles=[R/'app/Http/Controllers/InstallController.php',R/'app/Http/Middleware/EnsureInstallerEnabled.php',R/'app/Http/Middleware/UseInstallStores.php',R/'app/Providers/AppServiceProvider.php']+[P.Path(x) for x in glob.glob('app/Http/Requests/Install/*.php')+glob.glob('app/Support/Install/*.php')+glob.glob('app/Support/Install/Contracts/*.php')]\nt='\\n'.join(f.read_text(encoding='utf-8') for f in files)\nforbid=['mig'+'rate:fresh','db'+':wipe','db'+':reset','shell_exec','proc_open','passthru','system(','exec(']\nok=all(x not in t for x in forbid) and all(x in t for x in ['random_bytes','hash_equals','Cache::store','->lock(','Str::uuid','LOCK_KEY','Artisan::call'])\nb=(R/'bootstrap/app.php').read_text(encoding='utf-8')\nok=ok and \"'bootstrap_token'\" in b and \"'db_password'\" in b and 'UseInstallStores' in b\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I4-rutas-guard-exenciones-exactas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nw=(R/'routes/web.php').read_text(encoding='utf-8')\nm=(R/'app/Http/Middleware/EnsureSetupCompleted.php').read_text(encoding='utf-8')\nb=(R/'bootstrap/app.php').read_text(encoding='utf-8')\nnames=['install.show','install.bootstrap','install.preflight','install.connection','install.run']\nok=all((\"->name('\"+n+\"')\") in w for n in names)\nok=ok and all((\"'\"+n+\"'\") in m for n in names) and \"'install.'\" not in m and 'str_starts_with' not in m\nok=ok and \"Route::post('/install/run'\" in w and \"Route::get('/install/run'\" not in w\nok=ok and \"Route::get('/install/preflight'\" not in w and \"Route::get('/install/connection'\" not in w\nok=ok and 'install.complete' not in w and 'install.complete' not in m\nok=ok and 'EnsureInstallerEnabled' in w and 'UseInstallStores' in b and \"prependToGroup('web'\" in b\nok=ok and \"'bootstrap_token'\" in (R/'config/install.php').read_text(encoding='utf-8')\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I5-protegidos-intactos", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\npaths=['AGENTS.md','opencode.json','docs/governance/CONSTITUTION.md','docs/product','docs/branding','docs/harness/CONTEXTO-CHATGPT.md','public/docs/index.html','resources/roadmap/index.html','database/'+'migrations','database/seeders']\nr=subprocess.run(['git','diff','--quiet','HEAD','--']+paths,cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace')\nsys.exit(r.returncode)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I6-database-sin-cambios", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nout=subprocess.run(['git','status','--porcelain','--','database'],cwd='.',capture_output=True,text=True,encoding='utf-8',errors='replace').stdout\nsys.exit(0 if not out.strip() else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I7-contratos", "kind": "shell",
         "cmd": ["py", "-3", "scripts/check_contracts.py"],
         "mandatory": True, "timeout_s": 180},
        {"id": "I8-tests-install", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Install"],
         "mandatory": True, "timeout_s": 900},
        {"id": "I9-regresion-setup", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Setup"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I10-regresion-auth", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Auth"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I11-regresion-branches", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Branches"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I12-regresion-users", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Users"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I13-regresion-settings", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Settings"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I14-regresion-catalog", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Catalog"],
         "mandatory": True, "timeout_s": 900},
        {"id": "I15-regresion-roadmap", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Roadmap"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I16-regresion-docs", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Docs"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I17-suite-completa", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 1200},
        {"id": "I18-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 300},
        {"id": "I19-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I20-metadatos-guia-roadmap", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,re,sys\nR=P.Path('.')\ns=(R/'docs/work/GUIA-HTML-ESTADO.md').read_text(encoding='utf-8')\nr=(R/'docs/work/ROADMAP-HTML-ESTADO.md').read_text(encoding='utf-8')\nm=re.search(r'^pending_docs:\\s*(.+)$',s,re.M)\npending=m.group(1).strip() if m else ''\nno_pending={'','NINGUNO','NINGUNA','NO APLICA','NO_APLICA'}\nnext_line=next((l for l in s.splitlines() if l.startswith('next_feature:')),'')\nroad=next((l for l in r.splitlines() if l.startswith('in_progress:')),'')\npending_road=next((l for l in r.splitlines() if l.startswith('pending:')),'')\nok=('INSTALL-001' in s and 'GUIA_PENDIENTE_CHATGPT' in s and pending.upper() not in no_pending and 'INSTALL-001' in next_line and 'INSTALL-001' in road and 'ROADMAP_PENDIENTE_CHATGPT' in r and 'ROADMAP_PENDIENTE_CHATGPT' in pending_road)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I22-bindings-di-reales", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\ns=(R/'app/Providers/AppServiceProvider.php').read_text(encoding='utf-8')\ninterfaces=['InstallPreflightChecker','InstallConnectionTester','InstallMigrator','InstallSecretWriter','InstallDatabaseConfigurator']\nimpls=['DefaultInstallPreflightChecker','PdoInstallConnectionTester','ArtisanInstallMigrator','AtomicEnvSecretWriter','RuntimeDatabaseConfigurator']\nok=all(i in s for i in interfaces) and all(i in s for i in impls) and s.count('singleton(')>=5\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I23-test-wiring-runtime", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Install/InstallWiringTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I24-tests-drivers-database-sin-esquema", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'tests/Feature/Install/InstallDefaultStoresTest.php').read_text(encoding='utf-8')\nok=('session.driver' in t and 'cache.default' in t and \"'database'\" in t and 'sessions' in t and 'cache' in t)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I25-test-drivers-database-runtime", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Install/InstallDefaultStoresTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I26-test-estado-conexion-destino", "kind": "shell",
         "cmd": ["php", "artisan", "test", "tests/Feature/Install/InstallDestinationStateTest.php"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I27-orden-estado-destino-estatico", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nsvc=(R/'app/Support/Install/InstallService.php').read_text(encoding='utf-8')\nctrl=(R/'app/Http/Controllers/InstallController.php').read_text(encoding='utf-8')\ni=svc.index('public function perform')\nbody=svc[i:]\ncfg=body.index('->configure($database)')\nst=body.index('$state = $this->state()')\nw=body.index('writer->persist')\nmg=body.index('migrator->'+'mig'+'rate')\nri=svc.index('private function recordInstallationInfo')\nribody=svc[ri:]\nforbid=['mig'+'rate:fresh','db'+':wipe','db'+':reset']\nok=cfg<st<w<mg and 'assertVirgin' not in ctrl and 'users' in ribody and 'branches' in ribody and all(x not in svc for x in forbid)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I28-csrf-conductual-y-notas", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'tests/Feature/Install/InstallBootstrapSecurityTest.php').read_text(encoding='utf-8')\nn=(R/'docs/work/INSTALL-001-NOTES-20261117.md').read_text(encoding='utf-8')\nok=('csrf' in t.lower() and '419' in t and 'CSRF' in n and '419' in n)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 60},
        {"id": "I29-encoding-ui-sin-mojibake", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,glob\nR=P.Path('.')\nfiles=[]\nfor pat in ['resources/js/Pages/Install/*.vue','app/Support/Install/*.php','app/Support/Install/Contracts/*.php','app/Http/Controllers/InstallController.php','app/Http/Middleware/UseInstallStores.php','tests/Feature/Install/*.php','tests/Feature/Install/Support/*.php']:\n    files.extend(P.Path(x) for x in glob.glob(pat))\nbad=['Ã','Â','â€','Ã³','Ã©','Ã±']\nhits=[]\nfor f in files:\n    try:\n        t=f.read_text(encoding='utf-8')\n    except Exception:\n        hits.append(str(f)+':utf8')\n        continue\n    for b in bad:\n        if b in t:\n            hits.append(str(f)+':'+b)\nmanifest=R/'public/build/manifest.json'\nif manifest.exists():\n    for f in (R/'public/build/assets').glob('*.js'):\n        t=f.read_text(encoding='utf-8',errors='replace')\n        for b in bad:\n            if b in t:\n                hits.append(str(f)+':'+b)\nsys.exit(0 if not hits else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "I30-storage-no-escribible-controlado", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,subprocess\nR=P.Path('.')\ns=(R/'app/Http/Middleware/UseInstallStores.php').read_text(encoding='utf-8')\nok=('runtime_storage_unavailable' in s and 'Throwable' in s and \"'array'\" in s)\nif not ok:\n    sys.exit(1)\nr=subprocess.run(['php','artisan','test','tests/Feature/Install/InstallStorageFailureTest.php'],cwd='.')\nsys.exit(r.returncode)"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I31-cron-notice-deterministico", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,subprocess\nR=P.Path('.')\ns=(R/'app/Http/Controllers/SetupController.php').read_text(encoding='utf-8')\ni=(R/'app/Http/Controllers/InstallController.php').read_text(encoding='utf-8')\nw=(R/'resources/js/Pages/Setup/Wizard.vue').read_text(encoding='utf-8')\nok=('cron_command' in s and 'installation_info' in s and 'isCompleted' in s and \"with('install_notice'\" not in i and 'cron_command' in w)\nif not ok:\n    sys.exit(1)\nr=subprocess.run(['php','artisan','test','tests/Feature/Install/InstallSetupNoticeTest.php'],cwd='.')\nsys.exit(r.returncode)"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I32-db-url-socket-neutralizados", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys,subprocess\nR=P.Path('.')\na=(R/'app/Support/Install/AtomicEnvSecretWriter.php').read_text(encoding='utf-8')\nt=(R/'tests/Feature/Install/InstallSecretsTest.php').read_text(encoding='utf-8')\nok=(\"'DB_URL'\" in a and \"'DB_SOCKET'\" in a and \"'preserved' => $merged['preserved']\" in a and \"'written' => $merged['written']\" in a and 'DB_URL' in t and 'DB_SOCKET' in t)\nif not ok:\n    sys.exit(1)\nr=subprocess.run(['php','artisan','test','tests/Feature/Install/InstallSecretsTest.php'],cwd='.')\nsys.exit(r.returncode)"],
         "mandatory": True, "timeout_s": 600},
        {"id": "I21-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 60},
    ],
    "HTIME-001": [
        {"id": "T1-telemetry-tests", "kind": "shell",
         "cmd": ["py", "-3", "-m", "unittest", "discover", "-s", "tests/harness", "-p", "test_*.py", "-v"],
         "mandatory": True, "timeout_s": 180},
        {"id": "T2-timeline-privacy-static", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nt=(R/'scripts/sf_timeline.py').read_text(encoding='utf-8')\nv=(R/'scripts/sf_verify.py').read_text(encoding='utf-8')\nu=(R/'tests/harness/test_sf_timeline.py').read_text(encoding='utf-8')\nnet=['requests','urllib.request','socket','http://','https://']\nprivacy=['.env','prompt','transcript']\nok=('docs/work/.run/' in t and '^[A-Z0-9][A-Z0-9-]{2,63}$' in t and not any(x in t for x in net) and 'subprocess' not in t and not any(x in t for x in privacy) and 'sf_timeline' in v and 'record_gate' in v and '\"HTIME-001\"' in v and ('immut'+'able=1') not in t+u+v)\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "T3-diff-check", "kind": "shell",
         "cmd": ["git", "diff", "--check", "HEAD"],
         "mandatory": True, "timeout_s": 120},
        {"id": "T4-protected-intact", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nr=subprocess.run(['git','diff','--quiet','HEAD','--','opencode.json','AGENTS.md','docs/governance','docs/product','docs/branding','public/docs/index.html','resources/roadmap/index.html'])\nsys.exit(0 if r.returncode==0 else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "T5-app-runtime-untouched", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import subprocess,sys\nr=subprocess.run(['git','status','--porcelain','--','app','routes','resources/js','public','database','config'],capture_output=True,text=True)\nsys.exit(0 if r.returncode==0 and not r.stdout.strip() else 1)"],
         "mandatory": True, "timeout_s": 120},
        {"id": "T6-composer-validate", "kind": "shell",
         "cmd": ["composer", "validate", "--no-check-publish", "--no-check-all", "--strict"],
         "mandatory": True, "timeout_s": 120},
        {"id": "T7-php-tests", "kind": "shell",
         "cmd": ["php", "artisan", "test"],
         "mandatory": True, "timeout_s": 900},
        {"id": "T8-node-typecheck", "kind": "shell",
         "cmd": ["npm", "run", "typecheck"],
         "mandatory": True, "timeout_s": 600},
        {"id": "T9-node-build", "kind": "shell",
         "cmd": ["npm", "run", "build"],
         "mandatory": True, "timeout_s": 600},
        {"id": "T10-http-root", "kind": "http_serve",
         "mandatory": True, "timeout_s": 90},
        {"id": "T11-harness-docs-wired", "kind": "shell",
         "cmd": ["py", "-3", "-c", "import pathlib as P,sys\nR=P.Path('.')\nfiles=['.opencode/agents/stockflash-orchestrator.md','.opencode/agents/stockflash-implementer.md','.opencode/commands/sf-run.md','docs/harness/WORKFLOW-EFFICIENCY.md']\nok=all('sf_timeline.py' in (R/f).read_text(encoding='utf-8') for f in files) and ('Harness Python telemetry tests' in (R/'.github/workflows/ci.yml').read_text(encoding='utf-8'))\nsys.exit(0 if ok else 1)"],
         "mandatory": True, "timeout_s": 120},
    ],
}



def _changed_ui_files() -> list[str]:
    """Rutas de UI modificadas en el arbol de trabajo (solo lectura)."""
    prefixes = (
        "resources/js/",
        "resources/views/",
        "routes/web.php",
        "app/Http/Controllers/",
    )
    files: list[str] = []
    try:
        status_out = subprocess.run(
            ["git", "status", "--porcelain=v1", "--untracked-files=all"],
            cwd=ROOT,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=30,
        ).stdout
        for line in status_out.splitlines():
            if len(line) < 4:
                continue
            path = line[3:].strip()
            if " -> " in path:
                path = path.split(" -> ")[-1]
            if path.startswith(prefixes):
                files.append(path)
    except Exception:
        pass
    return sorted(set(files))


def _doc_handoff(
    state_path: Path,
    status_field: str,
    pending_field: str,
    integrated_marker: str,
    updated_status: str,
    pending_status: str,
    sha_fields: tuple[str, ...],
    label: str,
    instruction: str,
) -> dict:
    """Estado documental comun para GUIA y ROADMAP (no redacta contenido)."""
    result = {
        "status": pending_status,
        "covered": "",
        "sha": "",
        "pending": "",
        "ui_files": _changed_ui_files(),
        "instruction": instruction,
        "label": label,
    }
    if not state_path.exists():
        result["pending"] = f"Falta {state_path.name}"
        return result

    text = state_path.read_text(encoding="utf-8")

    def field(name: str) -> str:
        match = re.search(rf"^{re.escape(name)}:\s*(.+)$", text, re.M)
        return match.group(1).strip() if match else ""

    result["covered"] = field("covered_features")
    result["sha"] = next((field(name) for name in sha_fields if field(name)), "")
    result["pending"] = field(pending_field)

    pending_norm = result["pending"].upper()
    no_pending = pending_norm in ("", "NINGUNO", "NINGUNA", "NO APLICA", "NO_APLICA")
    if integrated_marker in field(status_field).upper() and no_pending:
        result["status"] = updated_status
        result["instruction"] = "Documento integrado; no hay pendientes de redaccion declarados."
    else:
        result["status"] = pending_status
        if not result["pending"]:
            result["pending"] = "Estado documental no integrado o sin metadatos suficientes."

    return result


def guide_handoff(packet: str) -> dict:
    result = _doc_handoff(
        ROOT / "docs" / "work" / "GUIA-HTML-ESTADO.md",
        "guide_status",
        "pending_docs",
        "INTEGRADA_EN_MAIN",
        "GUIA_ACTUALIZADA",
        "GUIA_PENDIENTE_CHATGPT",
        ("latest_covered_code_sha", "last_guide_integration_sha"),
        "GUIA",
        "Comparte este cierre con ChatGPT para actualizar /docs/.",
    )
    result["packet"] = packet
    return result


def roadmap_handoff(packet: str) -> dict:
    result = _doc_handoff(
        ROOT / "docs" / "work" / "ROADMAP-HTML-ESTADO.md",
        "roadmap_content_status",
        "pending",
        "INTEGRADA_EN_MAIN",
        "ROADMAP_ACTUALIZADA",
        "ROADMAP_PENDIENTE_CHATGPT",
        ("baseline_code_sha", "roadmap_integration_sha"),
        "ROADMAP",
        "Comparte este cierre con ChatGPT para actualizar el roadmap.",
    )
    result["packet"] = packet
    return result


def write_report(packet: str, results: list[dict], verdict: str, guide: dict, roadmap: dict) -> Path:
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
    lines += ["## Estado documental (GUIA y ROADMAP)"]
    for doc in (guide, roadmap):
        lines += [
            f"### {doc.get('label', 'DOC')}",
            f"- Estado: **{doc.get('status')}**",
            f"- Cobertura declarada: {doc.get('covered') or '(no declarada)'}",
            f"- SHA de referencia: {doc.get('sha') or '(no declarado)'}",
            f"- Pendientes de redacción ChatGPT: {doc.get('pending') or 'NINGUNO'}",
            f"- Archivos UI modificados: {', '.join(doc.get('ui_files', [])) or '(ninguno detectado)'}",
            f"- Handoff: {doc.get('instruction')}",
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
    # Telemetria local best-effort: nunca altera gates, exit codes ni reporte.
    begin_span = end_span = record_gate = None
    try:
        sys.path.insert(0, str(ROOT / "scripts"))
        from sf_timeline import begin_span as _begin_span
        from sf_timeline import end_span as _end_span
        from sf_timeline import record_gate as _record_gate
        begin_span, end_span, record_gate = _begin_span, _end_span, _record_gate
    except Exception:
        begin_span = end_span = record_gate = None

    ap = argparse.ArgumentParser(description="Verificador determinista de gates StockFlash.")
    ap.add_argument("packet", help="ID de Feature Packet (p. ej. BOOT-001)")
    ap.add_argument("--port", type=int, default=18733)
    ap.add_argument("--no-build", action="store_true", help="Omitir G3 (solo depuracion; el gate queda como SKIP=FAIL si es obligatorio)")
    ap.add_argument("--reuse-evidence", action="store_true", help="Reutilizar PASS solo si la evidencia local y el fingerprint exacto siguen validos")
    args = ap.parse_args()

    packet = args.packet.strip().upper()
    if packet not in GATES_BY_PACKET:
        print(f"FAIL: packet sin gates definidos: {packet}. Definir en GATES_BY_PACKET antes de verificar.")
        return 1
    if packet != "BOOT-001":
        print(f"Nota: verificando {packet} con definicion declarativa correspondiente.")

    # A deliberate opt-in: never silently bypass a full verification. Fail closed.
    evidence = None
    evidence_before = None
    try:
        import sf_evidence as evidence
        if args.reuse_evidence and not args.no_build:
            reusable, why, original = evidence.check(packet, ROOT)
            if reusable:
                print(f"GATES_PASS (REUSED_PREVIOUS_EXECUTION; original={original}; reason={why})")
                print("No gates executed now. Exact-tree proof is local, <=2h; QA and CI remain mandatory.")
                return 0
            print(f"FULL_GATES_REQUIRED: {why}")
        # Always invalidate before computing the new fingerprint: a failed
        # fingerprint must never leave a prior PASS proof eligible for reuse.
        evidence.invalidate(packet, ROOT)
        evidence_before = evidence.fingerprint(packet, ROOT)
    except Exception:
        evidence_before = None  # Evidence is optional; gates still execute in full.
        print("EVIDENCE_UNAVAILABLE: executing all mandatory gates (fail closed).")

    def record_gate_result(gate_id: str, result: dict) -> None:
        if record_gate is None:
            return
        try:
            record_gate(
                packet,
                gate_id,
                outcome="PASS" if result["ok"] else "FAIL",
                duration_ms=int(round(float(result["elapsed_s"]) * 1000)),
            )
        except Exception:
            pass

    results: list[dict] = []
    verdict = "GATES_FAIL"
    telemetry_started = False
    telemetry_closed = False
    telemetry_span = None
    try:
        if begin_span is not None:
            try:
                started = begin_span(packet, "verification", "verifier", source="native_gate_runner")
                telemetry_started = started is not None
                if isinstance(started, dict):
                    telemetry_span = started.get("span")
            except Exception:
                telemetry_started = False
        for gate in GATES_BY_PACKET[packet]:
            gid = gate["id"]
            print(f"== {gid} :: {' '.join(gate['cmd']) if gate['kind']=='shell' else 'serve-temporal + GET ' + gate.get('path', '/')}")
            if args.no_build and gid.startswith("G3-"):
                skip = {"id": gid, "cmd": "npm run build (omitido por --no-build)",
                        "exit": 99, "ok": False, "elapsed_s": 0.0,
                        "stdout": "", "stderr": "omitido; gate obligatorio => FAIL"}
                results.append(skip)
                record_gate_result(gid, skip)
                print(f"-- {gid} SKIP (tratado como FAIL por ser obligatorio)")
                continue
            if gate["kind"] == "shell":
                r = run_shell(gate["cmd"], gate["timeout_s"])
            elif gate["kind"] == "http_serve":
                port = find_free_port(args.port)
                if port != args.port:
                    print(f"-- puerto {args.port} ocupado, usando {port}")
                r = gate_http_serve(
                    port,
                    gate["timeout_s"],
                    gate.get("path", "/"),
                    tuple(gate.get("expect", ("StockFlash",))),
                )
            else:
                r = {"cmd": gid, "exit": 99, "ok": False, "elapsed_s": 0.0,
                     "stdout": "", "stderr": f"kind desconocido: {gate['kind']}"}
            r["id"] = gid
            r["mandatory"] = gate["mandatory"]
            results.append(r)
            record_gate_result(gid, r)
            print(f"-- {gid} {'PASS' if r['ok'] else 'FAIL'} (exit={r['exit']})")

        mandatory_fail = [r for r in results if r.get("mandatory") and not r["ok"]]
        verdict = "GATES_PASS" if not mandatory_fail else "GATES_FAIL"
    except BaseException:
        if telemetry_started and end_span is not None:
            try:
                end_span(packet, "verification", "verifier", status="FAIL", span=telemetry_span, source="native_gate_runner")
                telemetry_closed = True
            except Exception:
                pass
        raise
    finally:
        if telemetry_started and not telemetry_closed and end_span is not None:
            try:
                end_span(
                    packet,
                    "verification",
                    "verifier",
                    status="PASS" if verdict == "GATES_PASS" else "FAIL",
                    span=telemetry_span,
                    source="native_gate_runner",
                )
            except Exception:
                pass
    guide = guide_handoff(packet)
    roadmap = roadmap_handoff(packet)
    report = write_report(packet, results, verdict, guide, roadmap)
    if verdict == "GATES_PASS" and evidence is not None and evidence_before is not None:
        try:
            saved = evidence.record_success(packet, evidence_before, report, ROOT)
            if not saved:
                print("EVIDENCE_NOT_REUSABLE: source changed during verification.")
        except Exception:
            print("EVIDENCE_UNAVAILABLE: PASS remains valid; future run must verify again.")
    for doc in (guide, roadmap):
        print(f"Estado documental {doc['label']}: {doc['status']}")
        if doc["status"] in ("GUIA_PENDIENTE_CHATGPT", "ROADMAP_PENDIENTE_CHATGPT"):
            print(f"Pendiente ChatGPT ({doc['label']}): {doc['pending']}")
            print(f"Handoff ({doc['label']}): {doc['instruction']}")
    print(f"Reporte: {report}")
    print(f"Veredicto: {verdict}")
    if verdict != "GATES_PASS":
        print("FAIL: al menos un gate obligatorio fallo; no presentar como PASS.")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
