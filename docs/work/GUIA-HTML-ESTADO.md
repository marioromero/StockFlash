# StockFlash — Seguimiento de guía HTML de usuario

content_owner: Codex CLI (sucesor de ChatGPT por aprobación BRIDGE-001, 2026-11-12)
integration_owner: OpenCode (validación, enlace /docs/, publicación y reportes)
policy: `docs/harness/GUIA-HTML-USUARIO.md`
guide_path: `public/docs/index.html`
guide_url: `/docs/`
guide_branch: `docs/guia-usuario-interactiva-20261110`
guide_pr: https://github.com/marioromero/StockFlash/pull/2
guide_status: INTEGRADA_EN_MAIN (2026-11-10)
guide_content_last_written: 2026-11-10
covered_features: AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED (2026-11-10); ROADMAP-001 VERIFIED (2026-11-10)
latest_covered_code_sha: `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41` (ROADMAP-001 publicado; DOCS/AUTH/BRANCH previos)
next_feature: USERS-001 DRAFT (NO documentar como disponible)
last_guide_integration_sha: `6601a2a3cf230581f1fe733d1ea57d2060eb39ab` (head del PR #4; explicación de «Avance del proyecto» integrada)
last_guide_integration_date: 2026-11-10
pending_docs: NINGUNO

## Cómo mantener este archivo

Adenda de continuidad: Codex asume redacción, OpenCode mantiene metadatos e
integración. Sin cambios de UI en el núcleo BRIDGE-001, sin nueva cobertura ni
pendiente editorial de usuario. Transporte automático aún bloqueado; las
referencias históricas a traslado por ChatGPT no acreditan puente operativo.

- OpenCode puede actualizar solamente los metadatos y el registro de pendientes aquí, no los textos para usuarios en `public/docs/index.html`.
- Al completar/publicar una feature: si Codex aún no redactó su guía, registrar
  `GUIA_PENDIENTE_CHATGPT` (alias histórico) con feature, SHA y evidencia.
- Después de redacción de Codex e integración/QA/publicación por OpenCode,
  actualizar cobertura y SHA, marcando GUIA_ACTUALIZADA.
- Si cambia la rama principal o existe otro PR abierto, verificar HEAD antes de editar; no sobrescribir trabajo de otros agentes.
- Durante ejecución autorizada del puente validado, el cierre llega a Codex para
  revisión editorial sin traslado manual; antes de aceptar el puente se conserva
  el handoff pendiente. No atribuir actividad de fondo ni actualizaciones ficticias.
