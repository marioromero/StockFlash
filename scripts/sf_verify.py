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
        print(f"== {gid} :: {' '.join(gate['cmd']) if gate['kind']=='shell' else 'serve-temporal + GET ' + gate.get('path', '/')}")
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
        print(f"-- {gid} {'PASS' if r['ok'] else 'FAIL'} (exit={r['exit']})")

    mandatory_fail = [r for r in results if r.get("mandatory") and not r["ok"]]
    verdict = "GATES_PASS" if not mandatory_fail else "GATES_FAIL"
    guide = guide_handoff(packet)
    roadmap = roadmap_handoff(packet)
    report = write_report(packet, results, verdict, guide, roadmap)
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
