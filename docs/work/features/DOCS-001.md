# DOCS-001 — Integración del manual HTML de usuario y continuidad ChatGPT

status: APPROVED
owner_approval: Decisión expresa del propietario (2026-11-10): autoriza e integra la propuesta del PR #2, con autoría exclusiva de ChatGPT para el contenido explicativo de `/docs/` y `CONTEXTO-CHATGPT.md`, y autoriza el enlace «Ayuda» en Dashboard, la automatización de seguimiento y su publicación mediante el harness.
etapa: HARNESS-DOCS
dependencias: PR #2 (`refs/pull/2/head` `bc3ca23d41f8610fe2c7c7863488aa2db4c38d69`), `main` `f67a0779405fa3ecaf5b2f9e379fa11da8ccd2d8`, AUTH-001 VERIFIED, BRANCH-001 VERIFIED, USERS-001 DRAFT intacto.

## Qué / Por qué / No incluido

**Qué.** Integrar en `main` el sistema de documentación de usuario y continuidad entre sesiones de ChatGPT propuesto en el PR #2: manual HTML navegable en `/docs/`, protocolo de autoría, bitácora de cobertura, archivo de contexto de ChatGPT y reglas de handoff en `AGENTS.md` y comandos del harness. Añadir el acceso «Ayuda» en Dashboard, automatizar el estado `GUIA_ACTUALIZADA`/`GUIA_PENDIENTE_CHATGPT` y verificar `/docs/`.

**Por qué.** El propietario aprobó el PR #2. El manual y el contexto deben quedar versionados en `main`, con separación estricta de responsabilidades, para que cada cierre de OpenCode entregue a ChatGPT la información necesaria sin que OpenCode redacte contenido de usuario.

**No incluido.**
- Redactar, reescribir o ampliar el contenido explicativo de `public/docs/index.html` o `docs/harness/CONTEXTO-CHATGPT.md`: autoría exclusiva de ChatGPT.
- Documentar USERS-001 como disponible: sigue DRAFT, sin implementación.
- Cambios de permisos de OpenCode, reconstrucción del harness, migraciones, deploy o staging.
- Nuevas funcionalidades de producto.

## Archivos integrados del PR #2

Se integran **verbatim** desde `refs/pull/2/head` (`bc3ca23`):

- `public/docs/index.html` — manual HTML (14 temas de AUTH-001 y BRANCH-001, buscador, menú, acordeones, responsive).
- `docs/harness/GUIA-HTML-USUARIO.md` — protocolo de autoría y ciclo de documentación.
- `docs/harness/CONTEXTO-CHATGPT.md` — contexto persistente de ChatGPT.
- `docs/work/GUIA-HTML-ESTADO.md` — bitácora (OpenCode puede actualizar metadatos de cobertura, no textos de usuario).
- `AGENTS.md` — reglas 17 y 18 (manual HTML y continuidad ChatGPT).
- `.opencode/commands/sf-run.md`, `sf-feature.md`, `sf-review.md`, `sf-publish.md`, `sf-handoff.md` — controles de handoff documental.

La bitácora `docs/work/GUIA-HTML-ESTADO.md` se actualiza solo en metadatos técnicos (estado de integración, SHA, pendientes y cobertura). No se modifica el HTML ni el contexto de ChatGPT.

## Responsabilidades

- **ChatGPT (exclusivo):** títulos, textos, pasos, explicaciones, ejemplos, FAQ, glosario y cualquier cambio semántico de `public/docs/index.html`; mantenimiento de `docs/harness/CONTEXTO-CHATGPT.md`.
- **OpenCode:** integración técnica, enlace «Ayuda», ruta `/docs/`, verificación de HTML/CSS/JS, accesibilidad, responsive, CI, gates, QA y publicación. Puede corregir aspectos técnicos sin cambiar el contenido redactado. Actualiza la bitácora técnica `GUIA-HTML-ESTADO.md`.
- **Handoff:** el propietario entrega a ChatGPT el cierre de OpenCode; no existe conexión automática.

## Automatización del seguimiento

- Las reglas 17/18 de `AGENTS.md` y los comandos del harness incorporan la obligación de informar `GUIA_ACTUALIZADA` o `GUIA_PENDIENTE_CHATGPT` al cierre de cada feature.
- `scripts/sf_verify.py` añade al reporte de gates un bloque de estado documental calculado desde `docs/work/GUIA-HTML-ESTADO.md`: `guide_status`, cobertura, pendientes, SHA de referencia y archivos de UI modificados. No redacta contenido; solo reporta.
- Si hay pendientes, el informe incluye la instrucción de compartir el cierre con ChatGPT y los datos de handoff (feature, SHA, rutas/pantallas, packet y QA/CI).
- No se bloquea la publicación de código por una guía pendiente.

## Acceso e integración visual

- Ruta pública `/docs` (con o sin barra final) que sirve `public/docs/index.html`.
- Enlace visible **Ayuda** en el Dashboard, accesible, con apertura en nueva pestaña y `rel="noopener noreferrer"`.
- Verificación de buscador, menú lateral, enlaces, acordeones, teclado, foco visible, `aria-*`, `lang="es-CL"`, responsive y ausencia de recursos externos/secretos.
- CI existente amplía el smoke para comprobar `GET /docs/` y el título del manual.

## Aceptación (EARS)

- CUANDO un usuario autenticado abre el Dashboard, EL SISTEMA DEBE mostrar un enlace «Ayuda» hacia `/docs/`, accesible por teclado y visible en móvil y escritorio.
- CUANDO se solicita `GET /docs/`, EL SISTEMA DEBE responder 200 con el manual HTML y `lang="es-CL"`.
- CUANDO se solicita `GET /docs`, EL SISTEMA DEBE servir el mismo manual que `/docs/`.
- CUANDO se revisa `public/docs/index.html`, EL SISTEMA DEBE contener buscador, menú lateral, acordeones, anclas y estilos responsive, sin recursos externos ni secretos.
- CUANDO se compara el contenido integrado con `refs/pull/2/head`, LOS ARCHIVOS DE AUTORÍA CHATGPT DEBEN ser idénticos byte a byte, salvo la bitácora técnica.
- CUANDO se ejecuta `sf_verify`, EL REPORTE DEBE incluir `GUIA_ACTUALIZADA` o `GUIA_PENDIENTE_CHATGPT` con la información de handoff.
- CUANDO hay una pantalla nueva no documentada por ChatGPT, EL CIERRE DEBE informar `GUIA_PENDIENTE_CHATGPT` sin redactar el contenido desde OpenCode.
- CUANDO se publica, EL SISTEMA DEBE conservar USERS-001 en DRAFT, sin implementación.
- CUANDO se ejecutan pruebas y gates, LA SUITE PHP, TYPECHECK, BUILD Y CONTRATOS DEBEN quedar verdes.

## Plan técnico

- Integrar los 10 archivos del PR #2 (9 archivos + bitácora) preservando el trabajo de USERS-001.
- Añadir ruta pública `docs.index` en `routes/web.php` (sirve `/docs` y `/docs/`).
- Añadir enlace «Ayuda» en `resources/js/Pages/Dashboard.vue`.
- Añadir `tests/Feature/Docs/DocsIntegrationTest.php` con disponibilidad, estructura, seguridad, enlace, reglas de autoría y bitácora.
- Ampliar `.github/workflows/ci.yml` con smoke de `/docs/`.
- Extender `scripts/sf_verify.py` con gates `DOCS-001`, soporte de ruta en el gate HTTP y bloque automático de estado documental.
- Actualizar `docs/work/GUIA-HTML-ESTADO.md` (metadatos) y `docs/work/CURRENT.md`.
- No modificar el contenido de `public/docs/index.html`, `docs/harness/CONTEXTO-CHATGPT.md` ni `docs/harness/GUIA-HTML-USUARIO.md` respecto del PR.

## Gates

- [ ] packet `DOCS-001` APPROVED y activo en `CURRENT.md`.
- [ ] archivos del PR presentes; autoría ChatGPT intacta byte a byte.
- [ ] reglas 17/18 en `AGENTS.md` y controles en comandos del harness.
- [ ] ruta `/docs` (con o sin barra final) y enlace «Ayuda» en Dashboard.
- [ ] HTML con buscador, menú, acordeones, `lang=es-CL`, sin recursos externos ni secretos.
- [ ] bitácora con `content_owner: ChatGPT`, estado de integración y pendientes.
- [ ] `tests/Feature/Docs` verde.
- [ ] suite completa, regresión AUTH-001/BRANCH-001, `npm run typecheck` y `npm run build`.
- [ ] `scripts/check_contracts.py` OK.
- [ ] gate HTTP `GET /docs/` 200 + título.
- [ ] reviewer independiente `APROBADO` sobre el estado exacto.
- [ ] reporte `sf_verify` incluye estado `GUIA_*`.

## Estado de funciones

- **Ya existente:** AUTH-001 VERIFIED, BRANCH-001 VERIFIED, USERS-001 DRAFT con QA documental APROBADO (sin implementar), harness con gates/QA/publisher.
- **Esta integración:** manual `/docs/`, protocolo y bitácora, contexto ChatGPT, reglas de autoría/handoff, enlace «Ayuda», automatización de estado documental y smoke de `/docs/`.
- **Pendiente de autoría ChatGPT:** referencia al nuevo enlace «Ayuda» en el manual y actualización de `CONTEXTO-CHATGPT.md` para reflejar que el PR #2 ya está integrado. OpenCode no los redacta.
