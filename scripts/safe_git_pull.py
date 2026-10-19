#!/usr/bin/env python3
"""Sincroniza main con el remoto SOLO cuando es necesario y seguro.

Regla local-first: todo desarrollo se ejecuta localmente; GitHub es el
repositorio de código, no el servidor de ejecución. Los commits/push no
autorizan despliegue. Esta script nunca descarta, resetea, hace stash ni
sobreescribe trabajo local.

Flujo:
1. `git fetch origin main` (solo actualiza refs remotos, no toca el árbol).
2. Compara HEAD local con origin/main tras el fetch.
   - Iguales: SYNC_OK, sin pull.
   - Local adelante (commits sin push): SYNC_OK, no pull (los commits locales
     esperan instrucción del propietario; no se resuelven con pull).
   - Remoto adelante y árbol limpio: `git pull --ff-only origin main`, SYNC_OK.
   - Remoto adelante y árbol sucio, o ramas divergentes: SYNC_BLOCKED, sin
     descartar nada.
"""
from pathlib import Path
import os
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]


def git(*args, check=False):
    return subprocess.run(
        ["git", *args],
        cwd=ROOT,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        check=check,
        env={**os.environ, "GIT_TERMINAL_PROMPT": "0"},
    )


def is_ancestor(ancestor: str, descendant: str) -> bool:
    r = git("merge-base", "--is-ancestor", ancestor, descendant)
    return r.returncode == 0


branch = git("branch", "--show-current")
if branch.returncode != 0 or branch.stdout.strip() != "main":
    print("SYNC_BLOCKED: sincronizacion automatica solo en rama main.")
    sys.exit(2)

remote = git("remote", "get-url", "origin")
if remote.returncode != 0:
    print("SYNC_BLOCKED: remoto origin no disponible.")
    sys.exit(2)

# Fetch solo actualiza referencia remota; nunca toca el árbol de trabajo.
fetch = git("fetch", "origin", "main")
if fetch.returncode != 0:
    print("SYNC_BLOCKED: no se pudo consultar el remoto origin/main.")
    print((fetch.stderr or fetch.stdout).strip()[:1800])
    sys.exit(2)

local = git("rev-parse", "HEAD").stdout.strip()
remote_sha = git("rev-parse", "origin/main").stdout.strip()

if not local or not remote_sha:
    print("SYNC_BLOCKED: no se pudieron resolver HEAD o origin/main.")
    sys.exit(2)

if local == remote_sha:
    print("SYNC_OK: repositorio main ya sincronizado con origin/main (sin pull).")
    sys.exit(0)

if is_ancestor(remote_sha, local):
    # Local adelante: commits pendientes de push. No hacer pull.
    print("SYNC_OK: commits locales pendientes de push; no se ejecuta pull (esperan instruccion del propietario).")
    sys.exit(0)

# Remoto adelante de local. Solo fast-forward si el arbol esta limpio.
status = git("status", "--porcelain")
if status.stdout.strip():
    print("SYNC_BLOCKED: arbol de trabajo con cambios sin commit; no se descarta ni sobrescribe nada.")
    sys.exit(2)

result = git("pull", "--ff-only", "origin", "main")
if result.returncode != 0:
    print("SYNC_BLOCKED: Git no pudo hacer fast-forward seguro; NO se descartaron cambios locales.")
    print((result.stderr or result.stdout).strip()[:1800])
    sys.exit(2)

print("SYNC_OK: repositorio main sincronizado mediante git pull --ff-only origin main.")
if result.stdout.strip():
    print(result.stdout.strip()[:1000])
