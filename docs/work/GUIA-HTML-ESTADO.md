# StockFlash — Seguimiento de guía HTML de usuario

content_owner: ChatGPT (exclusivo para textos explicativos de `public/docs/index.html`)
integration_owner: OpenCode (validación, enlace /docs/, publicación y reportes)
policy: `docs/harness/GUIA-HTML-USUARIO.md`
guide_path: `public/docs/index.html`
guide_url: `/docs/`
guide_branch: `docs/guia-usuario-interactiva-20261110`
guide_pr: https://github.com/marioromero/StockFlash/pull/2
guide_status: INTEGRADA_EN_MAIN (2026-11-16)
guide_content_last_written: 2026-11-16
covered_features: AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED (2026-11-10); ROADMAP-001 VERIFIED (2026-11-10); USERS-001 VERIFIED técnico 2026-11-16 (código 2aec038)
latest_covered_code_sha: `2aec03893c78bc7180544930e0b6f741599df9ed` (USERS-001 corregido; DOCS/AUTH/BRANCH/ROADMAP previos)
next_feature: NINGUNO (USERS-001 documentada y VERIFIED técnico; siguiente feature por definir)
last_guide_integration_sha: `79c3b0f5af573b956cbcfb8c95c50aef251f5bd5` (commit de ChatGPT que amplió el manual a 19 temas)
last_guide_integration_date: 2026-11-16
pending_docs: NINGUNO

## Cómo mantener este archivo
- OpenCode puede actualizar solamente los metadatos y el registro de pendientes aquí, no los textos para usuarios en `public/docs/index.html`.
- Al completar/publicar una nueva feature: si ChatGPT aún no redactó su nueva guía, registrar `GUIA_PENDIENTE_CHATGPT` con feature, SHA y fecha, más enlaces al packet/QA en el handoff.
- Después de que ChatGPT redacte y OpenCode integre/publica la guía, actualizar la cobertura y el SHA, marcando `GUIA_ACTUALIZADA`.
- Si cambia la rama principal o existe otro PR abierto, verificar HEAD antes de editar; no sobrescribir trabajo de otros agentes.
- La guía no se actualiza sin una solicitud explícita al chat: al usuario le corresponde traer el cierre de OpenCode, y ChatGPT verifica el código real mediante GitHub.
