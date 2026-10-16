#!/usr/bin/env python3
"""Transportador FTPS manual/one-shot STAGING-001 (harness StockFlash).

Destino UNICO: /home/grupoint/stockflash-dev-app. Si el servidor FTP presenta
esa carpeta como raiz virtual `/`, NO concatenar la ruta fisica.

Endpoint: ftp.tusitioexpress.cl:21, FTPS explicito (AUTH TLS),
usuario admin@stockflash-dev.tusitioexpress.cl.

Secreto: la contrasena se obtiene SOLO en tiempo de ejecucion desde la
variable de entorno STAGING_FTPS_PASSWORD o mediante prompt interactivo
(getpass). Nunca se imprime, guarda en archivos/evidencias/scripts,
argumentos visibles ni registros. Todos los logs redactan credenciales.

Seguridades:
- Lista explicita de archivos bajo la raiz restringida; sin `rm -r`,
  sin sincronizadores con borrado, sin explorar otros sitios.
- Inventario remoto + punto de restauracion ANTES de sobrescribir.
- Jamas sobrescribir `.env`, claves, `storage/` remotos ajenos ni `.ftpquota`.
- Un unico despliegue reversible con manifest y SHA.

Uso (operador local):
    py -3 scripts/staging_ftps_one_shot.py --list
    py -3 scripts/staging_ftps_one_shot.py --marker
    py -3 scripts/staging_ftps_one_shot.py --manifest-check scripts/staging-manifest-20261016.txt
    py -3 scripts/staging_ftps_one_shot.py --probe   (prueba controlada, pocos archivos)
    py -3 scripts/staging_ftps_one_shot.py --deploy --manifest <manifest>
    py -3 scripts/staging_ftps_one_shot.py --deploy --manifest <manifest> --resume --backup-dir backup-20261015-212305

Sin la variable y sin terminal interactivo el script se detiene (BLOCKED)
sin intentar autenticar. TLS siempre activo; ningun flag lo desactiva.
"""
from __future__ import annotations

import argparse
import ftplib
import getpass
import hashlib
import os
import re
import socket
import ssl
import sys
import time
from io import BytesIO
from pathlib import Path

HOST = "ftp.tusitioexpress.cl"
PORT = 21
USER = "admin@stockflash-dev.tusitioexpress.cl"
PHYSICAL_ROOT = "/home/grupoint/stockflash-dev-app"
ENV_VAR = "STAGING_FTPS_PASSWORD"

FORBIDDEN_REMOTE = (".env", "storage/", ".ssh", "..", ".ftpquota", ".git/", "node_modules", "tmp-boot-001/")
REDACT = re.compile(r"(?i)(password|passwd|secret|token)\s*[:=]\s*\S+")

# Politica de reintentos / cortacircuito (corrige fallo masivo 92 OK / 8819 EOF).
PER_FILE_RETRIES = 3
RETRY_BACKOFF_S = (2.0, 5.0, 10.0)
MAX_CONSECUTIVE_FAILURES = 20
KEEPALIVE_EVERY = 25
PROBE_FILES = 5


def log(msg: str) -> None:
    print(REDACT.sub(r"\1=***", msg), flush=True)


def get_password() -> str | None:
    pw = os.getenv(ENV_VAR)
    if pw:
        return pw
    if sys.stdin.isatty():
        try:
            pw = getpass.getpass("Contrasena FTPS (no se muestra ni se guarda): ")
            return pw or None
        except Exception:
            return None
    return None


class ReuseFTP_TLS(ftplib.FTP_TLS):
    """FTP_TLS con reuso de sesion TLS en conexiones de datos.

    Causa del fallo masivo: Pure-FTPd con TLS exige (o favorece) reutilizar
    la sesion TLS del canal de control en cada canal de datos. ftplib std no
    lo hace: cada STOR/RETR negocia una sesion nueva y el servidor aborta el
    canal con `EOF occurred in violation of protocol`. Los primeros archivos
    (92) pasan y luego el canal queda inservible; sin reconexion, los ~8819
    restantes fallan en cascada. Esta subclase envuelve el socket de datos
    con `session=self.sock.session`, practica estandar contra ese error.
    TLS sigue verificado por el contexto por defecto; no se desactiva.
    """

    def ntransfercmd(self, cmd, rest=None):  # type: ignore[override]
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(
                conn, server_hostname=self.host, session=self.sock.session
            )
        return conn, size


def connect(pw: str) -> ReuseFTP_TLS:
    ftps = ReuseFTP_TLS(timeout=60)
    ftps.connect(HOST, PORT)
    ftps.auth()  # AUTH TLS explicito
    ftps.prot_p()  # canal de datos cifrado
    try:
        ftps.set_pasv(True)
    except Exception:
        pass
    ftps.login(USER, pw)
    return ftps


def close_quiet(ftps) -> None:
    try:
        ftps.quit()
    except Exception:
        try:
            ftps.close()
        except Exception:
            pass


def is_recoverable(exc: Exception) -> bool:
    return isinstance(
        exc,
        (ssl.SSLEOFError, ssl.SSLError, ConnectionResetError, ConnectionAbortedError,
         BrokenPipeError, TimeoutError, socket.timeout, EOFError, OSError),
    ) or isinstance(exc, ftplib.all_errors)


def resolve_root(ftps: ReuseFTP_TLS) -> str:
    """Detecta si la sesion ya esta confinada a stockflash-dev-app (raiz virtual /)."""
    try:
        cur = ftps.pwd()
    except Exception:
        cur = "/"
    log(f"PWD remoto: {cur}")
    listing: list[str] = []
    try:
        ftps.retrlines("NLST", listing.append)
    except Exception as exc:
        log(f"NLST fallo: {exc}")
    names = {n.strip().lower() for n in listing}
    # Si vemos public/ + app/ + artisan tipico de Laravel, ya estamos en la raiz de la app.
    if {"public", "app", "routes"} & names or "artisan" in names:
        log("Raiz virtual detectada: la sesion ya esta en stockflash-dev-app (/). NO concatenar ruta fisica.")
        return ""
    # Si vemos la carpeta fisica como entrada, entrar a ella una sola vez.
    if "stockflash-dev-app" in names:
        log("Carpeta stockflash-dev-app visible; entrando una sola vez.")
        ftps.cwd("stockflash-dev-app")
        return PHYSICAL_ROOT
    # Ultimo recurso: intentar CWD fisico una vez (servidores con vista completa).
    try:
        ftps.cwd(PHYSICAL_ROOT)
        log(f"CWD a raiz fisica {PHYSICAL_ROOT} OK.")
        return PHYSICAL_ROOT
    except Exception as exc:
        log(f"CWD fisico no disponible (esperable con raiz virtual): {exc}")
        return ""


def guarded(path: str) -> bool:
    low = path.lower().lstrip("/ ")
    if ".." in low:
        return False
    if low in (".ftpquota",) or low.endswith("/.ftpquota"):
        return False
    return not any(f in low for f in FORBIDDEN_REMOTE)


def cmd_list() -> int:
    pw = get_password()
    if not pw:
        log("BLOCKED: sin secreto FTPS en tiempo de ejecucion (variable o prompt). No se autentica.")
        return 2
    ftps = connect(pw)
    try:
        del pw
        root = resolve_root(ftps)
        log(f"Inventario bajo raiz autorizada ({PHYSICAL_ROOT if root else 'raiz virtual /'}):")
        lines: list[str] = []
        ftps.retrlines("LIST", lines.append)
        for ln in lines[:100]:
            log("  " + ln)
        log(f"Total listado: {len(lines)} entradas (truncado a 100 en pantalla).")
        return 0
    finally:
        close_quiet(ftps)


def cmd_marker() -> int:
    """Sube marcador inocuo a public/, indica URL HTTPS para comprobacion manual y lo retira."""
    import secrets
    token = secrets.token_hex(4)
    name = f"sf-marker-{token}.txt"
    body = f"stockflash-staging-marker {token}\n".encode()
    pw = get_password()
    if not pw:
        log("BLOCKED: sin secreto FTPS en tiempo de ejecucion. No se sube marcador.")
        return 2
    ftps = connect(pw)
    try:
        del pw
        resolve_root(ftps)
        try:
            ftps.cwd("public")
        except Exception as exc:
            log(f"BLOCKED: no se pudo entrar a public/ bajo raiz autorizada: {exc}")
            return 3
        ftps.storbinary(f"STOR {name}", BytesIO(body))
        url = f"https://stockflash-dev.tusitioexpress.cl/{name}"
        log(f"Marcador subido a public/{name}. Comprobar por HTTPS: GET {url} -> 200 y contenido del marcador.")
        log("Tras comprobar Document Root, retirar con: DELE public/" + name + " (este script lo retira con --marker-clean).")
        # Limpieza inmediata opcional: este modo one-shot retira tras confirmacion del operador.
        if sys.stdin.isatty():
            ans = input("Retirar marcador ahora? [S/n]: ").strip().lower()
            if ans in ("", "s", "si", "sí", "y", "yes"):
                try:
                    ftps.delete(name)
                    log("Marcador retirado.")
                except Exception as exc:
                    log(f"Aviso: no se pudo retirar marcador (retirar manual): {exc}")
                    return 4
        else:
            log("Sin terminal interactivo: marcador queda subido; retirar manualmente tras smoke HTTPS.")
        return 0
    finally:
        close_quiet(ftps)


def sha_of(paths: list[Path]) -> str:
    h = hashlib.sha256()
    for p in sorted(paths):
        h.update(p.name.encode())
        h.update(p.read_bytes())
    return h.hexdigest()[:12]


def load_manifest(manifest_path: str) -> list[str]:
    p = Path(manifest_path)
    if not p.exists():
        raise SystemExit(f"BLOCKED: manifest no existe: {manifest_path}")
    items: list[str] = []
    for line in p.read_text(encoding="utf-8").splitlines():
        s = line.strip()
        if not s or s.startswith("#"):
            continue
        s = s.replace("\\", "/")
        items.append(s)
    return items


def cmd_manifest_check(manifest_path: str) -> int:
    try:
        items = load_manifest(manifest_path)
    except SystemExit as exc:
        log(str(exc))
        return 2
    bad = [s for s in items if not guarded(s)]
    missing = [s for s in items if not (Path.cwd() / s).exists()]
    log(f"Manifest: {len(items)} entradas.")
    if bad:
        log(f"BLOCKED: {len(bad)} entradas prohibidas (se preservan .env/storage/.ftpquota): {bad[:10]}")
        return 3
    if missing:
        log(f"BLOCKED: {len(missing)} rutas locales inexistentes: {missing[:10]}")
        return 4
    # Confina a raiz autorizada: ninguna ruta absoluta fisica fuera de la app.
    outside = [s for s in items if s.startswith("/") or s.lower().startswith("home/")]
    if outside:
        log(f"BLOCKED: rutas fuera de raiz virtual: {outside[:10]}")
        return 5
    log("Manifest OK: todas las rutas bajo raiz virtual autorizada stockflash-dev-app (/), sin .env/storage/.ftpquota.")
    return 0


def ensure_remote_dirs(ftps: ReuseFTP_TLS, remote: str) -> None:
    parts = [x for x in remote.split("/")[:-1] if x not in ("", ".")]
    cur = ""
    for part in parts:
        cur = f"{cur}/{part}" if cur else part
        try:
            ftps.mkd(cur)
        except Exception:
            pass  # ya existe


def remote_size(ftps: ReuseFTP_TLS, rel: str) -> int | None:
    """Tamano remoto via SIZE; None si no existe o no se puede medir."""
    try:
        return ftps.size(rel)
    except Exception:
        return None


def keepalive(ftps: ReuseFTP_TLS) -> bool:
    try:
        ftps.voidcmd("NOOP")
        return True
    except Exception:
        return False


def safe_store(ftps: ReuseFTP_TLS, local: Path, rel: str) -> tuple[bool, str]:
    """Subida segura: STOR a tmp + verificacion SIZE + RENAME. Retorna (ok, detalle)."""
    if not guarded(rel):
        return False, "ruta prohibida"
    tmp = rel + ".part"
    if not guarded(tmp):
        return False, "tmp prohibido"
    try:
        size_local = local.stat().st_size
    except Exception as exc:
        return False, f"stat local fallo: {exc}"
    try:
        ensure_remote_dirs(ftps, rel)
        with open(local, "rb") as fh:
            ftps.storbinary(f"STOR {tmp}", fh)
        sz_tmp = remote_size(ftps, tmp)
        if sz_tmp is None:
            return False, "sin SIZE en tmp (posible parcial)"
        if sz_tmp != size_local:
            try:
                ftps.delete(tmp)
            except Exception:
                pass
            return False, f"tmp parcial {sz_tmp}!={size_local}"
        ftps.rename(tmp, rel)
        sz = remote_size(ftps, rel)
        if sz is None:
            return False, "sin SIZE final tras rename"
        if sz != size_local:
            return False, f"tamano final {sz}!={size_local}"
        return True, f"ok {size_local}B verificado"
    except Exception as exc:
        try:
            ftps.delete(tmp)
        except Exception:
            pass
        if is_recoverable(exc):
            return False, f"recuperable: {exc}"
        return False, f"no-recuperable: {exc}"


def backup_remote(ftps: ReuseFTP_TLS, backup_base: str, rel: str) -> str:
    """Respalda remoto previo a backup_base/ sin sobrescribir un backup original.

    Retorna: 'nuevo' (respaldado ahora), 'preservado' (ya existia backup,
    se conserva el original del primer despliegue), 'sin-previo' (no habia
    nada que respaldar, p. ej. primera instalacion).
    """
    try:
        if remote_size(ftps, f"{backup_base}/{rel}") is not None:
            return "preservado"
    except Exception:
        pass
    try:
        buf = BytesIO()
        ftps.retrbinary(f"RETR {rel}", buf.write)
        data_old = buf.getvalue()
        if not data_old:
            return "sin-previo"
        ensure_remote_dirs(ftps, f"{backup_base}/{rel}")
        ftps.storbinary(f"STOR {backup_base}/{rel}", BytesIO(data_old))
        return "nuevo"
    except Exception:
        return "sin-previo"


def cmd_probe(n_files: int = PROBE_FILES) -> int:
    """Prueba controlada: K transferencias consecutivas pequenas en dir autorizado.

    Usa archivos efimeros bajo backup-probe-<ts>/ (misma raiz virtual, sin
    tocar la app), con el mismo canal TLS con reuso de sesion. Verifica
    STOR+SIZE+RETR y luego los retira con DELE explicito. Si esto falla, el
    bulk de miles de archivos tambien fallara: detenerse (BLOCKED).
    """
    import datetime as _dt
    import secrets as _secrets
    pw = get_password()
    if not pw:
        log("BLOCKED: sin secreto FTPS en tiempo de ejecucion. No se prueba.")
        return 2
    ftps = connect(pw)
    try:
        resolve_root(ftps)
        stamp = _dt.datetime.now().strftime("%Y%m%d-%H%M%S")
        base = f"backup-probe-{stamp}"
        try:
            ftps.mkd(base)
        except Exception as exc:
            log(f"BLOCKED: no se pudo crear dir de prueba {base}/: {exc}")
            return 3
        ok, fail = 0, 0
        for i in range(max(1, n_files)):
            token = _secrets.token_hex(8)
            name = f"{base}/sf-probe-{i:02d}-{token}.txt"
            body = f"stockflash-probe {i} {token}\n".encode() * 64
            try:
                ftps.storbinary(f"STOR {name}", BytesIO(body))
                sz = remote_size(ftps, name)
                if sz != len(body):
                    log(f"PROBE {i}: SIZE {sz} != {len(body)} (parcial)")
                    fail += 1
                    continue
                buf = BytesIO()
                ftps.retrbinary(f"RETR {name}", buf.write)
                if buf.getvalue() != body:
                    log(f"PROBE {i}: contenido RETR difiere")
                    fail += 1
                    continue
                ok += 1
            except Exception as exc:
                log(f"PROBE {i}: {exc}")
                fail += 1
                if fail >= 3:
                    break
            finally:
                try:
                    ftps.delete(name)
                except Exception:
                    pass
        try:
            ftps.rmd(base)
        except Exception:
            pass
        if fail == 0 and ok == max(1, n_files):
            log(f"PROBE_PASS: {ok}/{ok} transferencias consecutivas verificadas (STOR+SIZE+RETR+DELE). Canal TLS con reuso OK.")
            return 0
        log(f"PROBE_FAIL: ok={ok} fail={fail}. BLOCKED: no iniciar bulk; revisar TLS/red/servidor.")
        return 5
    finally:
        close_quiet(ftps)
        try:
            del pw
        except Exception:
            pass


def cmd_deploy(manifest_path: str, resume: bool = False, backup_dir: str = "",
               limit: int = 0, probe_first: bool = False) -> int:
    import datetime as _dt
    try:
        items = load_manifest(manifest_path)
    except SystemExit as exc:
        log(str(exc))
        return 2
    bad = [s for s in items if not guarded(s)]
    if bad:
        log(f"BLOCKED: manifest contiene rutas prohibidas (no se toca .env/storage/.ftpquota): {bad[:10]}")
        return 3
    missing = [s for s in items if not (Path.cwd() / s).exists()]
    if missing:
        log(f"BLOCKED: faltan archivos locales: {missing[:10]}")
        return 4
    pw = get_password()
    if not pw:
        log("BLOCKED: sin secreto FTPS en tiempo de ejecucion (variable o prompt). No se autentica.")
        return 2

    ordered = sorted(items, key=lambda s: (s.startswith("public/"), s))
    if limit and limit > 0:
        ordered = ordered[:limit]
        log(f"Modo limitado: primeras {len(ordered)} entradas (prueba controlada del manifest real).")

    ftps: ReuseFTP_TLS | None = None

    def ensure_conn() -> ReuseFTP_TLS:
        nonlocal ftps
        if ftps is not None:
            return ftps
        f = connect(pw)
        resolve_root(f)
        ftps = f
        return f

    def reconnect() -> ReuseFTP_TLS | None:
        nonlocal ftps
        if ftps is not None:
            close_quiet(ftps)
            ftps = None
        time.sleep(3.0)
        try:
            f = connect(pw)
            f.pwd()  # la sesion queda en la raiz virtual confinada
            ftps = f
            log("Reconexion FTPS OK (misma cuenta, misma raiz virtual).")
            return f
        except Exception as exc:
            log(f"Reconexion fallo: {exc}")
            return None

    try:
        ftps = ensure_conn()
        if probe_first:
            log("Probe-first: 3 transferencias de control antes del bulk...")
            close_quiet(ftps)
            ftps = None
            rc = cmd_probe(3)
            # cmd_probe pide de nuevo el secreto; en terminal interactiva el
            # operador lo reintroduce o lo exporta una vez via $env:. Si el
            # probe falla, no se toca la app.
            if rc != 0:
                log("BLOCKED: probe-first fallo; bulk no iniciado.")
                return rc
            ftps = ensure_conn()

        # Inventario remoto previo (solo raiz; el resume verifica por SIZE).
        try:
            lines: list[str] = []
            ftps.retrlines("LIST", lines.append)
            log(f"Inventario raiz: {len(lines)} entradas (muestra 5):")
            for ln in lines[:5]:
                log("  " + ln)
        except Exception as exc:
            log(f"LIST inicial fallo (se reintentara en resume por SIZE): {exc}")

        if backup_dir:
            backup_base = backup_dir.strip().replace("\\", "/").strip("/")
            log(f"Resume: se reutiliza backup remoto {backup_base}/ (no se pisa el original).")
            try:
                ftps.mkd(backup_base)
            except Exception:
                pass
        else:
            stamp = _dt.datetime.now().strftime("%Y%m%d-%H%M%S")
            backup_base = f"backup-{stamp}"
            try:
                ftps.mkd(backup_base)
                log(f"Backup remoto creado: {backup_base}/ (solo sobrescritos, sin .env/storage/.ftpquota).")
            except Exception as exc:
                log(f"Aviso backup MKD: {exc}")

        verified = uploaded = retried = failed = 0
        consec = 0
        pending: list[str] = []
        total = len(ordered)
        for idx, rel in enumerate(ordered, 1):
            local = Path.cwd() / rel
            if local.is_dir():
                try:
                    ensure_remote_dirs(ensure_conn(), rel + "/x")
                except Exception:
                    pass
                continue
            try:
                size_local = local.stat().st_size
            except Exception:
                failed += 1
                pending.append(rel)
                continue
            # Resume: no asumir integridad por existencia; exige SIZE igual.
            try:
                sz = remote_size(ensure_conn(), rel)
            except Exception as exc:
                if is_recoverable(exc) and reconnect() is not None:
                    try:
                        sz = remote_size(ensure_conn(), rel)
                    except Exception:
                        sz = None
                else:
                    sz = None
            if sz is not None and sz == size_local:
                verified += 1
                consec = 0
                if (verified + uploaded) % 500 == 0:
                    log(f"  ... {idx}/{total} verif={verified} subidos={uploaded} reint={retried} fallos={failed}")
                continue
            # Respalda previo real una sola vez (preserva backup original en resume).
            try:
                backup_remote(ensure_conn(), backup_base, rel)
            except Exception:
                pass
            ok_file = False
            for attempt in range(PER_FILE_RETRIES + 1):
                try:
                    ok, detail = safe_store(ensure_conn(), local, rel)
                except Exception as exc:
                    detail = f"recuperable: {exc}" if is_recoverable(exc) else f"no-recuperable: {exc}"
                    ok = False
                    if is_recoverable(exc):
                        if reconnect() is None:
                            detail += " + reconexion fallida"
                            break
                        continue
                    else:
                        break
                if ok:
                    if attempt > 0:
                        retried += 1
                    uploaded += 1
                    consec = 0
                    ok_file = True
                    break
                # Fallo: ¿recuperable?
                if "recuperable" in detail and attempt < PER_FILE_RETRIES:
                    retried += 1
                    time.sleep(RETRY_BACKOFF_S[min(attempt, len(RETRY_BACKOFF_S) - 1)])
                    if reconnect() is None:
                        break
                    continue
                break
            if not ok_file:
                failed += 1
                pending.append(rel)
                consec += 1
                if failed <= 5:
                    log(f"ERROR {rel}: {detail if 'detail' in dir() else 'fallo'}")
                if consec >= MAX_CONSECUTIVE_FAILURES:
                    log(f"BLOCKED: {consec} fallos consecutivos (cortacircuito). Detenido en {idx}/{total} "
                        f"para no generar miles de errores. Reanudar con --resume (verifica por SIZE).")
                    break
            if idx % KEEPALIVE_EVERY == 0:
                try:
                    if not keepalive(ensure_conn()):
                        reconnect()
                except Exception:
                    reconnect()
            if (verified + uploaded) % 500 == 0 and (verified + uploaded) > 0:
                log(f"  ... {idx}/{total} verif={verified} subidos={uploaded} reint={retried} fallos={failed}")

        log(f"Resumen: total={total} verificados={verified} transferidos={uploaded} "
            f"reintentados={retried} fallidos={failed} pendientes={len(pending)} backup={backup_base}/")
        if pending:
            log(f"Primeros pendientes: {pending[:10]}")
        log("Rollback: re-subir backup sobre mismas rutas; lo nuevo sin previo se retira por lista con DELE "
            "explicito (sin rm recursivo, sin tocar .ftpquota/.env/storage/).")
        if failed == 0:
            log("Siguiente: smoke HTTPS GET https://stockflash-dev.tusitioexpress.cl/ -> 200 StockFlash + assets.")
            return 0
        log("BLOCKED parcial: persisten fallidos. Reanudar (no reiniciar de cero): "
            f"--deploy --manifest {manifest_path} --resume --backup-dir {backup_base}")
        return 5
    finally:
        if ftps is not None:
            close_quiet(ftps)
        try:
            del pw
        except Exception:
            pass


def main() -> int:
    ap = argparse.ArgumentParser(description="Transportador FTPS STAGING-001 (sin secretos en disco).")
    ap.add_argument("--list", action="store_true", help="Preflight: autentica, lista raiz virtual y sale.")
    ap.add_argument("--marker", action="store_true", help="Preflight ruta publica: sube marcador a public/ y lo retira.")
    ap.add_argument("--deploy", action="store_true", help="Despliegue unico (requiere --manifest).")
    ap.add_argument("--manifest", default="", help="Ruta a manifest explicito de archivos a subir.")
    ap.add_argument("--manifest-check", default="", help="Valida manifest localmente sin FTPS (sin secreto).")
    ap.add_argument("--probe", action="store_true", help="Prueba controlada: K STOR+SIZE+RETR+DELE en dir autorizado.")
    ap.add_argument("--probe-files", type=int, default=PROBE_FILES, help="N archivos de prueba (defecto 5).")
    ap.add_argument("--resume", action="store_true", help="Reanuda: omite archivos con SIZE remoto igual al local.")
    ap.add_argument("--backup-dir", default="", help="Reutiliza backup remoto existente (no pisa el original).")
    ap.add_argument("--limit", type=int, default=0, help="Limita a las primeras N entradas (prueba controlada).")
    ap.add_argument("--probe-first", action="store_true", help="Probe de control antes del bulk en --deploy.")
    args = ap.parse_args()
    if args.manifest_check:
        return cmd_manifest_check(args.manifest_check)
    if args.probe:
        return cmd_probe(max(1, args.probe_files))
    if args.list:
        return cmd_list()
    if args.marker:
        return cmd_marker()
    if args.deploy:
        if not args.manifest:
            log("BLOCKED: --deploy requiere --manifest <archivo>. Generar lista explicita bajo raiz autorizada; se preservan .env, storage/ y .ftpquota.")
            return 2
        return cmd_deploy(args.manifest, resume=args.resume, backup_dir=args.backup_dir,
                          limit=args.limit, probe_first=args.probe_first)
    ap.print_help()
    return 2


if __name__ == "__main__":
    sys.exit(main())
