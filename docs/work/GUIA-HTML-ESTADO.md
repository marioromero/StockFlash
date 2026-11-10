# StockFlash — Seguimiento de guía HTML de usuario

content_owner: ChatGPT (exclusivo para textos explicativos de `public/docs/index.html`)
integration_owner: OpenCode (validación, enlace /docs/, publicación y reportes)
policy: `docs/harness/GUIA-HTML-USUARIO.md`
guide_path: `public/docs/index.html`
guide_url: `/docs/`
guide_branch: `docs/guia-usuario-interactiva-20261110`
guide_pr: https://github.com/marioromero/StockFlash/pull/2
guide_status: INTEGRADA_EN_MAIN (2026-11-10)
guide_content_last_written: 2026-11-10
covered_features: AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED (2026-11-10)
latest_covered_code_sha: `90254e59bf61bc0252a579c076886fc500eb0119` (DOCS-001 publicado; AUTH/BRANCH previos)
next_feature: USERS-001 DRAFT (NO documentar como disponible)
last_guide_integration_sha: `07eb72ee8432e84f8aa8cd6fbf5caeb66fdf4829` (head del PR #3; actualización de Ayuda integrada en main)
last_guide_integration_date: 2026-11-10
pending_docs: Dashboard «Avance del proyecto» → `/roadmap` (ChatGPT debe añadir la referencia en «Conocer el panel principal»). El contexto de continuidad sigue siendo mantenido exclusivamente por ChatGPT.
pending_since: 2026-11-10

## Cómo mantener este archivo
- OpenCode puede actualizar solamente los metadatos y el registro de pendientes aquí, no los textos para usuarios en `public/docs/index.html`.
- Al completar/publicar una nueva feature: si ChatGPT aún no redactó su nueva guía, registrar `GUIA_PENDIENTE_CHATGPT` con feature, SHA y fecha, más enlaces al packet/QA en el handoff.
- Después de que ChatGPT redacte y OpenCode integre/publica la guía, actualizar la cobertura y el SHA, marcando `GUIA_ACTUALIZADA`.
- Si cambia la rama principal o existe otro PR abierto, verificar HEAD antes de editar; no sobrescribir trabajo de otros agentes.
- La guía no se actualiza sin una solicitud explícita al chat: al usuario le corresponde traer el cierre de OpenCode, y ChatGPT verifica el código real mediante GitHub.
