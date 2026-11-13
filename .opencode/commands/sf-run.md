---
description: Ejecuta una Feature Packet aprobada y publica Git tras gates y QA
agent: stockflash-orchestrator
---

**Sincronización de inicio (una sola vez, sin descartar trabajo local):**

!`py -3 scripts/safe_git_pull.py`

Si la salida empieza con `SYNC_BLOCKED` o falta `SYNC_OK`, detener y solicitar revisión del estado Git. No usar reset/stash/clean.

**Nota de versión (anti-caché):** este comando es `sf-run v2` (publicación Git autónoma tras gates+QA, salvo OWNER_GATE explícito). Antes de coordinar, el agente debe confirmar que el procedimiento visible coincide con este archivo; ante discrepancia, informar y pedir reinicio de OpenCode una sola vez (los `.opencode/commands/` se registran al iniciar sesión).

Coordinar Feature Packet $ARGUMENTS con el agente `stockflash-orchestrator`:

1. Validar `status: APPROVED` en `docs/work/features/$ARGUMENTS.md` y activo en `docs/work/CURRENT.md`. Ante DRAFT/VERIFIED-otro/inactivo, detenerse sin cambios (p. ej. BRAND-001 DRAFT se rechaza, no se implementa).
2. Delegación Task a `stockflash-implementer` → gates `py -3 scripts/sf_verify.py $ARGUMENTS` → `stockflash-reviewer` independiente → `stockflash-publisher` (operación A **automática** tras `GATES_PASS` y QA `APROBADO` del mismo estado). La aprobación de la feature cubre Git commit/push, sin permiso adicional. `AUTO_GIT` es default también si falta el campo; solo `OWNER_GATE` expresamente definido impide publicar. Orquestador nunca implementa, revisa, publica por sí mismo ni declara VERIFIED.
3. Reintentos automáticos ante defectos corregibles en alcance: máximo 3 ciclos implementador→gates→review; al exceder, diagnóstico + pendientes con trabajo conservado.
4. Checkpoints `docs/work/.run/$ARGUMENTS.json` (ignorado en Git) con fingerprints `git hash-object`; al reanudar, revalidar y repetir desde el último stage válido.
5. Después del push: devolver SHA+URL, gate, QA y CI verificada por SHA (`SUCCESS`/`FAILURE`/`PENDING`). CI pendiente no significa éxito. Error de CI o `.md` de revisión ChatGPT -> corregir dentro de la misma feature al siguiente `ok`, repetir gates+QA y publicar nuevo SHA automáticamente. Revertir solo mediante commit selectivo revisado, sin force. Detener ante nuevas decisiones contractuales/de seguridad fuera de alcance, permisos, despliegue o BD remota. VERIFIED sigue siendo decisión externa.

Respetar permisos `deny` administrativos con bloqueo + parche. Autorización del propietario 2026-10-16: sin aprobaciones `ask`; ejecutar directo.

**Higiene de permisos:** priorizar herramientas nativas de archivo y comandos únicos; evitar pipelines PowerShell que disparan aprobaciones por subcomando. Checkpoints mediante edición permitida, nunca borrado destructivo. No invocar auto-approve con credenciales o secretos accesibles.

**Handoff de manual HTML (automático al final del ciclo):** consultar `docs/harness/GUIA-HTML-USUARIO.md` y `docs/work/GUIA-HTML-ESTADO.md`. Al terminar de implementar/publicar una feature con cambios visibles al usuario, comprobar cobertura de `public/docs/index.html`. Si falta contenido redactado por ChatGPT, incluir `GUIA_PENDIENTE_CHATGPT` en el cierre con SHA, nuevas pantallas/campos y referencias para el propietario. OpenCode NO genera los textos del manual. No detener la publicación de código por depender de un mensaje humano a ChatGPT.


**Contexto para nueva sesión del propietario:** `docs/harness/CONTEXTO-CHATGPT.md` pertenece exclusivamente a ChatGPT. OpenCode puede leerlo, no editarlo. Al terminar, suministrar los hechos nuevos en el handoff para su actualización por ChatGPT cuando el propietario comparta el informe.

**Seguimiento roadmap al cierre:** consultar `docs/harness/ROADMAP-HTML-PROYECTO.md` y `docs/work/ROADMAP-HTML-ESTADO.md`. Si la feature cambia hitos, capacidades listas o la ruta al primer lanzamiento, incluir `ROADMAP_PENDIENTE_CHATGPT` (SHA, QA/CI, Feature Packet, cambio verificable). OpenCode no edita `resources/roadmap/index.html`; es de ChatGPT. No bloquear entrega de código por esta actualización editorial externa.
