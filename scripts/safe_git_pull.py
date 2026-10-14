#!/usr/bin/env python3
"""Sincroniza main al iniciar tareas de OpenCode. No resetea ni limpia trabajo local."""
from pathlib import Path
import os
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]


def git(*args):
    return subprocess.run(
        ["git", *args],
        cwd=ROOT,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        check=False,
        env={**os.environ, "GIT_TERMINAL_PROMPT": "0"},
    )


branch = git("branch", "--show-current")
if branch.returncode != 0 or branch.stdout.strip() != "main":
    print("SYNC_BLOCKED: sincronizacion automatica solo en rama main.")
    sys.exit(2)

remote = git("remote", "get-url", "origin")
if remote.returncode != 0:
    print("SYNC_BLOCKED: remoto origin no disponible.")
    sys.exit(2)

# --ff-only impide merges automáticos y git protege cambios locales en conflicto.
# Nunca usar reset, checkout -f, clean, stash ni sobreescrituras.
result = git("pull", "--ff-only", "origin", "main")
if result.returncode != 0:
    print("SYNC_BLOCKED: Git no pudo hacer fast-forward seguro; NO se descartaron cambios locales.")
    print((result.stderr or result.stdout).strip()[:1800])
    sys.exit(2)

print("SYNC_OK: repositorio main sincronizado mediante git pull --ff-only origin main.")
if result.stdout.strip():
    print(result.stdout.strip()[:1000])
