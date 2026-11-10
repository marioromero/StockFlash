# StockFlash — Seguimiento técnico del roadmap HTML

content_owner: ChatGPT (archivo `resources/roadmap/index.html`; autoría 100 % exclusiva)
integration_owner: OpenCode (ruta, control de acceso, pruebas, publicación y este registro técnico)
policy: `docs/harness/ROADMAP-HTML-PROYECTO.md`
roadmap_path: `resources/roadmap/index.html`
route: `/roadmap` (restringida a Admin Central / `settings.manage`)
created_on: 2026-11-10
baseline_code_sha: `90254e59bf61bc0252a579c076886fc500eb0119`
roadmap_content_status: CHATGPT_REDACTADO_PR_3_PENDIENTE_DE_INTEGRACION
roadmap_integration_sha: PENDIENTE
roadmap_last_review: 2026-11-10
covered_features: BOOT-001 VERIFIED; CI-001 VERIFIED; AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 publicado/QA/CI OK, pendiente VERIFIED externo
in_progress: R3_CORE — USERS-001 DRAFT, no implementado
first_commercial_release: R7 — POS + Bodega sin emisión DTE integrada y con piloto/controles de salida
pending: Integrar PR #3; implementar `GET /roadmap` con permiso `settings.manage`; enlace de Dashboard «Avance del proyecto» restringido a administradores; QA/CI/registro de integración
report_state: ROADMAP_PENDIENTE_CHATGPT (únicamente cuando cambien hitos, funcionalidades o decisiones respecto del último contenido integrado)

## Mantenimiento por OpenCode

Actualizar exclusivamente metadatos de integración, cobertura, SHA y pendientes; no el HTML ni el protocolo redactados por ChatGPT. Al cerrar futuras features comparar el estado actual con la cobertura y emitir `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT` en el handoff. Los estados no son porcentajes de avance.

El propietario entrega el cierre de OpenCode a ChatGPT; ChatGPT consulta la evidencia actual, actualiza `resources/roadmap/index.html` cuando corresponda y mantiene su archivo de continuidad.
