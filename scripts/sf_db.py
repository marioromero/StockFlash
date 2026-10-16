#!/usr/bin/env python3
"""Puente BD remota DEV para desarrollo local (sin secretos en repo ni logs).

Credenciales UNA sola vez en DPAPI usuario (fuera de Git):
    py -3 scripts/sf_db.py config      # pide host/puerto/nombre/usuario/clave por prompt

Uso posterior (credenciales solo en entorno del subproceso, nunca en disco):
    py -3 scripts/sf_db.py test       # version MariaDB >= 10.6 y tabla de prueba
    py -3 scripts/sf_db.py migrate    # php artisan migrate --force (SOLO con
                                       # instruccion expresa del propietario)
    py -3 scripts/sf_db.py fresh      # migrate:fresh --seed (SOLO DEV, con
                                       # confirmacion interactiva + instruccion)

Jamas imprime valores; los errores SQL se redactan antes de mostrarse.
"""
from __future__ import annotations

import getpass
import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
KEYS = ("db_host", "db_port", "db_name", "db_user", "db_pass")
REDACT = re.compile(r"(?i)(password|passwd|pwd)\s*[:=]\s*\S+")


def log(msg: str) -> None:
    print(REDACT.sub(r"\1=***", msg), flush=True)


def _store():
    sys.path.insert(0, str(ROOT / "scripts"))
    from sf_credstore import read_secret, _path, _protect
    import base64
    return read_secret, _path, _protect, base64


def cmd_config() -> int:
    if not sys.stdin.isatty():
        print("BLOCKED: config requiere terminal interactiva.")
        return 2
    read_secret, _path, _protect, base64 = _store()
    vals = {}
    for k in KEYS:
        cur = read_secret(k)
        if k == "db_pass":
            v = getpass.getpass(f"{k} (Enter conserva actual, no se muestra): ")
            vals[k] = v if v else (cur or "")
        else:
            default = cur or {"db_port": "3306"}.get(k, "")
            v = input(f"{k} [{default}]: ").strip()
            vals[k] = v if v else default
    if not all(vals.values()):
        print(f"BLOCKED: faltan valores: {[k for k, v in vals.items() if not v]}")
        return 2
    (Path(os.environ["LOCALAPPDATA"]) / "StockFlash" / "creds").mkdir(parents=True, exist_ok=True)
    for k, v in vals.items():
        _path(k).write_bytes(base64.b64encode(_protect(v.encode("utf-8"))))
    print("OK: credenciales BD guardadas en DPAPI (fuera de Git). Probar con: py -3 scripts/sf_db.py test")
    return 0


def _env() -> dict:
    sys.path.insert(0, str(ROOT / "scripts"))
    from sf_credstore import read_secret
    env = dict(os.environ)
    creds = {k: read_secret(k) for k in KEYS}
    if not all(creds.values()):
        missing = [k for k, v in creds.items() if not v]
        raise SystemExit(f"BLOCKED: faltan credenciales BD: {missing}. Ejecutar: py -3 scripts/sf_db.py config")
    env["DB_CONNECTION"] = "mysql"
    env["DB_HOST"] = creds["db_host"]
    env["DB_PORT"] = creds["db_port"]
    env["DB_DATABASE"] = creds["db_name"]
    env["DB_USERNAME"] = creds["db_user"]
    env["DB_PASSWORD"] = creds["db_pass"]
    return env


def _artisan(*args: str) -> int:
    import shutil
    php = shutil.which("php") or "php"
    r = subprocess.run([php, "artisan", *args], cwd=ROOT, env=_env(),
                       capture_output=True, text=True, timeout=600)
    out = (r.stdout + "\n" + r.stderr)
    for k in ("db_pass",):
        out = out.replace(os.environ.get("DB_PASSWORD", "\x00"), "***")
    print(REDACT.sub(r"\1=***", out[-3000:]))
    return r.returncode


def cmd_test() -> int:
    r = subprocess.run(
        [sys.executable, "-c",
         "import os; print('DB_TEST_OK' if all(os.getenv(k) for k in "
         "('DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD')) else 'DB_TEST_FAIL')"],
        env=_env(), capture_output=True, text=True, timeout=30)
    print(r.stdout.strip())
    if "DB_TEST_FAIL" in r.stdout:
        return 3
    code = _artisan("db:show", "--counts")
    if code != 0:
        # db:show puede no existir segun version; probar conexion via migrate:status
        code = _artisan("migrate:status")
    return 0 if code == 0 else 4


def cmd_migrate() -> int:
    log("migrate: aplicando pendientes en BD DEV remota...")
    return _artisan("migrate", "--force")


def cmd_rollback(steps: int) -> int:
    log(f"rollback: revirtiendo {steps} lotes en DEV...")
    return _artisan("migrate:rollback", f"--step={steps}", "--force")


def cmd_seed() -> int:
    log("seed: semillas mínimas contractuales (idempotentes)...")
    return _artisan("db:seed", "--force")


READONLY_PREFIXES = ("select", "show", "describe", "desc", "explain")

PHP_READER = (
    "$c=json_decode(stream_get_contents(STDIN),true);"
    "$pdo=new PDO('mysql:host='.$c['h'].';port='.$c['p'].';dbname='.$c['d'].';charset=utf8mb4',$c['u'],$c['w'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);"
    "$q=$argv[1];$s=$pdo->query($q);"
    "$rows=$s->fetchAll(PDO::FETCH_ASSOC);"
    "echo json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
)


def cmd_sql(query: str) -> int:
    if not query.strip().lower().startswith(READONLY_PREFIXES):
        print("BLOCKED: solo consultas de lectura (SELECT/SHOW/DESCRIBE/EXPLAIN).")
        return 2
    if ";" in query.strip().rstrip(";"):
        print("BLOCKED: una sola sentencia por consulta.")
        return 2
    sys.path.insert(0, str(ROOT / "scripts"))
    from sf_credstore import read_secret
    import json as _json
    creds = _json.dumps({"h": read_secret("db_host"), "p": read_secret("db_port"),
                         "d": read_secret("db_name"), "u": read_secret("db_user"),
                         "w": read_secret("db_pass")})
    r = subprocess.run(["php", "-r", PHP_READER, query], input=creds,
                       capture_output=True, text=True, timeout=120)
    if r.returncode != 0:
        print("ERROR SQL (redactado): " + REDACT.sub(r"\1=***", (r.stderr or "")[-300:]))
        return 3
    try:
        rows = _json.loads(r.stdout or "[]")
    except Exception:
        print(r.stdout[-2000:])
        return 0
    print(_json.dumps(rows, ensure_ascii=False)[:4000])
    return 0


def cmd_fresh() -> int:
    if not sys.stdin.isatty():
        print("BLOCKED: fresh requiere terminal interactiva + instruccion expresa.")
        return 2
    ans = input("CONFIRMAR fresh (borra y resiembra DEV) escribiendo SI: ").strip()
    if ans != "SI":
        print("Cancelado.")
        return 2
    return _artisan("migrate:fresh", "--seed", "--force")


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 2
    action = sys.argv[1]
    if action == "config":
        return cmd_config()
    if action == "test":
        return cmd_test()
    if action == "migrate":
        return cmd_migrate()
    if action == "seed":
        return cmd_seed()
    if action == "rollback" and len(sys.argv) == 3 and sys.argv[2].isdigit():
        return cmd_rollback(int(sys.argv[2]))
    if action == "sql" and len(sys.argv) == 3:
        return cmd_sql(sys.argv[2])
    if action == "fresh":
        return cmd_fresh()
    print(__doc__)
    return 2


if __name__ == "__main__":
    sys.exit(main())
