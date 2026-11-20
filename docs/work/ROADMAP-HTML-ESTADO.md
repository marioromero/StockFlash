# StockFlash — Seguimiento técnico del roadmap HTML

content_owner: ChatGPT (archivo `resources/roadmap/index.html`; autoría 100 % exclusiva)
integration_owner: OpenCode (ruta, control de acceso, pruebas, publicación y este registro técnico)
policy: `docs/harness/ROADMAP-HTML-PROYECTO.md`
roadmap_path: `resources/roadmap/index.html`
route: `/roadmap` (PUBLIC_READ_ONLY; owner decision 2026-11-20; no login or settings.manage; /setup remains protected)
created_on: 2026-11-10
baseline_code_sha: `13cdd6a6f1ae3a986884e8079f81fb34d5f259e4`
roadmap_content_status: INTEGRADA_EN_MAIN (2026-11-20; 265 requisitos en 18 grupos; PR #55)
roadmap_integration_sha: `564b0a583e07355962c2a8a00c5837a645038d34` (ChatGPT detalló 265 requisitos y acceso público, INVTRANSFER-001 incluido)
roadmap_integration_date: 2026-11-20
roadmap_last_review: 2026-11-20
covered_features: BOOT-001 VERIFIED; CI-001 VERIFIED; AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001/002/003 VERIFIED; CORESEQ/CORELIC/COREMOD/COREPIN VERIFIED; CATDB-001 VERIFIED; CATMAST-001 VERIFIED (9279d57); SETUP-001 VERIFIED (13cdd6a); INVCORE-001 VERIFIED (966b685); INVWRITE-001 VERIFIED_TECH (816fdc6); INVKARDEX-001 VERIFIED_TECH (317a61d); INVREORDER-001 VERIFIED_TECH (679e858); INVCSV-001 VERIFIED_TECH (f856592); INVTRANSFER-001 VERIFIED_TECH (3072f54)
in_progress: R6_POS_PREREQUISITE — CUSCORE-001 implementado en código/SQLite (clientes, grupos, descuentos, RUT cifrado/índice ciego y revelado auditado), gates/QA/CI pendientes; R4_BODEGA — INVTRANSFER-001 VERIFIED_TECH (envío/recepción total/parcial); INVCSV-001 VERIFIED_TECH; reservas, POS, caja y licencias comerciales pendientes; producción no demo bloqueada por BKP-08 `NOT_READY_FOR_PRODUCTION`
first_commercial_release: R7 — POS + Bodega sin emisión DTE integrada y con piloto/controles de salida
pending: ROADMAP_PENDIENTE_CHATGPT — CUSCORE-001 (clientes/grupos/descuentos, RUT cifrado e índice ciego, revelado auditado; guía NO redactada); ROADMAP_PENDIENTE_CHATGPT — INSTALL-001 (reconciliación editorial del instalador CORE-17); INVCSV-001 e INVTRANSFER-001 integrados por ChatGPT el 2026-11-20; backlog detallado de v2.2 visible por módulo y estado
report_state: ROADMAP_PENDIENTE_CHATGPT (PR #55, PUBLIC_READ_ONLY, 265 requirements); CUSCORE-001 e INSTALL-001 pendientes editoriales

## Mantenimiento por OpenCode

Actualizar exclusivamente metadatos de integración, cobertura, SHA y pendientes; no el HTML ni el protocolo redactados por ChatGPT. Al cerrar futuras features comparar el estado actual con la cobertura y emitir `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT` en el handoff. Los estados no son porcentajes de avance.

El propietario entrega el cierre de OpenCode a ChatGPT; ChatGPT consulta la evidencia actual, actualiza `resources/roadmap/index.html` cuando corresponda y mantiene su archivo de continuidad.
