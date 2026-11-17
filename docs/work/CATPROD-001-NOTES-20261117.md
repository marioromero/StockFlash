# CATPROD-001 — Notas técnicas de implementación (2026-11-17)

- Feature Packet: `docs/work/features/CATPROD-001.md`
- Estado del packet: `APPROVED` (`new_business_decisions: NONE`, `publication_mode: AUTO_GIT`, `execution_mode: ONE_SMALL_WAVE`)
- SHA base: `7889f5e990594bcdb8676060c915b5c9fb0305d1`
- Etapa: `R3_CORE`
- DB de pruebas: SQLite `:memory:` exclusivamente.
- `maria=NOT_RUN`
- `remote_db=UNTOUCHED`
- Navegador real/UX visual: no ejecutado en esta entrega.

## Alcance implementado

Administración de productos, variantes y códigos de barras sobre el esquema CATDB-001 existente, sin migraciones ni seeders nuevos:

- Listado `/catalog/products` con búsqueda por nombre, código interno, SKU y código de barras, paginación y filtros preservados.
- Detalle de producto con etiquetas, variantes, valores de atributo y códigos de barras.
- Alta/edición/baja lógica de producto y variantes.
- Alta/edición/baja de códigos de barras por variante.
- Etiquetas por `product_tag` sin duplicados.
- Valores de atributo por variante con máximo un valor por atributo.
- Un único código primario por variante con desplazamiento transaccional.
- Enlace desde Dashboard solo con `catalog.view` efectivo.

## Decisiones técnicas

- Autoridad de permisos reutilizada: `CheckPermission::allows`. El ámbito proviene solo de la ruta `{branch}` o `default_branch_id`; no se lee `branch_id` de query/body.
- Lectura: `catalog.view`. Mutaciones: `catalog.manage`. Costos, margen y markup: solo `catalog.view_cost`.
- Sin `catalog.view_cost`, el servidor omite `cost_price`, `last_cost`, `margin` y `markup_percent` de props, auditoría y UI; un `cost_price` enviado por el cliente se ignora y el costo existente se preserva.
- `last_cost` no es editable: no tiene regla en Product Request ni Variant Request.
- Producto simple = exactamente una variante; variable = al menos dos; combo = al menos una variante contractual, sin composición funcional P1.
- Bajas de producto/variante con soft delete. Los identificadores únicos (internal_code, SKU, barcode) no se liberan ni se reutilizan.
- Cada mutación y su auditoría se ejecutan en una transacción. Un fallo de auditoría revierte producto, variantes, pivotes y etiquetas.
- RN-70 reutiliza `LicenseReadOnlyRule`: licencia suspendida fuera de gracia bloquea escrituras.
- La auditoría usa `module_code = 'catalog'` y `branch_id = null` porque el catálogo es global.
- No se creó stock, inventario, Kardex, POS, caja, ventas, composición de combos, promociones, CSV, WooCommerce ni documento fiscal.

## Archivos principales

Backend:

- `app/Models/Product.php`
- `app/Models/ProductVariant.php`
- `app/Models/ProductBarcode.php`
- `app/Support/Catalog/ProductCatalogService.php`
- `app/Support/Catalog/ProductPresenter.php`
- `app/Support/Catalog/CatalogWriteGuard.php`
- `app/Support/Catalog/CatalogUniqueViolation.php`
- `app/Http/Controllers/Catalog/ProductController.php`
- `app/Http/Controllers/Catalog/ProductVariantController.php`
- `app/Http/Controllers/Catalog/ProductBarcodeController.php`
- `app/Http/Requests/Catalog/ProductRequest.php`
- `app/Http/Requests/Catalog/ProductVariantRequest.php`
- `app/Http/Requests/Catalog/ProductBarcodeRequest.php`
- `routes/web.php`
- `resources/js/Pages/Catalog/Products.vue`
- `resources/js/Pages/Dashboard.vue`

Pruebas:

- `tests/Feature/Catalog/ProductTestCase.php`
- `tests/Feature/Catalog/ProductAuthorizationTest.php`
- `tests/Feature/Catalog/ProductCrudTest.php`
- `tests/Feature/Catalog/ProductVariantTest.php`
- `tests/Feature/Catalog/ProductBarcodeTest.php`
- `tests/Feature/Catalog/ProductSearchTest.php`
- `tests/Feature/Catalog/ProductAuditRollbackTest.php`

## Cobertura de pruebas

- Permisos `catalog.view`, `catalog.manage` y `catalog.view_cost` por rol global y por sucursal.
- Props sin costos para actores sin `catalog.view_cost` y preservación de costos al editar.
- Producto simple con una variante; variable con varias; combo contractual.
- Duplicados de SKU, barcode e internal_code, incluida colisión directa contra SQLite.
- FK activas/no eliminadas, enums, precisión decimal, negativos y rangos.
- Máximo un valor por atributo; desplazamiento del código primario.
- Etiquetas sin duplicados.
- Rollback completo ante fallo de auditoría.
- RN-70 dentro y fuera de gracia.
- Búsqueda por nombre/SKU/barcode y paginación con filtros.
- Soft delete sin reutilización de identificadores.
- Payload extra ignorado y costos no filtrados sin permiso.
- Rutas no autorizadas con 403.
- Regresiones CATDB/CATMAST/Auth/Users/Core/Branches/Settings y suite completa vía gates.

## Ciclo correctivo QA — H1..H6

Correcciones aplicadas tras la revisión independiente `APROBADO 0 bloqueantes` (`docs/work/CATPROD-001-REVIEW-20261117.md`), sin ampliar alcance ni tocar `database/`, protegidos, dependencias, contratos o permisos:

- **H1 costo null/""**: con `catalog.view_cost`, `cost_price` ahora usa `sometimes|required|numeric` (y rango contractual); `null` o `""` responden 422 sin escritura. Sin `catalog.view_cost` el campo sigue ignorándose y se preserva el costo existente. El servicio, como defensa adicional, usa 0 al crear sin costo y omite el null al actualizar una columna NOT NULL. `last_cost` sigue no editable.
- **H2 precisión decimal**: `cost_price` (y `last_cost` por contrato) se alinean a DECIMAL(14,4) con máximo `9999999999.9999`; `price`/`min_price` mantienen DECIMAL(14,2) con máximo `999999999999.99`. Valores fuera de rango o precisión responden 422 con mensajes es-CL.
- **H3 orden de códigos**: `ProductPresenter` ordena por `is_primary` descendente y luego `id` ascendente; el primario queda primero y el resto mantiene orden estable por id.
- **H4 prueba tautológica**: la aserción de `name => 'Inventado'` se reemplazó por payload extra real (`percentage`, `current_stock`, `branch_id`, `invented_column`, `last_cost`) y verificación de que no se persiste ni altera columnas/valor de la variante.
- **H5 nombre de prueba**: se renombró a `test_listado_incluye_skus_y_tags_del_producto` y se aclaró que valida la forma del payload, sin afirmar medición de consultas.
- **H6 UI lectura/simple**: el formulario "Nueva variante" se oculta para producto `simple` salvo al editar la variante existente; los formularios de mutación de variante, código y producto se ocultan con `read_only`. Se mantienen etiquetas accesibles y textos es-CL.

Nuevos conteos de este ciclo: Catalog **105 pruebas / 1617 aserciones**; suite completa **465 pruebas / 3999 aserciones**; regresiones Auth 45/108, Users 79/564, Core 97/529, Branches 53/388, Settings 59/640; `py -3 scripts/sf_verify.py CATPROD-001` = **GATES_PASS 19/19**. SHA base sin cambios: `7889f5e990594bcdb8676060c915b5c9fb0305d1`. `maria=NOT_RUN`; `remote_db=UNTOUCHED`.

## Límites

- `maria=NOT_RUN`: no se ejecutó MariaDB real ni se afirmó FULLTEXT, concurrencia simultánea ni latencia <300 ms.
- `remote_db=UNTOUCHED`: no se abrió ni modificó ninguna base remota.
- No se ejecutó navegador real; la verificación visual/responsive queda para predespliegue.
- No se modificaron migraciones, seeders, contratos, permisos, licencias, HTML editorial del manual ni roadmap.

## Ciclo correctivo externo 2026-11-17 (H-01/H-02/H-03)

Correcciones aplicadas dentro del mismo alcance autorizado, sin decisiones comerciales nuevas, sin migraciones ni cambios de esquema. Base de la corrección: `8be4e8157905cf4bc4f482e5d5e837547b8bae16`.

- **H-01 origen/procedencia:** el CRUD manual `/catalog/products` crea siempre `origin=pos` en el servidor e ignora `origin=channel|import` del payload. Al editar se preserva el origen existente (incluido uno externo preexistente) y el campo ya no se actualiza desde el request. `Products.vue` quitó el selector editable; el origen solo se muestra como texto informativo. Sin cambios de modelo, esquema ni integraciones.
- **H-02 margen/markup RN-25:** se agregó `app/Support/Catalog/CatalogPricing.php` para calcular el precio neto según `prices_include_tax` de CFG-003 y la tasa efectiva variante → producto → predeterminada `is_default`, con exención (`is_exempt`). `margin` = neto - costo; `margin_percent` = (neto-costo)/neto; `markup_percent` = (neto-costo)/costo. Se manejan costo/precio cero y redondeos sin INF/NAN. `net_price`, `margin`, `margin_percent`, `markup_percent`, `effective_tax_rate`, `tax_is_exempt` y `tax_source` solo viajan con `catalog.view_cost`; sin permiso siguen ausentes `cost_price`, `last_cost` y derivados.
- **H-03 calculadora CAT-04:** alta/edición de variante y variante inicial del alta de producto incluyen calculadora visible solo con `catalog.view_cost`. Permite objetivo margen% o markup%, deriva el precio neto desde el costo neto y lo convierte al campo `price` según `prices_include_tax` y tasa efectiva. Rechaza margen ≥100%, costo 0, objetivos inválidos y resultados fuera de rango; el resultado solo reemplaza el campo editable y el servidor valida al guardar. La UI recibe `prices_include_tax` y las tasas con `rate`, `is_exempt` e `is_default`; no se agregaron columnas, tablas, API ni reglas nuevas.
- **Pruebas nuevas:** `tests/Feature/Catalog/ProductOriginTest.php` (3 pruebas) y `tests/Feature/Catalog/ProductPricingTest.php` (8 pruebas), más ajuste de la prueba de enums para aceptar que `origin` es ignorado. Cubren payload manipulado en alta/edición, origen externo preservado, precios inclusivos/exclusivos, herencia de tasa, exención, costo/precio cero, redondeo y actor sin `catalog.view_cost`.
- **Conteos de este ciclo:** Catalog **116 pruebas / 1810 aserciones**; suite completa **476 pruebas / 4192 aserciones**; typecheck y build Vite PASS.
- Límites sin cambios: `maria=NOT_RUN`, `remote_db=UNTOUCHED`; sin navegador real, MariaDB concurrente/FULLTEXT, deploy ni migraciones. HTML editorial y roadmap permanecen sin edición de OpenCode.

## Gates

Definición declarativa en `scripts/sf_verify.py` (`CATPROD-001`), 19 gates. Reutiliza infraestructura existente; no se agregaron scripts, agentes ni protocolos.

- Resultado: `GATES_PASS` 19/19.
- Pruebas Catalog: 105 pruebas, 1617 aserciones.
- Suite completa: 465 pruebas, 3999 aserciones.
- Regresiones Auth 45/108, Users 79/564, Core 97/529, Branches 53/388, Settings 59/640.
- Typecheck y build Vite PASS.
- Contratos, protegidos, `database/` sin cambios y diff-check PASS.

## Handoff

- Publicación: pendiente por `stockflash-publisher` (no se hizo commit/push en esta sesión).
- QA independiente y dictamen `VERIFIED`: pendientes de revisión externa posterior.
- Este documento no declara `VERIFIED`.

## GUIA_PENDIENTE_CHATGPT — CATPROD-001

- feature: CATPROD-001 — Productos, variantes, códigos de barras, valores de atributo y etiquetas (CAT-01/03/04/05/07/08).
- pantalla: `/catalog/products` (acceso «Productos» desde el Dashboard con `catalog.view` efectivo).
- campos reales del producto: `category_id`, `brand_id`, `type` (`simple|variable|combo`), `name` (máx. 255), `description`, `internal_code` (único, máx. 60), `image_url` (máx. 500), `tax_rate_id`, `tracks_stock`, `is_sellable`, `is_active`, `origin` (`pos|channel|import`) y etiquetas por pivote.
- campos reales de variante: `sku` (único obligatorio, máx. 100), `price` DECIMAL(14,2), `cost_price` DECIMAL(14,4) protegido, `last_cost` solo lectura para quien tenga `catalog.view_cost`, `unit` (`unit|kg|g|lt|ml|m|box|other`), `sold_by_weight`, `max_discount_percent`, `min_price`, `allow_price_override`, `tax_rate_id`, `combo_deducts_components`, `is_active` y soft delete.
- códigos de barras: varios por variante, `barcode` único global (máx. 64), `type` (`ean13|ean8|upc|code128|internal|other`), máximo un `is_primary` por variante.
- valores de atributo: máximo un valor por atributo en cada variante.
- permisos: lectura `catalog.view`, mutaciones `catalog.manage`, exposición/edición de costos, margen y markup `catalog.view_cost`.
- contenido a redactar por ChatGPT: uso de la pantalla, diferencias simple/variable/combo, SKU y códigos únicos, lectura de costos protegida, bajas lógicas que conservan identificadores, modo solo lectura por licencia RN-70 y aclaración de que no crea inventario, stock, ventas, composición de combos P1 ni documentos fiscales.
- exclusiones: sin POS, caja, ventas, inventario/Kardex, combos funcionales, promociones, CSV, WooCommerce, DTE/SII, migraciones nuevas ni BD remota.
- OpenCode no edita `public/docs/index.html`; este bloque es handoff técnico para ChatGPT.
