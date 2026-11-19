# StockFlash — Seguimiento técnico del roadmap HTML

content_owner: ChatGPT (archivo `resources/roadmap/index.html`; autoría 100 % exclusiva)
integration_owner: OpenCode (ruta, control de acceso, pruebas, publicación y este registro técnico)
policy: `docs/harness/ROADMAP-HTML-PROYECTO.md`
roadmap_path: `resources/roadmap/index.html`
route: `/roadmap` (restringida a Admin Central / `settings.manage`)
created_on: 2026-11-10
baseline_code_sha: `13cdd6a6f1ae3a986884e8079f81fb34d5f259e4`
roadmap_content_status: INTEGRADA_EN_MAIN (2026-11-16)
roadmap_integration_sha: `1ebf0f23fb354e7b5387b24075cd1600ffa39755` (ChatGPT incorporó INVKARDEX-001 a R4)
roadmap_integration_date: 2026-11-19
roadmap_last_review: 2026-11-19
covered_features: BOOT-001 VERIFIED; CI-001 VERIFIED; AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001/002/003 VERIFIED; CORESEQ/CORELIC/COREMOD/COREPIN VERIFIED; CATDB-001 VERIFIED; CATMAST-001 VERIFIED (9279d57); SETUP-001 VERIFIED (13cdd6a); INVCORE-001 VERIFIED (966b685); INVWRITE-001 VERIFIED_TECH (816fdc6); INVKARDEX-001 VERIFIED_TECH (317a61d)
in_progress: R4_BODEGA — INVREORDER-001 implementada en código/pruebas (gates 16/16 PASS, QA APROBADO; publicación/CI pendientes); INVKARDEX-001 VERIFIED_TECH con informe por sucursal/variante/período; INSTALL-001 implementado en código/pruebas; producción no demo bloqueada por BKP-08 `NOT_READY_FOR_PRODUCTION`
first_commercial_release: R7 — POS + Bodega sin emisión DTE integrada y con piloto/controles de salida
pending: ROADMAP_PENDIENTE_CHATGPT — INVREORDER-001 (reposición y stock crítico, implementada en código/pruebas; gates 16/16 PASS, QA APROBADO, publicación/CI pendientes; roadmap/guía pendientes); INSTALL-001 (reconciliación editorial de instalador CORE-17); INVCORE-001/INVWRITE-001/INVKARDEX-001 incorporados por ChatGPT el 2026-11-19; CSV diferido
report_state: ROADMAP_PENDIENTE_CHATGPT (INVREORDER-001); INSTALL-001 pendiente editorial

## Mantenimiento por OpenCode

Actualizar exclusivamente metadatos de integración, cobertura, SHA y pendientes; no el HTML ni el protocolo redactados por ChatGPT. Al cerrar futuras features comparar el estado actual con la cobertura y emitir `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT` en el handoff. Los estados no son porcentajes de avance.

El propietario entrega el cierre de OpenCode a ChatGPT; ChatGPT consulta la evidencia actual, actualiza `resources/roadmap/index.html` cuando corresponda y mantiene su archivo de continuidad.
