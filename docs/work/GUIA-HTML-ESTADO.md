# StockFlash — Seguimiento de guía HTML de usuario

content_owner: ChatGPT (exclusivo para textos explicativos de `public/docs/index.html`)
integration_owner: OpenCode (validación, enlace /docs/, publicación y reportes)
policy: `docs/harness/GUIA-HTML-USUARIO.md`
guide_path: `public/docs/index.html`
guide_url: `/docs/`
guide_branch: `docs/guia-usuario-interactiva-20261110`
guide_pr: https://github.com/marioromero/StockFlash/pull/2
guide_status: INTEGRADA_EN_MAIN (2026-11-16)
guide_content_last_written: 2026-11-17
covered_features: AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001/002/003 VERIFIED; CORELIC-001 VERIFIED; CATMAST-001 VERIFIED (9279d57)
latest_covered_code_sha: `9279d57996ca2592a7c03fcdc7822e588621e2fe` (CATMAST-001 y anteriores)
next_feature: CATPROD-001 activa, productos/variantes/códigos con UI aún no implementada
internal_only_features: CATDB-001 VERIFIED (11 tablas internas, sin UI ni página nueva); CORESEQ-001 VERIFIED (servicio interno invisible; sin entrada nueva en guía); COREMOD-001 VERIFIED técnico (servicio interno invisible de módulos; sin página nueva); COREPIN-001 VERIFIED (servicio interno invisible de autorización PIN; sin página nueva)
last_guide_integration_sha: `7aa19fd39192f74ed265c5571b0930c9b4f61670` (ChatGPT, guía 24 temas CATMAST-001)
last_guide_integration_date: 2026-11-17
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

## GUIA_PENDIENTE_CHATGPT — CFG-003
- feature: CFG-003 — Preferencias generales del comercio (CORE-09).
- pantalla: `/settings/general` (acceso "Preferencias generales" desde el dashboard, con permiso `settings.manage`).
- contenido a redactar por ChatGPT: dos grupos («Región y moneda» y «Precios y efectivo»), las siete claves contractuales (`country`, `currency_code`, `currency_decimals`, `timezone`, `locale`, `prices_include_tax`, `cash_rounding_step`) con sus defaults (CL, CLP, 0, America/Santiago, es-CL, true, 1), y alcance informativo sin prometer cálculo, emisión fiscal ni modificación de ventas existentes.
- fecha: 2026-11-16.
- estado: publicado, QA/CI comprobadas y guía actualizada por ChatGPT en `e6ffeea6123950f7213e1c71443cbbcfd6e863d1`.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — CORELIC-001
- feature: CORELIC-001 — Estado local de licencia, aviso en Dashboard y modo solo lectura tras la gracia (CORE-08 · RN-67–70).
- pantalla: `/dashboard` (banner de estado visible para cualquier usuario autenticado; no es una pantalla de administración de licencias).
- contenido a redactar por ChatGPT: significado de los avisos de licencia (`expiring_soon`, `suspended` dentro de gracia, `suspended` fuera de gracia/sin gracia y «sin licencia configurada»), diferencia entre aviso y bloqueo de altas, modo solo lectura tras la gracia, y aclaración de que la licencia es informativa y no se administra desde el Dashboard.
- fecha: 2026-11-16.
- estado: implementado y documentado por ChatGPT en `aa3ba75b65f228c9ef68c85958bad77bc14a9666`;
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — CATMAST-001
- feature: CATMAST-001 — Maestros de Catálogo P0 (CAT-02/CAT-05).
- pantalla: `/catalog/masters` (acceso «Maestros de catálogo» desde el dashboard con `catalog.view` o `catalog.manage`).
- agrupaciones y campos reales:
  - «Impuestos»: `code` (único, máx. 20), `name` (máx. 80), `rate` (0–100, decimal), `is_exempt`, `is_default`, `fiscal_code` (máx. 10), `is_active`. Marcar una tasa como predeterminada desmarca las demás.
  - «Categorías»: `parent_id` (jerarquía sin ciclos), `name` (máx. 150), `slug` (único, máx. 170; se genera al crear y no cambia solo al editar), `max_discount_percent` (0–100), `default_tax_rate_id`, `sort_order` (0–65535), `is_active`.
  - «Marcas»: `name` (máx. 150), `slug` único (máx. 170), `description` (máx. 255), `logo_url` (máx. 500), `is_active`.
  - «Etiquetas»: `name` (máx. 100), `slug` único (máx. 120).
  - «Atributos y valores»: `attributes.name` único (máx. 100); `attribute_values.attribute_id` + `value` únicos por atributo (máx. 100).
- contenido a redactar por ChatGPT: uso de la pantalla, lectura con `catalog.view` y escritura con `catalog.manage`, catálogo común a la instalación, slugs automáticos/no renombrados, jerarquía y bloqueos de baja por referencias, tasa predeterminada atómica, modo solo lectura por licencia suspendida fuera de gracia, y aclaración de que esta pantalla no calcula impuestos ni emite documentos tributarios.
- fecha: 2026-11-17.
- estado: implementado; 17/17 gates PASS, 405 pruebas PHP y QA independiente APROBADO en SQLite :memory:. Pendiente publicación y redacción por ChatGPT; guía NO redactada todavía.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.
