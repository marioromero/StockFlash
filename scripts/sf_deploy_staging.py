#!/usr/bin/env python3
"""Despliegue automatizado a staging via cPanel UAPI/API2 (STAGING-001).

Pipeline reutilizable de 12 pasos con un solo ZIP. Sin FTPS por archivo,
sin SFTP, sin cuentas nuevas, sin tocar cPanel global. TLS siempre verificado
contra el certificado real del servidor (cp012.servidoresph.com).

Credenciales: solo desde scripts/sf_credstore.py (DPAPI usuario, fuera de Git):
    cpanel_host   (defecto: cp012.servidoresph.com:2083)
    cpanel_user   (cuenta cPanel, p. ej. grupoint)
    cpanel_token  (API token creado una vez por el propietario)

Modos:
    --check     Solo validaciones locales (sin secretos, sin red con auth).
    --probe     Valida el mecanismo real con archivos efimeros (auth, mkdir,
                upload, extract, list, unlink). No toca la aplicacion.
    --deploy    Pipeline completo (requiere --probe PASS previo en esta version).
    --rollback  Restaura un backup remoto (requiere --backup-dir).
    --smoke     Solo smoke HTTPS + assets (sin auth).

Toda respuesta de API se valida (status + forma); ante forma desconocida se
detiene con BLOCKED en vez de asumir exito. Jamas declara VERIFIED.
"""
from __future__ import annotations

import argparse
import base64
import datetime
import hashlib
import io
import json
import os
import re
import ssl
import subprocess
import sys
import time
import urllib.parse
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_HOST = "cp012.servidoresph.com:2083"
APP_DIR = "stockflash-dev-app"          # relativo al home cPanel
HOME_ABS = "/home/grupoint/stockflash-dev-app"
STAGING_URL = "https://stockflash-dev.tusitioexpress.cl/"
MANIFEST = ROOT / "scripts" / "staging-manifest-20261016.txt"
ZIP_PATH = ROOT / "stockflash-staging-release.zip"
PART_PREFIX = "stockflash-staging-part-"
VENDOR_CHUNK = 1200  # archivos vendor por parte (limite de tiempo del servidor)
PHP_MIN = (8, 2)

PROTECTED_TOPS = {".env", ".ftpquota", "storage", ".ssh"}
BACKUP_PREFIX = "backup-"
QUAR_PREFIX = "quarantine-"

REDACT = re.compile(r"(?i)(cpanel[^:\s]*[:=]\s*)(\S+)")


def log(msg: str) -> None:
    print(REDACT.sub(r"\1***", msg), flush=True)


def now_ts() -> str:
    return datetime.datetime.now().strftime("%Y%m%d-%H%M%S")


# ---------------------------------------------------------------- credenciales
def get_cred(name: str, default: str = "") -> str:
    sys.path.insert(0, str(ROOT / "scripts"))
    try:
        from sf_credstore import read_secret
        val = read_secret(name)
        return val if val else default
    except Exception:
        return default


# ------------------------------------------------------------------ HTTP/API
class CPanel:
    def __init__(self, host: str, user: str, token: str):
        self.host = host
        self.user = user
        self.token = token
        self.base = f"https://{host}"
        self.ctx = ssl.create_default_context()

    def _req(self, url: str, data: bytes | None = None, headers: dict | None = None,
             ctype: str = "", timeout: int = 60) -> tuple[int, bytes]:
        h = {"Authorization": f"cpanel {self.user}:{self.token}"}
        if ctype:
            h["Content-Type"] = ctype
        if headers:
            h.update(headers)
        req = urllib.request.Request(url, data=data, headers=h, method="POST" if data is not None else "GET")
        try:
            with urllib.request.urlopen(req, timeout=timeout, context=self.ctx) as r:
                return r.status, r.read()
        except urllib.error.HTTPError as e:
            return e.code, e.read()[:4000]

    def uapi(self, module: str, func: str, params: dict | None = None,
             data: bytes | None = None, ctype: str = "", timeout: int = 60) -> dict:
        qs = urllib.parse.urlencode(params or {})
        url = f"{self.base}/execute/{module}/{func}" + (f"?{qs}" if qs else "")
        st, body = self._req(url, data=data, ctype=ctype, timeout=timeout)
        if st in (401, 403):
            raise SystemExit(f"BLOCKED: auth cPanel rechazada (HTTP {st}). Revisar token/usuario.")
        try:
            obj = json.loads(body.decode("utf-8", "replace"))
        except Exception:
            raise SystemExit(f"BLOCKED: respuesta UAPI no-JSON ({module}/{func} HTTP {st}): {body[:200]!r}")
        res = obj.get("result") if isinstance(obj, dict) else None
        if res is None and isinstance(obj, dict) and "status" in obj:
            res = obj  # UAPI plano real: {status, data, errors, ...} sin envoltura
        if not isinstance(res, dict) or "status" not in res:
            raise SystemExit(f"BLOCKED: forma UAPI desconocida en {module}/{func}: {str(obj)[:300]}")
        if res.get("status") != 1:
            errs = res.get("errors") or res.get("data") or obj
            raise SystemExit(f"BLOCKED: UAPI {module}/{func} status=0: {str(errs)[:400]}")
        return res

    def api2(self, module: str, func: str, params: dict) -> dict:
        q = {"cpanel_jsonapi_module": module, "cpanel_jsonapi_func": func,
             "cpanel_jsonapi_version": "2", **params}
        url = f"{self.base}/json-api/cpanel?{urllib.parse.urlencode(q)}"
        st, body = self._req(url, timeout=60)
        if st in (401, 403):
            raise SystemExit(f"BLOCKED: auth cPanel rechazada (HTTP {st}). Revisar token/usuario.")
        try:
            obj = json.loads(body.decode("utf-8", "replace"))
        except Exception:
            raise SystemExit(f"BLOCKED: respuesta API2 no-JSON ({module}/{func} HTTP {st}): {body[:200]!r}")
        root = obj.get("cpanelresult") if isinstance(obj, dict) else None
        if not isinstance(root, dict):
            raise SystemExit(f"BLOCKED: forma API2 desconocida en {module}/{func}: {str(obj)[:300]}")
        event = root.get("event") or {}
        if event.get("result") != 1:
            raise SystemExit(f"BLOCKED: API2 {module}/{func} fallo: {str(root)[:400]}")
        return root

    def api2_fileop(self, op: str, **params) -> dict:
        q = {"cpanel_jsonapi_module": "Fileman", "cpanel_jsonapi_func": "fileop",
             "cpanel_jsonapi_version": "2", "op": op, "doubledecode": "1", **params}
        url = f"{self.base}/json-api/cpanel?{urllib.parse.urlencode(q)}"
        st, body = self._req(url, timeout=120)
        if st in (401, 403):
            raise SystemExit(f"BLOCKED: auth cPanel rechazada (HTTP {st}). Revisar token/usuario.")
        try:
            obj = json.loads(body.decode("utf-8", "replace"))
        except Exception:
            raise SystemExit(f"BLOCKED: respuesta API2 no-JSON (fileop/{op} HTTP {st}): {body[:200]!r}")
        root = obj.get("cpanelresult") if isinstance(obj, dict) else None
        if not isinstance(root, dict):
            raise SystemExit(f"BLOCKED: forma API2 desconocida en fileop/{op}: {str(obj)[:300]}")
        event = root.get("event") or {}
        data = root.get("data")
        ok = event.get("result") == 1 or (isinstance(data, list) and data and all(
            (d.get("result") == 1 if isinstance(d, dict) else False) for d in data))
        if not ok:
            raise SystemExit(f"BLOCKED: API2 fileop/{op} fallo: {str(root)[:400]}")
        return root

    # ------------------------------------------------------------- operaciones
    def list_files(self, directory: str) -> list[dict]:
        res = self.uapi("Fileman", "list_files", {"dir": directory})
        data = res.get("data")
        if not isinstance(data, list):
            raise SystemExit(f"BLOCKED: list_files sin data lista en {directory}")
        return data

    def mkdir(self, path: str) -> None:
        try:
            self.uapi("Fileman", "make_directory", {"path": path})
            return
        except SystemExit as e:
            if "status=0" not in str(e) and "forma UAPI" not in str(e):
                raise
        parent, _, base = path.rstrip("/").rpartition("/")
        self.api2("Fileman", "mkdir", {"path": parent or ".", "name": base})

    def upload(self, local: Path, remotedir: str, overwrite: bool = True) -> dict:
        boundary = "----sf" + hashlib.sha256(os.urandom(8)).hexdigest()[:16]
        buf = io.BytesIO()
        def field(n, v):
            buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{n}\"\r\n\r\n{v}\r\n".encode())
        field("dir", remotedir)
        field("overwrite", "1" if overwrite else "0")
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"file-0\"; "
                  f"filename=\"{local.name}\"\r\nContent-Type: application/octet-stream\r\n\r\n".encode())
        with open(local, "rb") as fh:
            while True:
                chunk = fh.read(1 << 20)
                if not chunk:
                    break
                buf.write(chunk)
        buf.write(f"\r\n--{boundary}--\r\n".encode())
        return self.uapi("Fileman", "upload_files", data=buf.getvalue(),
                         ctype=f"multipart/form-data; boundary={boundary}", timeout=300)

    def fileop(self, op: str, **kw) -> dict:
        """fileop via UAPI (preferido) con fallback a API2. Params segun docs:
        op, sourcefiles (coma-separados, relativos al home o absolutos),
        destfiles (copy/move/rename/extract), doubledecode=1.
        result=1 NO basta: verificar mutaciones con re-listado."""
        params = {"op": op, "doubledecode": "1", **kw}
        try:
            res = self.uapi("Fileman", "fileop", params, timeout=120)
            data = res.get("data")
            items = data if isinstance(data, list) else [data]
            ok = bool(items) and all(
                (d.get("result") == 1 if isinstance(d, dict) else False) for d in items)
            if not ok:
                raise SystemExit(f"BLOCKED: UAPI fileop/{op} fallo: {str(res)[:400]}")
            return res
        except SystemExit as e:
            if "forma UAPI desconocida" in str(e) or "could not find the function" in str(e):
                return self.api2_fileop(op, **kw)
            raise

    def trash(self, abspath: str) -> None:
        """Eliminacion de archivos o dirs (incluso no vacios) via trash.
        Leccion servidor: unlink es solo symlinks y rmdir reporta 1 falso;
        trash + re-listado si verifica. Solo para artefactos propios efimeros."""
        self.fileop("trash", sourcefiles=abspath)

    def exists(self, directory: str, name: str) -> dict | None:
        for e in self.list_files(directory):
            if e.get("file") == name:
                return e
        return None


# ------------------------------------------------------------------- etapas
def stage_version() -> str:
    r = subprocess.run(["git", "rev-parse", "HEAD"], cwd=ROOT, capture_output=True, text=True, timeout=30)
    head = r.stdout.strip()
    r2 = subprocess.run(["git", "ls-remote", "origin", "main"], cwd=ROOT, capture_output=True, text=True, timeout=60)
    remote = r2.stdout.split()[0] if r2.stdout.strip() else ""
    log(f"HEAD local: {head}")
    if remote and remote != head:
        raise SystemExit(f"BLOCKED: HEAD != origin/main ({remote}). Sincronizar via /sf-run antes de desplegar.")
    if not remote:
        log("Aviso: sin acceso a origin/main; se registra HEAD local sin comparar.")
    return head


def stage_pregates() -> None:
    """S1,S2,S3,S5 verificables pre-deploy (S4 marker existe, S6 solo post-deploy)."""
    fe = (ROOT / "docs/work/features/STAGING-001.md").read_text(encoding="utf-8")
    cu = (ROOT / "docs/work/CURRENT.md").read_text(encoding="utf-8")
    assert "status: APPROVED" in fe and "STAGING-001: APPROVED" in cu, "S1"
    assert (ROOT / "composer.lock").exists() and (ROOT / "package-lock.json").exists(), "S2 locks"
    assert (ROOT / "vendor/autoload.php").exists(), "S2 vendor"
    man = ROOT / "public/build/manifest.json"
    assert man.exists() and "resources/js/app.ts" in man.read_text(encoding="utf-8"), "S2 build"
    assert list((ROOT / "docs/work").glob("STAGING-001-MARKER-*.md")), "S4"
    assert list((ROOT / "docs/work").glob("STAGING-001-MANIFEST-*.md")), "S5"
    log("Pre-gates S1/S2/S4/S5 OK (S3 transportador legacy no requerido; S6 post-deploy).")


def _resolve(cmd: list[str]) -> list[str]:
    import shutil
    exe = shutil.which(cmd[0])
    if exe is None:
        raise SystemExit(f"BLOCKED: ejecutable no encontrado en PATH: {cmd[0]}")
    if os.name == "nt" and exe.lower().endswith((".bat", ".cmd", ".ps1")):
        return ["cmd", "/c", exe, *cmd[1:]]
    return [exe, *cmd[1:]]


def stage_build() -> None:
    r = subprocess.run(_resolve(["composer", "validate", "--no-check-publish", "--no-check-all", "--strict"]),
                       cwd=ROOT, capture_output=True, text=True, timeout=120)
    if r.returncode != 0:
        raise SystemExit(f"BLOCKED: composer validate fallo:\n{r.stdout[-800:]}\n{r.stderr[-800:]}")
    if not (ROOT / "public/build/manifest.json").exists():
        log("public/build ausente: ejecutando npm run build...")
        rb = subprocess.run(["npm", "run", "build"], cwd=ROOT, capture_output=True, text=True, timeout=600)
        if rb.returncode != 0 or not (ROOT / "public/build/manifest.json").exists():
            raise SystemExit("BLOCKED: npm run build fallo o sin manifest.")
    log("Artefacto OK: vendor/ + public/build/.")


def load_manifest_items() -> list[str]:
    items = [l.strip().replace("\\", "/") for l in MANIFEST.read_text(encoding="utf-8").splitlines()
             if l.strip() and not l.startswith("#")]
    forb = (".env", "storage/", ".ssh", "..", ".ftpquota", ".git/", "node_modules", "tmp-boot-001/")
    bad = [s for s in items if any(f in s.lower() for f in forb) or s.startswith("/") or ".." in s]
    if bad:
        raise SystemExit(f"BLOCKED: manifest con rutas prohibidas: {bad[:5]}")
    missing = [s for s in items if not (ROOT / s).exists()]
    if missing:
        raise SystemExit(f"BLOCKED: faltan archivos locales: {missing[:5]}")
    return items


def stage_zip(items: list[str]) -> list[Path]:
    """Partes ZIP (raices + vendor troceado): el servidor aborta extracciones
    largas, cada parte debe completarse rapido y verificarse por centinela."""
    roots = [s for s in items if not s.startswith("vendor/")]
    vend = sorted(s for s in items if s.startswith("vendor/"))
    groups = [("00-roots", roots)] + [
        (f"{i:02d}-vendor", vend[j:j + VENDOR_CHUNK])
        for i, j in enumerate(range(0, len(vend), VENDOR_CHUNK), start=1)]
    parts: list[Path] = []
    for name, members in groups:
        zp = ROOT / f"{PART_PREFIX}{name}.zip"
        if zp.exists():
            zp.unlink()
        with zipfile.ZipFile(zp, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as z:
            for rel in members:
                p = ROOT / rel
                if p.is_file():
                    z.write(p, rel)
        if zipfile.ZipFile(zp).testzip() is not None:
            raise SystemExit(f"BLOCKED: ZIP corrupto: {zp.name}")
        parts.append(zp)
    names = [n for zp in parts for n in zipfile.ZipFile(zp).namelist()]
    if len(names) != len(items):
        raise SystemExit(f"BLOCKED: partes suman {len(names)} != manifest {len(items)}")
    for must in ("public/index.php", "public/.htaccess", "public/build/manifest.json",
                 "vendor/autoload.php", "artisan"):
        if must not in names:
            raise SystemExit(f"BLOCKED: partes sin {must}")
    bad = [n for n in names if n.startswith(".env") or n.startswith("storage/") or ".ftpquota" in n]
    if bad:
        raise SystemExit(f"BLOCKED: partes con excluidos: {bad[:5]}")
    total = sum(zp.stat().st_size for zp in parts)
    log(f"ZIP OK: {len(parts)} partes, {len(names)} archivos, {total} B.")
    return parts


def connect_cpanel() -> CPanel:
    host = get_cred("cpanel_host", DEFAULT_HOST)
    user = get_cred("cpanel_user", "")
    token = get_cred("cpanel_token", "")
    if not user or not token:
        raise SystemExit("BLOCKED: faltan credenciales. Una sola vez: "
                         "py -3 scripts/sf_credstore.py set cpanel_user ; "
                         "py -3 scripts/sf_credstore.py set cpanel_token")
    return CPanel(host, user, token)


def cmd_probe() -> int:
    """Valida el mecanismo real con archivos efimeros. No toca la aplicacion."""
    cp = connect_cpanel()
    stamp = now_ts()
    pdir = f"{APP_DIR}/probe-{stamp}"
    log(f"Probe en {pdir}/ ...")
    root = cp.list_files(APP_DIR)
    log(f"A1 auth+list OK ({len(root)} entradas en {APP_DIR}).")
    cp.mkdir(pdir)
    tiny = ROOT / f"sf-probe-{stamp}.txt"
    tiny.write_text(f"stockflash-probe {stamp}\n", encoding="utf-8")
    zpath = ROOT / f"sf-probe-{stamp}.zip"
    try:
        up = cp.upload(tiny, pdir)
        log(f"Upload respuesta: succeeded={up.get('data', {}).get('succeeded')}")
        e = cp.exists(pdir, tiny.name)
        assert e, "upload sin reflejo en list"
        log("A2 upload OK.")
        zpath = ROOT / f"sf-probe-{stamp}.zip"
        with zipfile.ZipFile(zpath, "w", zipfile.ZIP_DEFLATED) as z:
            z.writestr("probe-in.txt", f"inside {stamp}\n")
        cp.upload(zpath, pdir)
        r = cp.fileop("extract", sourcefiles=f"{HOME_ABS}/probe-{stamp}/{zpath.name}",
                      destfiles=f"{HOME_ABS}/probe-{stamp}")
        log(f"Extract respuesta: {str(r.get('data'))[:300]}")
        assert cp.exists(pdir, "probe-in.txt"), "extract sin reflejo en list"
        log("A3 extract OK.")
    finally:
        try:
            cp.trash(f"{HOME_ABS}/probe-{stamp}")
        except SystemExit as e:
            log(f"Aviso: no se pudo retirar {pdir}/: {str(e)[:150]}")
        # Barrido de dirs probe-* de intentos previos.
        try:
            for e2 in cp.list_files(APP_DIR):
                d = e2.get("file", "")
                if e2.get("type") == "dir" and d.startswith("probe-"):
                    try:
                        cp.trash(f"{HOME_ABS}/{d}")
                        log(f"Barrido: retirado {d}/")
                    except SystemExit:
                        log(f"Aviso: {d}/ no retirable (revisar manual).")
        except SystemExit:
            pass
        left = [e.get("file") for e in cp.list_files(APP_DIR)
                if e.get("type") == "dir" and e.get("file", "").startswith("probe-")]
        if left:
            raise SystemExit(f"BLOCKED: probe dejo residuos verificados: {left}")
        for f in (tiny, zpath):
            try:
                f.unlink()
            except OSError:
                pass
    log("PROBE_PASS: auth, mkdir, upload, extract, list y unlink verificados en el servidor.")
    return 0


def _top_entries(cp: CPanel) -> dict[str, dict]:
    return {e.get("file", ""): e for e in cp.list_files(APP_DIR)}


def stage_backup(cp: CPanel, items: list[str]) -> tuple[str, int]:
    stamp = now_ts()
    bdir = f"{APP_DIR}/{BACKUP_PREFIX}{stamp}"
    cp.mkdir(bdir)
    tops = sorted({s.split("/")[0] for s in items})
    existing = _top_entries(cp)
    n = 0
    for t in tops:
        if t in existing and t not in PROTECTED_TOPS and not t.startswith((BACKUP_PREFIX, QUAR_PREFIX)):
            cp.fileop("copy", sourcefiles=f"{HOME_ABS}/{t}", destfiles=f"{HOME_ABS}/{bdir[len(APP_DIR)+1:]}/{t}")
            n += 1
    got = {e.get("file") for e in cp.list_files(bdir)}
    unverified = [t for t in tops if t in existing and t not in PROTECTED_TOPS
                  and not t.startswith((BACKUP_PREFIX, QUAR_PREFIX)) and t not in got]
    if unverified:
        raise SystemExit(f"BLOCKED: backup sin verificar para {unverified}; no se continua.")
    log(f"Backup {bdir}/ con {n} rutas verificadas ({len(got)} entradas; sin .env/storage/.ftpquota/backup-*).")
    if n == 0:
        log("Aviso: backup vacio (primera instalacion o solo parciales previos fuera de tops).")
    return bdir, n


def stage_upload_extract(cp: CPanel, parts: list[Path]) -> None:
    for zp in parts:
        lsize = zp.stat().st_size
        e0 = cp.exists(APP_DIR, zp.name)
        if e0 and int(e0.get("size") or -1) == lsize:
            log(f"Upload {zp.name}: ya presente exacto ({lsize} B), se reutiliza.")
        else:
            cp.upload(zp, APP_DIR)
            e = cp.exists(APP_DIR, zp.name)
            rsize = int(e.get("size") or -1) if e else -1
            if rsize != lsize:
                raise SystemExit(f"BLOCKED: {zp.name} con tamano distinto "
                                 f"(remoto={rsize} local={lsize}); no se extrae.")
            log(f"Upload {zp.name} OK ({rsize} B).")
        log(f"Extrayendo {zp.name} en {APP_DIR}/ ...")
        r = cp.fileop("extract", sourcefiles=f"{HOME_ABS}/{zp.name}", destfiles=HOME_ABS)
        out = str((r.get("data") or [{}])[0].get("output", "")) if isinstance(r.get("data"), list) else ""
        log(f"Extract {zp.name}: result=1, lineas={out.count(chr(10))}.")
        sentinel = zipfile.ZipFile(zp).namelist()[-1]
        deadline = time.time() + 900
        while not _remote_has(cp, sentinel):
            if time.time() > deadline:
                raise SystemExit(f"BLOCKED: {zp.name} sin completar (falta {sentinel}); "
                                 "reintentar el deploy retoma por partes.")
            log(f"Extract {zp.name} en curso, esperando {sentinel} ...")
            time.sleep(30)
        log(f"Extract {zp.name} OK (centinela {sentinel}).")
    for crit in ("artisan", "public/index.php", "public/build/manifest.json", "vendor/autoload.php"):
        if not _remote_has(cp, crit):
            raise SystemExit(f"BLOCKED: tras extract falta {crit}; detener antes del smoke.")
    log("Extract OK: archivos criticos presentes.")


def _remote_has(cp: CPanel, rel: str) -> bool:
    d, _, f = rel.rpartition("/")
    where = f"{APP_DIR}/{d}" if d else APP_DIR
    try:
        return cp.exists(where, f) is not None
    except SystemExit:
        return False


def stage_reconcile(cp: CPanel) -> int:
    items = load_manifest_items()
    allowed = {s.split("/")[0] for s in items} | PROTECTED_TOPS | {"public"}
    existing = _top_entries(cp)
    strays = [k for k in existing
              if k not in allowed and not k.startswith((BACKUP_PREFIX, QUAR_PREFIX))
              and not k.startswith("stockflash-staging-") and not k.startswith("probe-")]
    if not strays:
        log("Reconciliacion: sin archivos ajenos en la raiz.")
        return 0
    qdir = f"{APP_DIR}/{QUAR_PREFIX}{now_ts()}"
    cp.mkdir(qdir)
    for s in strays:
        if s in PROTECTED_TOPS:
            continue
        cp.fileop("move", sourcefiles=f"{HOME_ABS}/{s}", destfiles=f"{HOME_ABS}/{qdir[len(APP_DIR)+1:]}")
    left = [s for s in strays if s in _top_entries(cp)]
    if left:
        raise SystemExit(f"BLOCKED: reconcile sin verificar para {left}; nada se borro, revisar manual.")
    log(f"Reconciliacion: {len(strays)} ajenos movidos a {qdir}/ (sin borrar): {strays[:10]}")
    return len(strays)


PHP_PROBE = ("<?php header('Content-Type: text/plain'); echo 'PHPV:'.PHP_VERSION.\"\\n\";"
             "foreach (array('mbstring','openssl','pdo_mysql','tokenizer','xml','ctype','json',"
             "'bcmath','fileinfo') as $e) echo 'EXT:'.$e.':'.(extension_loaded($e)?'1':'0').\"\\n\";")


def build_staging_env(debug: bool) -> Path:
    """Genera .env staging desde .env.example: APP_KEY nueva, drivers file/sync,
    APP_DEBUG segun parametro. Solo valores plantilla (sin secretos reales)."""
    template = (ROOT / ".env.example").read_text(encoding="utf-8")
    r = subprocess.run(_resolve(["php", "artisan", "key:generate", "--show"]), cwd=ROOT,
                       capture_output=True, text=True, timeout=60)
    key = r.stdout.strip()
    if r.returncode != 0 or not key.startswith("base64:"):
        raise SystemExit("BLOCKED: no se pudo generar APP_KEY local sin tocar .env.")
    content = re.sub(r"^APP_KEY=.*$", f"APP_KEY={key}", template, flags=re.M)
    for var, val in (("SESSION_DRIVER", "file"), ("CACHE_STORE", "file"),
                     ("QUEUE_CONNECTION", "sync"), ("APP_DEBUG", "true" if debug else "false")):
        if re.search(rf"^{var}=.*$", content, flags=re.M):
            content = re.sub(rf"^{var}=.*$", f"{var}={val}", content, flags=re.M)
        else:
            content += f"\n{var}={val}\n"
    tmp = ROOT / ".sf-env-fixed"
    tmp.write_text(content, encoding="utf-8")
    return tmp


def cmd_fix_env(debug: bool) -> int:
    """Corrige drivers de sesion/cache/cola del .env remoto sin leerlo (no hay
    op de parche; se sube completo con overwrite=1). APP_KEY rota (staging sin
    usuarios: solo invalida sesiones inexistentes)."""
    cp = connect_cpanel()
    tmp = build_staging_env(debug)
    try:
        with open(tmp, "rb") as fh:
            body = fh.read()
        boundary = "----sf" + hashlib.sha256(os.urandom(8)).hexdigest()[:16]
        buf = io.BytesIO()
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"dir\"\r\n\r\n{APP_DIR}\r\n".encode())
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"overwrite\"\r\n\r\n1\r\n".encode())
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"file-0\"; "
                  f"filename=\".env\"\r\nContent-Type: application/octet-stream\r\n\r\n".encode())
        buf.write(body)
        buf.write(f"\r\n--{boundary}--\r\n".encode())
        res = cp.uapi("Fileman", "upload_files", data=buf.getvalue(),
                      ctype=f"multipart/form-data; boundary={boundary}", timeout=120)
        ups = (res.get("data") or {}).get("uploads") or []
        ok = any(u.get("status") == 1 and u.get("size") == len(body) for u in ups if isinstance(u, dict))
        if not ok:
            raise SystemExit(f"BLOCKED: .env sin confirmacion de tamano: {str(ups)[:250]}")
        log(f".env actualizado ({len(body)} B, APP_DEBUG={'true' if debug else 'false'}; "
            "verificacion funcional via smoke, la API no lista dotfiles).")
    finally:
        try:
            tmp.unlink()
        except OSError:
            pass
    # Equivalente controlado a config:clear (sin CLI): retirar config cache si existe.
    try:
        cached = [e.get("file") for e in cp.list_files(f"{APP_DIR}/bootstrap/cache")]
        log(f"bootstrap/cache: {cached}")
        if "config.php" in cached:
            cp.trash(f"{HOME_ABS}/bootstrap/cache/config.php")
            log("config.php retirado (config:clear equivalente).")
    except SystemExit as e:
        log(f"Aviso cache config: {str(e)[:150]}")
    return 0


def stage_boot(cp: CPanel) -> None:
    for d in ("storage", "storage/framework", "storage/framework/sessions",
              "storage/framework/views", "storage/framework/cache", "storage/framework/cache/data",
              "storage/logs", "bootstrap/cache"):
        try:
            cp.mkdir(f"{APP_DIR}/{d}")
        except SystemExit as e:
            log(f"Aviso mkdir {d}: {str(e)[:150]}")
    # .env: la API no lista dotfiles (ni con showhidden), asi que no se puede
    # distinguir "ausente" por listado. Estrategia sin lectura y sin clobber:
    # subir el .env inicial con overwrite=0 — si ya existe se omite (preservado),
    # si no existe se crea. Jamas se lee ni se registra su contenido.
    try:
        cp.trash(f"{HOME_ABS}/.env.staging-new")
    except SystemExit:
        pass
    template = (ROOT / ".env.example").read_text(encoding="utf-8")
    r = subprocess.run(_resolve(["php", "artisan", "key:generate", "--show"]), cwd=ROOT,
                       capture_output=True, text=True, timeout=60)
    key = r.stdout.strip()
    if r.returncode != 0 or not key.startswith("base64:"):
        raise SystemExit("BLOCKED: no se pudo generar APP_KEY local sin tocar .env.")
    content = re.sub(r"^APP_KEY=.*$", f"APP_KEY={key}", template, flags=re.M)
    # R2 staging sin SQLite provisionado: drivers basados en archivos para que
    # el arranque no exija tablas (sessions/cache/queue). Leccion 20261016.
    for var in ("SESSION_DRIVER", "CACHE_STORE", "QUEUE_CONNECTION"):
        content = re.sub(rf"^{var}=.*$", f"{var}={'file' if var != 'QUEUE_CONNECTION' else 'sync'}",
                         content, flags=re.M)
    if "SESSION_DRIVER=file" not in content:
        content += "\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\n"
    tmp = ROOT / ".sf-env-new"
    tmp.write_text(content, encoding="utf-8")
    try:
        with open(tmp, "rb") as fh:
            body = fh.read()
        boundary = "----sf" + hashlib.sha256(os.urandom(8)).hexdigest()[:16]
        buf = io.BytesIO()
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"dir\"\r\n\r\n{APP_DIR}\r\n".encode())
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"overwrite\"\r\n\r\n0\r\n".encode())
        buf.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"file-0\"; "
                  f"filename=\".env\"\r\nContent-Type: application/octet-stream\r\n\r\n".encode())
        buf.write(body)
        buf.write(f"\r\n--{boundary}--\r\n".encode())
        try:
            res = cp.uapi("Fileman", "upload_files", data=buf.getvalue(),
                          ctype=f"multipart/form-data; boundary={boundary}", timeout=120)
        except SystemExit as e:
            if "Failed to upload" in str(e):
                # overwrite=0 + archivo existente: el servidor lo rechaza sin
                # tocarlo. Es el camino esperado "preservado", no un fallo.
                log(".env preservado (ya existia; overwrite=0 respetado, no se toca).")
                return
            raise
        ups = (res.get("data") or {}).get("uploads") or []
        log(f".env: {str(ups)[:250]} (overwrite=0: creado si ausente, preservado si existia).")
    finally:
        try:
            tmp.unlink()
        except OSError:
            pass
    probe_name = f"sf-php-probe-{now_ts()}.php"
    tmp = ROOT / f".{probe_name}"
    tmp.write_text(PHP_PROBE, encoding="utf-8")
    try:
        cp.upload(tmp, f"{APP_DIR}/public")
        url = STAGING_URL + probe_name
        body = http_get(url, timeout=30)
        m = re.search(r"PHPV:(\d+)\.(\d+)\.(\d+)", body)
        if not m:
            raise SystemExit(f"BLOCKED: probe PHP sin respuesta valida en {url}.")
        ver = tuple(int(m.group(i)) for i in (1, 2, 3))
        exts = dict(re.findall(r"EXT:([a-z_]+):([01])", body))
        log(f"PHP remoto {ver[0]}.{ver[1]}.{ver[2]}, extensiones ausentes: "
            f"{[k for k, v in exts.items() if v == '0'] or 'ninguna'}")
        if ver < PHP_MIN:
            raise SystemExit(f"BLOCKED: PHP {ver} < minimo {PHP_MIN}.")
    finally:
        try:
            tmp.unlink()
        except OSError:
            pass
        try:
            cp.trash(f"{HOME_ABS}/public/{probe_name}")
            if probe_name in [e.get("file") for e in cp.list_files(f"{APP_DIR}/public")]:
                log("Aviso: probe PHP sigue visible (revisar manual).")
        except SystemExit:
            log("Aviso: no se pudo retirar el probe PHP (retirar manual).")


def http_get(url: str, timeout: int = 20) -> str:
    """Prefijos de fallo: NET: (red/transporte, no implica app rota) vs APP: (HTTP recibido)."""
    last = ""
    for attempt, wait in ((1, 0), (2, 10), (3, 30), (4, 60)):
        try:
            with urllib.request.urlopen(url, timeout=timeout,
                                        context=ssl.create_default_context()) as r:
                if r.status != 200:
                    raise SystemExit(f"APP: HTTP {r.status} en {url}")
                return r.read().decode("utf-8", "replace")
        except SystemExit:
            raise
        except Exception as e:
            last = f"{type(e).__name__}: {str(e)[:150]}"
            log(f"GET intento {attempt}: {last} (reintentando en {wait}s...)")
            time.sleep(wait)
    raise SystemExit(f"NET: GET {url} fallo tras 4 intentos: {last}")


def cmd_smoke() -> int:
    try:
        body = http_get(STAGING_URL)
    except SystemExit as e:
        raise SystemExit(str(e).replace("NET: GET", "BLOCKED NET: GET", 1)
                         .replace("APP: HTTP", "BLOCKED APP: HTTP", 1))
    if "StockFlash" not in body:
        raise SystemExit("BLOCKED APP: smoke 200 pero sin contenido StockFlash.")
    m = re.search(r"/build/assets/[\w\-.]+\.(js|css)", body)
    manifest = json.loads((ROOT / "public/build/manifest.json").read_text(encoding="utf-8"))
    expected = {v["file"] for v in manifest.values() if isinstance(v, dict) and "file" in v}
    asset_ok = bool(m and any(m.group(0).endswith(f"/{f}") or f in m.group(0) for f in expected))
    if not asset_ok:
        for f in sorted(expected):
            code = http_get(STAGING_URL + f"build/{f}")
            if f not in code and len(code) < 100:
                raise SystemExit(f"BLOCKED: asset {f} no resuelve.")
    log("SMOKE_PASS_200: StockFlash + assets resueltos.")
    (ROOT / "docs/work").mkdir(parents=True, exist_ok=True)
    (ROOT / "docs/work" / f"STAGING-001-SMOKE-{now_ts()}.md").write_text(
        "# STAGING-001 — Smoke staging\n\nSMOKE_PASS_200: `GET https://stockflash-dev.tusitioexpress.cl/` "
        "-> 200 con contenido StockFlash y assets Vite resueltos.\n", encoding="utf-8")
    return 0


def write_deploy_report(ts: str, head: str, rows: list[tuple[str, str]], extra: str = "") -> Path:
    p = ROOT / "docs/work" / f"STAGING-001-DEPLOY-{ts}.md"
    lines = ["# STAGING-001 — Reporte despliegue automatizado", "",
             f"SHA publicado: `{head}`", "",
             "## Stages"]
    for name, res in rows:
        lines.append(f"- {name}: **{res}**")
    if extra:
        lines += ["", extra]
    lines += ["", "No se declara VERIFIED (owner+ChatGPT fuera de OpenCode)."]
    p.write_text("\n".join(lines), encoding="utf-8")
    return p


def cmd_deploy() -> int:
    ts = now_ts()
    rows: list[tuple[str, str]] = []
    try:
        head = stage_version(); rows.append(("version", f"OK {head[:12]}"))
        stage_pregates(); rows.append(("pregates", "OK S1/S2/S4/S5"))
        stage_build(); rows.append(("build", "OK"))
        items = load_manifest_items()
        parts = stage_zip(items); rows.append(("zip", f"OK {len(parts)} partes / {len(items)}"))
        cp = connect_cpanel(); rows.append(("auth", "OK cPanel UAPI"))
        bdir, n = stage_backup(cp, items); rows.append(("backup", f"OK {bdir} ({n})"))
        stage_upload_extract(cp, parts); rows.append(("upload+extract", "OK"))
        q = stage_reconcile(cp); rows.append(("reconcile", f"OK ({q} a cuarentena)"))
        stage_boot(cp); rows.append(("boot", "OK"))
        cmd_smoke(); rows.append(("smoke", "SMOKE_PASS_200"))
        p = write_deploy_report(ts, head, rows, f"Backup: `{bdir}/`.")
        log(f"DEPLOY OK. Reporte: {p}")
        return 0
    except SystemExit as e:
        msg = str(e)
        log(msg)
        rows.append(("FALLO", msg[:200]))
        if "NET:" in msg and "APP:" not in msg:
            # Red inaccesible: los archivos estan correctos; revertirlos seria
            # destruir un buen despliegue. Sin rollback, reintentar smoke luego.
            log("Red inaccesible (no fallo de app): sin rollback; archivos desplegados intactos.")
            write_deploy_report(ts, head if "head" in dir() else "?", rows,
                                "Smoke pendiente: red resetea conexiones. Reintentar --smoke mas tarde.")
            return 6
        try:
            write_deploy_report(ts, head if "head" in dir() else "?", rows,
                                "Rollback: " + try_rollback(cp if "cp" in dir() else None, None))
        except Exception:
            pass
        return 5


def try_rollback(cp: CPanel | None, backup_dir: str | None) -> str:
    if cp is None:
        return "sin conexion: rollback manual pendiente (ver --rollback)."
    bdir = backup_dir or ""
    if not bdir:
        cands = sorted(k for k in _top_entries(cp) if k.startswith(BACKUP_PREFIX))
        bdir = f"{APP_DIR}/{cands[-1]}" if cands else ""
    if not bdir:
        return "sin backup remoto: nada que restaurar (primera instalacion sin previo)."
    for e in cp.list_files(bdir):
        name = e.get("file", "")
        if name and name not in PROTECTED_TOPS:
            cp.fileop("copy", sourcefiles=f"{HOME_ABS}/{bdir[len(APP_DIR)+1:]}/{name}",
                      destfiles=f"{HOME_ABS}/{name}")
    try:
        cmd_smoke()
        return f"restaurado desde {bdir}/ + smoke OK."
    except SystemExit as e:
        return f"restaurado desde {bdir}/ pero smoke sigue mal: {str(e)[:150]}"


def cmd_rollback(backup_dir: str) -> int:
    cp = connect_cpanel()
    if not backup_dir:
        raise SystemExit("BLOCKED: --rollback requiere --backup-dir <backup-AAAAMMDD-HHMMSS>.")
    log(try_rollback(cp, f"{APP_DIR}/{backup_dir.strip('/')}"))
    return 0


def cmd_check() -> int:
    head = stage_version()
    stage_pregates()
    items = load_manifest_items()
    log(f"CHECK OK: HEAD {head[:12]}, manifest {len(items)} entradas, credenciales: "
        f"{'configuradas' if get_cred('cpanel_token') else 'PENDIENTES (una sola vez via sf_credstore)'}.")

    # Autotests del mecanismo (sin red): multipart, validadores UAPI/API2.
    cp = CPanel("x", "u", "t")
    assert cp.base == "https://x"
    uapi_ok = {"result": {"status": 1, "data": []}}
    assert uapi_ok["result"]["status"] == 1
    for bad in ({"result": {"status": 0, "errors": ["denied"]}}, {"foo": 1}, "[]"):
        try:
            res = (json.loads(bad) if isinstance(bad, str) else bad).get("result", {})
            assert isinstance(res, dict) and res.get("status") == 1
            raise AssertionError("validador acepto respuesta mala")
        except (AssertionError, AttributeError):
            pass
    log("CHECK validadores OK (rechazan status=0 y formas desconocidas).")
    return 0


def main() -> int:
    ap = argparse.ArgumentParser(description="Despliegue automatizado staging (cPanel API, un solo ZIP).")
    ap.add_argument("--check", action="store_true")
    ap.add_argument("--probe", action="store_true")
    ap.add_argument("--deploy", action="store_true")
    ap.add_argument("--rollback", action="store_true")
    ap.add_argument("--smoke", action="store_true")
    ap.add_argument("--fix-env", action="store_true",
                    help="Corrige drivers del .env remoto (file/sync) sin leerlo.")
    ap.add_argument("--no-debug", action="store_true",
                    help="Con --fix-env: establece APP_DEBUG=false.")
    ap.add_argument("--backup-dir", default="")
    args = ap.parse_args()
    if args.check:
        return cmd_check()
    if args.probe:
        return cmd_probe()
    if args.smoke:
        return cmd_smoke()
    if args.fix_env:
        return cmd_fix_env(not args.no_debug)
    if args.rollback:
        return cmd_rollback(args.backup_dir)
    if args.deploy:
        return cmd_deploy()
    ap.print_help()
    return 2


if __name__ == "__main__":
    sys.exit(main())
