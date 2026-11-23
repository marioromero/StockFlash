# StockFlash — Seguimiento técnico del roadmap HTML

content_owner: ChatGPT (archivo `resources/roadmap/index.html`; autoría 100 % exclusiva)
integration_owner: OpenCode (ruta, control de acceso, pruebas, publicación y este registro técnico)
policy: `docs/harness/ROADMAP-HTML-PROYECTO.md`
roadmap_path: `resources/roadmap/index.html`
route: `/roadmap` (PUBLIC_READ_ONLY; owner decision 2026-11-20; no login or settings.manage; /setup remains protected)
created_on: 2026-11-10
baseline_code_sha: `13cdd6a6f1ae3a986884e8079f81fb34d5f259e4`
roadmap_content_status: INTEGRADA_EN_MAIN (2026-11-23; CUSCORE-001 reflectado en PR #60, selector R7/completo, 265 requisitos, 23 módulos, 8 etapas)
roadmap_integration_sha: `1798380616e79c33b9f929226827377fe062c533` (ChatGPT integra CUSCORE-001: R7 22/169=13,0%, P0 22/104; completo 22/265=8,3%, P0 22/109; PR #60; tasas no ponderadas)
roadmap_integration_date: 2026-11-23
roadmap_last_review: 2026-11-23
covered_features: BOOT-001 VERIFIED; CI-001 VERIFIED; AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001/002/003 VERIFIED; CORESEQ/CORELIC/COREMOD/COREPIN VERIFIED; CATDB-001 VERIFIED; CATMAST-001 VERIFIED (9279d57); SETUP-001 VERIFIED (13cdd6a); INVCORE-001 VERIFIED (966b685); INVWRITE-001 VERIFIED_TECH (816fdc6); INVKARDEX-001 VERIFIED_TECH (317a61d); INVREORDER-001 VERIFIED_TECH (679e858); INVCSV-001 VERIFIED_TECH (f856592); INVTRANSFER-001 VERIFIED_TECH (3072f54); CUSCORE-001 VERIFIED_TECH (b89ed47)
in_progress: R6_POS_PREREQUISITE — CUSCORE-001 VERIFIED_TECH; SELLERS-001 siguiente P0 (registro de vendedores); R4_BODEGA transfer/CSV VERIFIED_TECH, reservas ligadas a ventas pendientes; producción no demo bloqueada por BKP-08 `NOT_READY_FOR_PRODUCTION`
first_commercial_release: R7 — POS + Bodega sin emisión DTE integrada y con piloto/controles de salida
pending: ROADMAP_PENDIENTE_CHATGPT — INSTALL-001 (reconciliación editorial CORE-17); CUSCORE-001 integrado 2026-11-23 en R6 y porcentajes; 265 requisitos detallados conservados
report_state: ROADMAP_ACTUALIZADO (PR #60, PUBLIC_READ_ONLY, default R7/full selector, CUSCORE VERIFIED_TECH and ratios updated); INSTALL-001 pendiente editorial

## Mantenimiento por OpenCode

Actualizar exclusivamente metadatos de integración, cobertura, SHA y pendientes; no el HTML ni el protocolo redactados por ChatGPT. Al cerrar futuras features comparar el estado actual con la cobertura y emitir `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT` en el handoff. Los estados no son porcentajes de avance.

El propietario entrega el cierre de OpenCode a ChatGPT; ChatGPT consulta la evidencia actual, actualiza `resources/roadmap/index.html` cuando corresponda y mantiene su archivo de continuidad.
