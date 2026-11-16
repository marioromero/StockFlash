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
covered_features: AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001 VERIFIED (fd12693); CFG-002 VERIFIED (81ba734)
latest_covered_code_sha: `81ba73419bcffada59cea86efec4ac80f8a32c6f` (CFG-002 y anteriores)
next_feature: CFG-003 activa; sin implementación publicada todavía
last_guide_integration_sha: `6a55e363684aa73ddb0a1c1c605b790871bdae4e` (ChatGPT, 21 temas, CFG-002)
last_guide_integration_date: 2026-11-16
pending_docs: NINGUNO

## Cómo mantener este archivo
- OpenCode puede actualizar solamente los metadatos y el registro de pendientes aquí, no los textos para usuarios en `public/docs/index.html`.
- Al completar/publicar una nueva feature: si ChatGPT aún no redactó su nueva guía, registrar `GUIA_PENDIENTE_CHATGPT` con feature, SHA y fecha, más enlaces al packet/QA en el handoff.
- Después de que ChatGPT redacte y OpenCode integre/publica la guía, actualizar la cobertura y el SHA, marcando `GUIA_ACTUALIZADA`.
- Si cambia la rama principal o existe otro PR abierto, verificar HEAD antes de editar; no sobrescribir trabajo de otros agentes.
- La guía no se actualiza sin una solicitud explícita al chat: al usuario le corresponde traer el cierre de OpenCode, y ChatGPT verifica el código real mediante GitHub.

## GUIA_PENDIENTE_CHATGPT — CFG-002
- feature: CFG-002 — Datos del comercio y contacto responsable (CORE-09).
- pantalla: `/settings/business` (acceso "Datos del comercio" desde el dashboard, con permiso `settings.manage`).
- contenido a redactar por ChatGPT: dos grupos (Comercio y Responsable), campos opcionales, aviso de auditoría de lectura de datos personales y alcance no tributario (sin prometer validación SII/RUT ni emisión DTE).
- fecha: 2026-11-16.
- estado: implementado, QA y CI verificadas; guía publicada por ChatGPT en `6a55e363684aa73ddb0a1c1c605b790871bdae4e`.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.
