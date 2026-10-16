#!/usr/bin/env python3
"""Credenciales locales StockFlash fuera de Git (Windows DPAPI, sin dependencias).

Guarda secretos cifrados con DPAPI de usuario en %LOCALAPPDATA%\\StockFlash\\creds\\,
nunca en el repositorio, argumentos visibles ni logs. Cifrado/descifrado via
ctypes (CryptProtectData/CryptUnprotectData); no requiere modulos externos.

Uso:
    py -3 scripts/sf_credstore.py set <nombre>     # pide el valor por prompt (no se muestra)
    py -3 scripts/sf_credstore.py get <nombre>     # imprime el valor (usar solo redirigido a otro proceso)
    py -3 scripts/sf_credstore.py list             # lista nombres (nunca valores)
    py -3 scripts/sf_credstore.py delete <nombre>

Nombres usados por el despliegue: cpanel_user, cpanel_token, cpanel_host.
"""
from __future__ import annotations

import ctypes
import ctypes.wintypes as wt
import getpass
import os
import sys
from pathlib import Path

STORE = Path(os.environ.get("LOCALAPPDATA", str(Path.home()))) / "StockFlash" / "creds"


class DATA_BLOB(ctypes.Structure):
    _fields_ = [("cbData", wt.DWORD), ("pbData", ctypes.POINTER(ctypes.c_byte))]


_crypt32 = ctypes.windll.crypt32
_kernel32 = ctypes.windll.kernel32


def _protect(raw: bytes) -> bytes:
    blob_in = DATA_BLOB(len(raw), ctypes.cast(ctypes.create_string_buffer(raw), ctypes.POINTER(ctypes.c_byte)))
    blob_out = DATA_BLOB()
    if not _crypt32.CryptProtectData(ctypes.byref(blob_in), None, None, None, None, 0, ctypes.byref(blob_out)):
        raise OSError("CryptProtectData fallo")
    try:
        return bytes((ctypes.c_byte * blob_out.cbData).from_address(ctypes.addressof(blob_out.pbData.contents)))
    finally:
        _kernel32.LocalFree(blob_out.pbData)


def _unprotect(enc: bytes) -> bytes:
    blob_in = DATA_BLOB(len(enc), ctypes.cast(ctypes.create_string_buffer(enc), ctypes.POINTER(ctypes.c_byte)))
    blob_out = DATA_BLOB()
    if not _crypt32.CryptUnprotectData(ctypes.byref(blob_in), None, None, None, None, 0, ctypes.byref(blob_out)):
        raise OSError("CryptUnprotectData fallo (¿otro usuario Windows?)")
    try:
        return bytes((ctypes.c_byte * blob_out.cbData).from_address(ctypes.addressof(blob_out.pbData.contents)))
    finally:
        _kernel32.LocalFree(blob_out.pbData)


def _path(name: str) -> Path:
    safe = "".join(c for c in name if c.isalnum() or c in "-_").strip()
    if not safe or safe != name:
        raise SystemExit(f"Nombre invalido: {name!r} (solo [a-zA-Z0-9_-]).")
    return STORE / f"{safe}.dpapi"


def read_secret(name: str) -> str | None:
    """Lee un secreto del almacen (uso interno del despliegue). Retorna None si ausente."""
    p = _path(name)
    if not p.exists():
        return None
    import base64
    return _unprotect(base64.b64decode(p.read_bytes())).decode("utf-8")


def cmd_set(name: str) -> int:
    if not sys.stdin.isatty():
        print("BLOCKED: set requiere terminal interactiva (el valor no se pasa por argumentos).")
        return 2
    STORE.mkdir(parents=True, exist_ok=True)
    val = getpass.getpass(f"Valor para {name} (no se muestra ni queda en historial): ")
    if not val:
        print("BLOCKED: valor vacio, no se guarda.")
        return 2
    import base64
    _path(name).write_bytes(base64.b64encode(_protect(val.encode("utf-8"))))
    del val
    try:
        os.chmod(_path(name), 0o600)
    except Exception:
        pass
    print(f"OK: {name} guardado en DPAPI usuario (fuera de Git).")
    return 0


def cmd_get(name: str) -> int:
    val = read_secret(name)
    if val is None:
        print(f"BLOCKED: {name} no configurado. Usar: py -3 scripts/sf_credstore.py set {name}")
        return 2
    sys.stdout.write(val)
    if not val.endswith("\n"):
        sys.stdout.write("\n")
    return 0


def cmd_list() -> int:
    if not STORE.exists():
        print("(vacio: sin credenciales configuradas)")
        return 0
    for f in sorted(STORE.glob("*.dpapi")):
        print(f.stem)
    return 0


def cmd_delete(name: str) -> int:
    p = _path(name)
    if not p.exists():
        print(f"(ausente: {name})")
        return 0
    p.unlink()
    print(f"OK: {name} eliminado.")
    return 0


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 2
    action = sys.argv[1]
    if action == "set" and len(sys.argv) == 3:
        return cmd_set(sys.argv[2])
    if action == "get" and len(sys.argv) == 3:
        return cmd_get(sys.argv[2])
    if action == "list":
        return cmd_list()
    if action == "delete" and len(sys.argv) == 3:
        return cmd_delete(sys.argv[2])
    print(__doc__)
    return 2


if __name__ == "__main__":
    sys.exit(main())
