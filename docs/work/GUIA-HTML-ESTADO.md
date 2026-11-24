# StockFlash — Seguimiento de guía HTML de usuario

content_owner: ChatGPT (exclusivo para textos explicativos de `public/docs/index.html`)
integration_owner: OpenCode (validación, enlace /docs/, publicación y reportes)
policy: `docs/harness/GUIA-HTML-USUARIO.md`
guide_path: `public/docs/index.html`
guide_url: `/docs/`
guide_branch: `docs/guia-usuario-interactiva-20261110`
guide_pr: https://github.com/marioromero/StockFlash/pull/2
guide_status: INTEGRADA_EN_MAIN (2026-11-16)
guide_content_last_written: 2026-11-23
covered_features: AUTH-001 VERIFIED; BRANCH-001 VERIFIED; DOCS-001 VERIFIED; ROADMAP-001 VERIFIED; USERS-001 VERIFIED; CFG-001/002/003 VERIFIED; CORELIC-001 VERIFIED; CATMAST-001 VERIFIED (9279d57); SETUP-001 VERIFIED (13cdd6a); INVCORE-001 VERIFIED (966b685); INVWRITE-001 VERIFIED_TECH (816fdc6); INVKARDEX-001 VERIFIED_TECH (317a61d); INVREORDER-001 VERIFIED_TECH (679e858); INVCSV-001 VERIFIED_TECH (f856592); INVTRANSFER-001 VERIFIED_TECH (3072f54; guía integrada); CUSCORE-001 VERIFIED_TECH (b89ed47; guía integrada, 28 temas); SELLERS-001 VERIFIED_TECH (e46f25f; guía integrada, 29 temas)
latest_covered_code_sha: `e46f25f14b9c0114abbd2964c893d4d6960d655d` (SELLERS-001 and prior user-visible features; not a deployment claim)
next_feature: POSSTATION-001 — Estaciones POS y vinculación de dispositivo (P0 CSH-02/POS-02; packet aprobado, implementación pendiente). INSTALL-001 — Instalador CORE-17 (reconciliación editorial pendiente)
internal_only_features: CATDB-001 VERIFIED (11 tablas internas, sin UI ni página nueva); CORESEQ-001 VERIFIED (servicio interno invisible; sin entrada nueva en guía); COREMOD-001 VERIFIED técnico (servicio interno invisible de módulos; sin página nueva); COREPIN-001 VERIFIED (servicio interno invisible de autorización PIN; sin página nueva)
last_guide_integration_sha: `56c90b49839b69f0c5cad6d160bf7d9cd900d8d3` (ChatGPT, guía SELLERS-001 + roadmap, PR #64)
last_guide_integration_date: 2026-11-23
pending_docs: GUIA_PENDIENTE_CHATGPT — INSTALL-001 (reconciliación editorial instalador CORE-17); SELLERS-001 integrado por ChatGPT en PR #64 con nueva sección de vendedores; próxima guía POSSTATION-001 solo tras publicación técnica

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

## GUIA_PENDIENTE_CHATGPT — CATPROD-001
- feature: CATPROD-001 — Productos, variantes, códigos de barras, valores de atributo y etiquetas (CAT-01/03/04/05/07/08).
- pantalla: `/catalog/products` (acceso «Productos» desde el dashboard con `catalog.view` efectivo).
- campos reales: producto con `category_id`, `brand_id`, `type`, `name`, `description`, `internal_code`, `image_url`, `tax_rate_id`, `tracks_stock`, `is_sellable`, `is_active`, `origin` y etiquetas; variante con `sku`, `price`, `cost_price` protegido, `last_cost` solo lectura, `unit`, `sold_by_weight`, `max_discount_percent`, `min_price`, `allow_price_override`, `tax_rate_id`, `combo_deducts_components`, `is_active` y soft delete; códigos de barras con `barcode` único global, `type` y un solo `is_primary` por variante; máximo un valor de atributo por atributo en cada variante.
- permisos: lectura `catalog.view`, escritura `catalog.manage`, costos/margen/markup `catalog.view_cost`.
- contenido a redactar por ChatGPT: uso de la pantalla, simple/variable/combo, unicidad de SKU/código interno/código de barras, costos protegidos, bajas lógicas que conservan identificadores, RN-70 en modo solo lectura y exclusiones (sin inventario/stock, POS, caja, ventas, combos funcionales P1, promociones, CSV, WooCommerce ni DTE/SII).
- fecha: 2026-11-17.
- estado: ciclo correctivo H-01/H-02/H-03 aplicado en SQLite :memory: (origen manual siempre POS, margen/markup sobre precio neto RN-25 y calculadora CAT-04). Catalog 116/1810 y suite 476/4192; gates, QA externa y CI pendientes de cierre. Guía NO redactada todavía.
- nota correctiva para ChatGPT: documentar que el origen del CRUD manual es siempre POS y no editable; que el margen/markup se calculan sobre precio neto según `prices_include_tax`, tasa de variante/producto/predeterminada y exención; y que la calculadora de precio por margen o markup es una sugerencia visible solo con `catalog.view_cost`, no una regla automática de venta.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — SETUP-001
- feature: SETUP-001 — Asistente de primer inicio CORE-02 (una instalación).
- pantalla: `/setup` (pública solo mientras la instalación está pendiente y virgen; luego redirige al login).
- campos reales por paso:
  - Comercio y responsable (9 claves CFG-002, todas opcionales): `business_name`, `fiscal_id`, `fiscal_name`, `commercial_activity`, `fiscal_address`, `fiscal_commune`, `responsible_name`, `responsible_email`, `responsible_phone`.
  - Región y precios (7 claves CFG-003, obligatorias): `country` (default CL), `currency_code` (CLP), `currency_decimals` (0), `timezone` (America/Santiago), `locale` (es-CL), `prices_include_tax` (true), `cash_rounding_step` (1).
  - Sucursal inicial: `branch_code` (mayúsculas, `^[A-Z0-9-]+$`, máx. 30), `branch_name` (máx. 150), `branch_type` (store/warehouse/online) y dirección/comuna/ciudad/región/teléfono opcionales.
  - Admin Central: `admin_name`, `admin_email`, `admin_password` (15–64 caracteres), confirmación; se crea exactamente una cuenta activa con el rol de sistema `central_admin`.
- contenido a redactar por ChatGPT: uso del asistente por pasos, qué campos son opcionales, creación única de sucursal y Admin Central, ingreso posterior con esa cuenta, bloqueo informativo si la instancia ya contiene usuarios o sucursales (sin reiniciar datos), y bloqueo de producción no demo por BKP-08 con código `NOT_READY_FOR_PRODUCTION` (paquete de recuperación no implementado). Aclarar que no se ejecutan migraciones, no se emiten documentos tributarios y no se muestran contraseñas.
- fecha: 2026-11-17.
- estado: implementado en SQLite `:memory:` (50 pruebas nuevas, 465 aserciones; suite 528/4662). Ciclo correctivo QA aplicado (password fuera de old input, caché `no-store`, defaults, foco/errores UI, aviso DEV). Gates `GATES_PASS` 19/19; QA independiente final, publicación y CI pendientes; guía NO redactada todavía.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — INSTALL-001
- feature: INSTALL-001 — Instalador web CORE-17 P0 (una sola instalación).
- pantalla: `/install` (cerrada por defecto; solo se abre con `INSTALL_ENABLED=true` y token de arranque gestionado fuera de la app pública). El éxito redirige a `/setup`, que calcula el aviso determinista de cron desde `installation_info` (sin depender de flash entre sesiones).
- contenido a redactar por ChatGPT: instalador técnico separado de `/setup`; preflight de PHP/extensiones/dependencias/permisos; prueba de conexión MariaDB/MySQL sin mostrar credenciales; generación de `APP_KEY`, `BLIND_INDEX_KEY` y `BACKUP_ENCRYPTION_KEY` sin exhibirlas; migraciones normales y semillas existentes; bloqueo de reinstalación si ya hay `installation_info`, usuarios o sucursales; una única línea orientativa `php artisan schedule:run` (no instala cron); tras éxito se continúa en `/setup` para crear comercio, sucursal y Admin Central. Aclarar que no se crean usuarios ni datos comerciales desde `/install`, que no se borran datos y que producción no demo sigue bloqueada por BKP-08 `NOT_READY_FOR_PRODUCTION`.
- fecha: 2026-11-17.
- estado: implementado en SQLite `:memory:` con 73 pruebas / 939 aserciones del instalador y suite completa 602/5604; ciclo correctivo R2 B-04/B-05/M-05/m-06..m-10 aplicado; gates `GATES_PASS` 32/32; QA independiente, publicación y CI pendientes de cierre. Guía NO redactada todavía.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT - INVCORE-001
- feature: INVCORE-001 - Inventario por sucursal: kardex físico, existencias y disponibles (INV-01/INV-02/INV-03; RN-19/RN-21).
- pantalla: `/inventory` (entrada con `inventory.view`; redirige a la sucursal por defecto o primera activa accesible).
- contenido a redactar por ChatGPT: lectura de existencia física, reservado y disponible por variante; disponible = existencia - reservado calculado por el esquema; pantalla solo lectura en esta etapa; permisos `inventory.view` y alcance por sucursal; modo solo lectura de licencia no bloquea la consulta; aclarar que aún no existen ajustes manuales, ventas, traspasos, conteos ni ciclo de reservas, y que el kardex se corrige solo con movimientos de reversa.
- fecha: 2026-11-18.
- estado: implementado en código/pruebas SQLite :memory:; guía NO redactada todavía; `maria=NOT_RUN` y `remote_db=UNTOUCHED`.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT - INVWRITE-001
- feature: INVWRITE-001 - Carga inicial manual de existencias y ajustes controlados (INV-04 P0; INV-05 parte manual P0; RN-21).
- pantalla: `/inventory` y `/inventory/stock/{branch}` (misma tabla de lectura; acciones «Cargar inicial» solo sin historial y «Ajustar» con `inventory.adjust`).
- contenido a redactar por ChatGPT: diferencia entre carga inicial única y ajuste con motivo; existencia/reservado/disponible antes de enviar; cantidades decimales con signo en ajustes; motivo activo del ámbito `stock_adjustment` y nota obligatoria cuando el motivo la exige; umbral `stock_adjust_pin_value_threshold` (0 desactiva) evaluado sobre el valor a costo vigente, con autorizador distinto y PIN solo por encima del umbral; estados de permiso, módulo deshabilitado y licencia solo lectura; aclarar que el stock reservado no cambia con estas acciones, que no hay importación CSV en esta ola y que no se emiten documentos tributarios ni ventas.
- fecha: 2026-11-19.
- estado: implementado en código/pruebas SQLite :memory:; guía NO redactada todavía; `maria=NOT_RUN` y `remote_db=UNTOUCHED`.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT - INVKARDEX-001
- feature: INVKARDEX-001 - Reporte Kardex físico por sucursal, variante y fechas (INV-08 P0; RN-19/RN-21).
- pantalla: `/inventory/stock/{branch}/kardex` (enlace «Ver Kardex» desde la pantalla de inventario de la sucursal, con `inventory.view`).
- contenido a redactar por ChatGPT: lectura de movimientos físicos por sucursal con fecha/hora, tipo es-CL, cantidad con signo y saldo registrado; filtros por SKU/producto, variante y rango de fechas inclusivo; orden por ID de anexado y aclaración de que el saldo no se recalcula por fecha; reversas visibles con el movimiento corregido; estado vacío, paginación por cursor, licencia solo lectura que no bloquea la consulta; aclarar que no se muestran costos, precios, márgenes, usuarios, PIN ni PII, y que no hay CSV, edición ni borrado en esta ola.
- fecha: 2026-11-19.
- estado: implementado en código/pruebas SQLite :memory:; guía NO redactada todavía; `maria=NOT_RUN` y `remote_db=UNTOUCHED`; no VERIFIED.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT - INVREORDER-001
- feature: INVREORDER-001 - Punto de reposición, cantidad sugerida, máximo y panel de stock crítico por sucursal y variante (INV-07 P0).
- pantalla: `/inventory/stock/{branch}/reorder` (enlace «Reposición» desde la pantalla de inventario de la sucursal, con `inventory.view`; configuración con `inventory.adjust`).
- contenido a redactar por ChatGPT: diferencia entre punto de reposición, cantidad sugerida y máximo; el punto es obligatorio y no negativo, la cantidad sugerida y el máximo son opcionales; disponible = existencia − reservado; una variante es crítica solo cuando tiene configuración con punto mayor que 0 y disponible igual o menor al punto; un punto 0 o una variante sin configurar no genera alerta; filtros por SKU/producto y «Solo stock crítico», paginación acotada; filas históricas de variantes inactivas o eliminadas visibles solo lectura; licencia solo lectura permite consultar pero no guardar; aclarar que no se modifican existencias ni movimientos, que no se editan `safety_stock` ni ubicación en esta ola, y que no se muestran costos, precios, márgenes, PIN ni PII.
- fecha: 2026-11-19.
- estado: implementado en código/pruebas SQLite :memory:; 150 pruebas / 1804 aserciones de inventario y typecheck TS; gates 16/16 PASS y QA independiente APROBADO; publicación/CI pendientes; `maria=NOT_RUN`, `remote_db=UNTOUCHED`, navegador y confluencia MariaDB diferidos; no VERIFIED.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — INVTRANSFER-001
- feature: INVTRANSFER-001 — Traspasos entre sucursales con envío, recepción y diferencias parciales (INV-06 P0; RN-26).
- pantalla: `/inventory/stock/{branch}/transfers` (listado, filtros y borrador con `inventory.adjust`) y `/inventory/stock/{branch}/transfers/{id}` (detalle y transiciones); enlace «Traspasos» desde la pantalla de inventario de la sucursal.
- contenido a redactar por ChatGPT: origen y destino distintos y activos; borrador sin número visible hasta solicitar; número asignado por sucursal de origen al solicitar; envío descuenta solo el origen y recepción suma solo el destino; cantidades solicitadas/enviadas/recibidas/pendientes; recepción parcial deja diferencia pendiente visible sin ajustes, compensaciones, reversas ni reclamos automáticos; acciones según estado y sucursal; permisos `inventory.view`/`inventory.adjust`; módulo deshabilitado y licencia solo lectura; productos históricos inactivos siguen visibles; aclarar que no se muestran costos, precios, márgenes ni PII y que no hay reservas, ventas, POS, fiscal ni CSV en esta pantalla.
- fecha: 2026-11-20.
- estado: implementado en código/pruebas SQLite :memory:; gates/QA/CI pendientes; guía NO redactada todavía; `maria=NOT_RUN`, `remote_db=KNOWN_HISTORICAL_EXCEPTION_NOT_UNTOUCHED`, navegador y deploy diferidos; no VERIFIED.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — SELLERS-001
- feature: SELLERS-001 — Registro de vendedores y asignación por sucursal (SEL-01 P0; RN-11/RN-13/RN-14).
- pantallas: `/sellers` (listado, búsqueda por nombre/código, filtro de sucursales permitidas, alta y edición) y `/sellers/{id}` (detalle, edición y activar/desactivar).
- contenido a redactar por ChatGPT: código único normalizado en mayúsculas e inmutable, identificador no credencial; fila reservada «Sin asignar» (`NONE`) oculta y no editable; usuario vinculado opcional con acceso a todas las sucursales finales; comisión configurada 0–100 con dos decimales sin cálculo de comisiones ganadas; asignación a una o más sucursales activas; alcance global o por sucursal según `sellers.manage`; `sellers.view_reports` no concede administración; módulo fallback POS; licencia solo lectura; activar/desactivar sin borrar ni reutilizar código; auditoría sin copiar nombre, código ni PII del usuario; aclarar que no hay ventas, tickets, reportes ni liquidación de comisiones en esta pantalla.
- fecha: 2026-11-23.
- estado: implementado en código/pruebas SQLite :memory:; 57 pruebas dirigidas / 636 aserciones; gates/QA/CI pendientes; guía NO redactada todavía; `maria=NOT_RUN`, `remote_db=NOT_TOUCHED`, navegador/deploy diferidos; no VERIFIED.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.

## GUIA_PENDIENTE_CHATGPT — CUSCORE-001
- feature: CUSCORE-001 — Registro de clientes persona/empresa, grupos, descuentos y RUT cifrado con índice ciego (CUS-01/02/03; RN-58/61/64; S3).
- pantallas: `/customers` (listado, filtros y alta con `customers.view`/`customers.manage`), `/customers/{id}` (ficha enmascarada, edición y revelado con `customers.view_sensitive`) y `/customers/groups` (grupos y descuentos).
- contenido a redactar por ChatGPT: diferencia persona/empresa; RUT opcional validado con módulo 11, cifrado e índice ciego, sin mostrarse en claro; máscaras de RUT y contacto por defecto; botón de revelado solo con permiso de datos sensibles y lectura auditada; campos sensibles en blanco conservan el valor; grupos con descuento por defecto obligatorio y tope opcional; descuento/tope propio nulo hereda del grupo; estados activo/inactivo, bloqueo y anonimización; módulo de clientes dependiente de POS; licencia solo lectura; aclarar que no hay ventas, reservas, stock, consentimientos, direcciones ni emisión fiscal en esta pantalla.
- fecha: 2026-11-20.
- estado: implementado en código/pruebas SQLite :memory:; 273 pruebas dirigidas / 3872 aserciones (Clientes + Inventario), typecheck PASS; gates/QA/CI pendientes; guía NO redactada todavía; `maria=NOT_RUN`, `remote_db=UNKNOWN_HISTORICAL_EXCEPTION`, navegador/deploy diferidos; no VERIFIED.
- OpenCode no edita `public/docs/index.html`; este registro es solo metadato técnico.
