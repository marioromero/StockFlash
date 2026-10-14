---
description: Coordina una Feature Packet aprobada de punta a punta con una sola instrucción (v1)
agent: stockflash-orchestrator
---

**Sincronización de inicio (una sola vez, sin descartar trabajo local):**

!`py -3 scripts/safe_git_pull.py`

Si la salida empieza con `SYNC_BLOCKED` o falta `SYNC_OK`, detener y solicitar revisión del estado Git. No usar reset/stash/clean.

**Nota de versión (anti-caché):** este comando es `sf-run v1` (coordinador + operaciones A/B de `/sf-publish`). Antes de coordinar, el agente debe confirmar que el procedimiento visible coincide con este archivo; ante discrepancia, informar y pedir reinicio de OpenCode una sola vez (los `.opencode/commands/` se registran al iniciar sesión).

Coordinar Feature Packet $ARGUMENTS con el agente `stockflash-orchestrator`:

1. Validar `status: APPROVED` en `docs/work/features/$ARGUMENTS.md` y activo en `docs/work/CURRENT.md`. Ante DRAFT/VERIFIED-otro/inactivo, detenerse sin cambios (p. ej. BRAND-001 DRAFT se rechaza, no se implementa).
2. Delegación nativa por Task a `stockflash-implementer` → gates `py -3 scripts/sf_verify.py $ARGUMENTS` (`GATES_BY_PACKET`) → `stockflash-reviewer` (artefacto `REVIEW-*.md` atribuible) → `stockflash-publisher` (operación A). El coordinador no implementa, revisa, publica ni auto-aprueba; no declara VERIFIED.
3. Reintentos automáticos ante defectos corregibles en alcance: máximo 3 ciclos implementador→gates→review; al exceder, diagnóstico + pendientes con trabajo conservado.
4. Checkpoints `docs/work/.run/$ARGUMENTS.json` (ignorado en Git) con fingerprints `git hash-object`; al reanudar, revalidar y repetir desde el último stage válido.
5. Solo se detiene ante decisiones contractuales, visuales, de seguridad o permisos del propietario. Cierre documental VERIFIED usa `/sf-publish` operación B, no `/sf-run`.

Respetar permisos `ask` mediante el mecanismo real; no interpretar `ask` como bloqueo si el propietario autoriza. Ante `deny` administrativo, entregar bloqueo + parche sin eludir.

**Higiene de permisos:** priorizar herramientas nativas de archivo y comandos únicos; evitar pipelines PowerShell que disparan aprobaciones por subcomando. Checkpoints mediante edición permitida, nunca borrado destructivo. No invocar auto-approve con credenciales o secretos accesibles.
