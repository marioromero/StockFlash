---
description: Publicar Git automáticamente tras gates y QA de feature aprobada, nunca deploy
agent: stockflash-publisher
---

**Sincronización de inicio (una sola vez, sin descartar trabajo local):**

!`py -3 scripts/safe_git_pull.py`

Si la salida empieza con `SYNC_BLOCKED` o falta `SYNC_OK`, detener y solicitar revisión del estado Git. No usar reset/stash/clean.

Dos operaciones permanentes (no ampliar permisos; OpenCode no decide VERIFIED):

**A. Publicación ordinaria** — Feature Packet $ARGUMENTS (p. ej. `BOOT-001`) solo si:
- Política de aprobación única (2026-11-13): feature `APPROVED` y activa autoriza publicación en GitHub tras gates y QA sin autorización separada. Default `AUTO_GIT` aunque el campo falte; `OWNER_GATE` expresamente definido por el propietario exige autorización separada (nunca inferirla de `ok`).
- `docs/work/features/$ARGUMENTS.md` tiene `status: APPROVED` y `docs/work/CURRENT.md` lo mantiene activo.
- Existe veredicto reviewer `APROBADO` (cero bloqueantes o bloqueantes resueltos) y reporte `docs/work/$ARGUMENTS-VERIFY-*.md` con `GATES_PASS` ejecutado para el estado exacto a publicar.
- Flujo respetado: `/sf-feature → /sf-verify → /sf-review → /sf-publish`. No publicar tras edición aislada sin gates+review.

**B. Publicación de cierre documental** — publicar el dictamen técnico VERIFIED de ChatGPT sobre una feature previamente aprobada por el propietario, sin pedir una segunda firma, solo si:
- `docs/work/features/$ARGUMENTS.md` tiene `status: VERIFIED` con fecha, dictamen externo ChatGPT y referencias SHA/gates/QA/CI y límites de pruebas.
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
8. Entregar archivos publicados/excluidos, gates+QA, SHA y URL `https://github.com/marioromero/StockFlash/commit/<SHA>`, coincidencia `origin/main`, estado `publicado pendiente VERIFIED` (A) o `VERIFIED registrado` (B). GitHub Actions se activa tras push a main: comprobar CI de ese SHA si es accesible y reportar SUCCESS/FAILURE/PENDING; un fallo de CI bloquea VERIFIED y retorna a ciclo de corrección vía SFH1 sin nueva aprobación Git. Publicar no es despliegue ni VERIFIED.

Autorización del propietario 2026-10-16: sin aprobaciones por `bash`/`edit`; ejecutar directo. Decisión permanente 2026-11-13: sin aprobación de Git repetida por feature `APPROVED`; no amplía autorización de deploy ni BD remota. Ante archivos sospechosos, conflictos, cambios fuera de alcance o gates fallidos, detener publicación y reportar bloqueo.

**Estado documental al informar entrega:** revisar `docs/work/GUIA-HTML-ESTADO.md`; incluir `GUIA_ACTUALIZADA` solo si la guía escrita por ChatGPT está integrada y cubre el código publicado. En caso contrario, indicar `GUIA_PENDIENTE_CHATGPT` con SHA/feature y recomendar al propietario llevar el reporte a ChatGPT. El publicador no redacta el manual ni salta gates/QA; esta verificación informativa no bloquea la publicación del código.

**Roadmap al publicar:** contrastar cambios de la feature con `docs/work/ROADMAP-HTML-ESTADO.md` y reportar `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT`. No escribir ni reescribir `resources/roadmap/index.html`; solo integrar versiones entregadas por ChatGPT y actualizar la bitácora técnica. No alterar condiciones de gates ni bloqueo de publicación.
