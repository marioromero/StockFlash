---
description: Publicar cambios aprobados en GitHub con publicador controlado (post gates + review)
agent: stockflash-publisher
---

**Sincronización de inicio (una sola vez, sin descartar trabajo local):**

!`py -3 scripts/safe_git_pull.py`

Si la salida empieza con `SYNC_BLOCKED` o falta `SYNC_OK`, detener y solicitar revisión del estado Git. No usar reset/stash/clean.

Dos operaciones permanentes (no ampliar permisos, no declarar VERIFIED automáticamente):

**A. Publicación ordinaria** — Feature Packet $ARGUMENTS (p. ej. `BOOT-001`) solo si:
- `docs/work/features/$ARGUMENTS.md` tiene `status: APPROVED` y `docs/work/CURRENT.md` lo mantiene activo.
- Existe veredicto reviewer `APROBADO` (cero bloqueantes o bloqueantes resueltos) y reporte `docs/work/$ARGUMENTS-VERIFY-*.md` con `GATES_PASS` ejecutado para el estado exacto a publicar.
- Flujo respetado: `/sf-feature → /sf-verify → /sf-review → /sf-publish`. No publicar tras edición aislada sin gates+review.

**B. Publicación de cierre documental** — registrar decisión VERIFIED externa ya documentada, solo si:
- `docs/work/features/$ARGUMENTS.md` tiene `status: VERIFIED` con fecha, decisión externa y referencias de evidencia.
- Implementación previamente publicada (SHA en `origin/main` verificable).
- Gates y QA originales verificables (`VERIFY-*.md` GATES_PASS + `REVIEW-*.md` APROBADO existentes).
- Alcance exclusivamente documental: estado, evidencias y registros de cierre autorizados. Sin código, dependencias, configuración funcional ni contratos normativos.
- Sin repetir gates funcionales cuando el cambio es exclusivamente documental (verificada ausencia de cambios funcionales vía `git diff`).
- Este flujo NO declara VERIFIED ni sustituye QA de código; solo publica el registro de una decisión externa. No alterar estados para engañar gates.

**Procedimiento (delegado al agente `stockflash-publisher`, sin implementar ni corregir ni auto-aprobar):**

1. Validar repo/rama/upstream/sync (main, origin/main, `git fetch origin main`, ff-only, sin divergencia incompatible).
2. Inventariar alcance exacto con `git status --porcelain=v1 --untracked-files=all`, `git diff --stat HEAD`, `git ls-files --others --exclude-standard`. Exclusiones fijas: `docs/work/features/BRAND-001.md` (DRAFT), `tmp-boot-001/`, secretos, `vendor/`, `node_modules/`, `*.sqlite`, `public/build/`, runtime/logs. No borrar temporales sin autorización. Preservar cambios reales del propietario en `docs/work/CURRENT.md` (no sobrescribir).
3. Pre-commit BOOT-001: comprobar `<html lang>` es `es-CL` en respuesta/servidor, no `en`. Ante `en`, detenerse (el publisher no corrige código; el fix exige implementador + repetir gates y QA).
4. Fingerprints: `git hash-object` del conjunto revisado vs staged; `git diff --cached --check` + inventario staged + `git check-ignore`. Ante cambios post-aprobación, detener y exigir re-verify/re-review.
5. Si el harness o el código cambió desde el último `GATES_PASS`, re-ejecutar `py -3 scripts/sf_verify.py $ARGUMENTS` antes de publicar. Mecanismo extensible por `GATES_BY_PACKET`; sin duplicar scripts ni lógica por feature.
6. Staging explícito por archivo (`git add -- <ruta>`), nunca `git add -A`. Commits descriptivos por coherencia (misma rama, sin vacíos).
7. Solo `git push origin main` sin variantes. Confirmar `git rev-parse HEAD` = `git rev-parse origin/main` + `git ls-remote origin main`.
8. Entregar: archivos publicados/excluidos, permisos efectivos, gates+QA, SHA + enlace `https://github.com/marioromero/StockFlash/commit/<SHA>`, coincidencia `origin/main`, estado según operación (`publicado, pendiente de VERIFIED` en A; `VERIFIED registrado` en B). Flujo permanente por feature: `Implementación → sf-verify → sf-review → sf-publish → comprobación GitHub`, más cierre documental autorizado. No declarar VERIFIED ni desplegar.

Respetar permisos `ask`: cada `bash`/`edit` muestra confirmación en OpenCode; no interpretar `ask` como bloqueo, continuar si el propietario autoriza. Ante archivos sospechosos, conflictos, cambios fuera de alcance o gates fallidos, detener publicación y reportar bloqueo.
