# StockFlash — Seguimiento técnico del roadmap HTML

content_owner: ChatGPT (archivo `resources/roadmap/index.html`; autoría 100 % exclusiva)
integration_owner: OpenCode (ruta, control de acceso, pruebas, publicación y este registro técnico)
policy: `docs/harness/ROADMAP-HTML-PROYECTO.md`
roadmap_path: `resources/roadmap/index.html`
route: `/roadmap` (PUBLIC_READ_ONLY; owner decision 2026-11-20; no login or settings.manage; /setup remains protected)
created_on: 2026-11-10
baseline_code_sha: `13cdd6a6f1ae3a986884e8079f81fb34d5f259e4`
roadmap_content_status: INTEGRADA_EN_MAIN (2026-11-23; SELLERS-001 integrado en PR #64, selector R7/completo, 265 requisitos, 23 módulos, 8 etapas)
roadmap_integration_sha: `56c90b49839b69f0c5cad6d160bf7d9cd900d8d3` (ChatGPT integra SELLERS-001: R7 23/169=13,6%, P0 23/104; completo 23/265=8,7%, P0 23/109; PR #64; tasas no ponderadas)
roadmap_integration_date: 2026-11-23
roadmap_last_review: 2026-11-23
covered_features: BOOT-001 VERIFIED; CI-001 VERIFIED; AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001/002/003 VERIFIED; CORESEQ/CORELIC/COREMOD/COREPIN VERIFIED; CATDB-001 VERIFIED; CATMAST-001 VERIFIED (9279d57); SETUP-001 VERIFIED (13cdd6a); INVCORE-001 VERIFIED (966b685); INVWRITE-001 VERIFIED_TECH (816fdc6); INVKARDEX-001 VERIFIED_TECH (317a61d); INVREORDER-001 VERIFIED_TECH (679e858); INVCSV-001 VERIFIED_TECH (f856592); INVTRANSFER-001 VERIFIED_TECH (3072f54); CUSCORE-001 VERIFIED_TECH (b89ed47); SELLERS-001 VERIFIED_TECH (e46f25f)
in_progress: R6_POS_PREREQUISITE — SELLERS-001/CUSCORE-001 VERIFIED_TECH; POSSTATION-001 packet APPROVED (estaciones/dispositivo), todavía sin código. R4_BODEGA reservas pendientes vinculadas a ventas; producción no demo bloqueada por BKP-08 `NOT_READY_FOR_PRODUCTION`
first_commercial_release: R7 — POS + Bodega sin emisión DTE integrada y con piloto/controles de salida
pending: ROADMAP_PENDIENTE_CHATGPT — INSTALL-001 (reconciliación editorial CORE-17); SELLERS-001 integrado en PR #64, 265 requisitos detallados y selector de lanzamiento conservados
report_state: ROADMAP_ACTUALIZADO (PR #64, PUBLIC_READ_ONLY, R7 23/169=13,6%, completo 23/265=8,7%, SEL-01 VERIFIED_TECH); INSTALL-001 pendiente editorial

## Mantenimiento por OpenCode

Actualizar exclusivamente metadatos de integración, cobertura, SHA y pendientes; no el HTML ni el protocolo redactados por ChatGPT. Al cerrar futuras features comparar el estado actual con la cobertura y emitir `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT` en el handoff. Los estados no son porcentajes de avance.

El propietario entrega el cierre de OpenCode a ChatGPT; ChatGPT consulta la evidencia actual, actualiza `resources/roadmap/index.html` cuando corresponda y mantiene su archivo de continuidad.
