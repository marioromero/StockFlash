# CATPROD-001 — Productos, variantes, códigos de barras y clasificación P0

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Contexto y contrato

CATDB-001 (11 tablas P0) y CATMAST-001 (maestros de impuestos/categorías/marcas/tags/atributos) están VERIFIED técnicamente. Crear la **unidad funcional de administración de productos, variantes y códigos de barras** sobre el esquema aprobado: CAT-01/03/04/05/07/08, Parte 1 §8.2 y RN-01/02/05/07 (solo datos del catálogo), Parte 2 §5.4–5.6. No abrir microfeatures de tabla en tabla. No construir aún POS, inventario, caja, compras ni impuestos fiscales de transacciones.

## Entregable

1. UI Inertia/Vue, es-CL, responsive y accesible: `/catalog/products` con listado y alta/edición y detalles de variantes, códigos de barras y etiquetas. Enlazar desde Dashboard. `catalog.view` para lectura, `catalog.manage` para mutaciones, y **`catalog.view_cost` para exponer cualquier costo, margen o markup** (tanto props de servidor como vistas). La sucursal efectiva para RBAC debe coincidir con `CheckPermission::allows`; jamás confiar en `branch_id` enviado en query/body. El catálogo es común a instalación, pero permisos son los efectivos del usuario. Si el servidor niega una operación, no hay escritura.
2. Producto: crear/editar `products` exclusivamente con `category_id`, `brand_id`, `type` (simple/variable/combo), `name`, `description`, `internal_code`, `image_url`, `tax_rate_id`, `tracks_stock`, `is_sellable`, `is_active`, `origin` y las referencias permitidas; relaciones existentes, no columnas nuevas. Categoría/marca/tasa elegidas deben existir y no estar retiradas/deshabilitadas según contrato; tags por pivote sin duplicados. El `origin` no puede elevar privilegios de canales ni fingir creación por integraciones externas. No crear inventario físico al guardar.
3. Variantes: SKU único obligatorio (hasta 100), precio base DECIMAL(14,2), costo neto DECIMAL(14,4) protegido, `last_cost` solo si la política existente permite editarlo (no inventar costos comprados), `unit` enum contractual, `sold_by_weight`, `max_discount_percent`, `min_price`, `allow_price_override`, `tax_rate_id`, `combo_deducts_components`, `is_active` y soft delete existente. Un producto **simple** debe tener exactamente una variante; un producto **variable** admite varias variantes con un SKU distinto por cada una. Soportar `type=combo` únicamente dentro del contrato sin fingir composición P1: no habilitar venta de combos sin `combo_components` ni inventar descuentos/composición.
4. Para cada variante, gestionar varios `product_barcodes` con cadena única global y tipo enum (`ean13/ean8/upc/code128/internal/other`), a lo sumo un código marcado primario por variante (validación transaccional de aplicación; si se requiere UNIQUE DB nueva, NO crear migración silenciosa). Gestionar `product_variant_attribute_values` con máximo un valor por atributo para cada variante, sin colisiones ni cruces, validando relación con `attributes`. Producto↔tag por `product_tag` validado.
5. CRUD mediante Form Requests con allowlist, validación de formato/longitudes/rangos/precisiones/enum/FK, mensajes es-CL y respuestas 422 para duplicados reales de SKU, código de barras o internal_code. Operaciones de múltiples filas (crear producto + variante + pivotes, cambiar atributos/códigos) dentro de **una transacción y auditoría atómica**; si falla auditoría revertir todo. Auditoría con campos/IDs; no exponer ni registrar costos a usuarios sin `catalog.view_cost`. Deshabilitar/baja lógica conserva identificadores únicos; no borrar en cascada registros de negocio que más adelante puedan tener ventas/stock. Evitar N+1, paginar listado y buscar nombre/SKU/barcode con consultas acordes al esquema; **no afirmar cumplimiento <300ms de CAT-09** sin medición MariaDB real.
6. Licencia suspendida fuera de gracia RN-70 bloquea escritura. El permiso RBAC NO reemplaza módulos ni derechos comerciales; no deducir derechos de `installation_modules.is_enabled` ni crear ventas. Reutilizar seguridad de CATMAST-001 y mantener intactos usuarios, sucursales, configuraciones y otros maestros. No publicar API pública nueva, solo rutas del admin autenticado.
7. Tests en SQLite `:memory:` exclusivamente: permisos view/manage/view_cost por rol global y sucursal; props sin costos para usuarios sin permiso; validación de producto simple con variante única y variable con varias; rechazo duplicate SKU/barcode/internal_code, FK inválidas, tipo/unit/barcode fuera de enum, precisión/negativos, atributo repetido en variante, múltiples primarios, tags, transacción/rollback, RN-70, búsqueda con escáner simulada por texto y paginación; soft delete sin reutilizar identificadores, payload extra ignorado, rutas no autorizadas 403, regresiones CATDB/CATMAST/AUTH/USERS/CORE. CI PHP/TS/build/contratos, gates declarativos `CATPROD-001` en `GATES_BY_PACKET`, QA independiente y publicación AUTO_GIT, reportar SHA exacto y CI. Guía `GUIA_PENDIENTE_CHATGPT` para ChatGPT, sin editar HTML editorial.
8. Evitar mega-controlador monolítico y duplicación accidental de helpers; estructura Laravel pequeña y clara por responsabilidad (producto/variante/códigos), sin framework genérico, nuevas dependencias o pipelines. Si el alcance necesita una división por límites reales de QA, registrarla en packet y delegarla a ChatGPT sin pedir aprobación comercial genérica.

## EARS de aceptación

- CUANDO un gestor autorizado crea producto simple, EL SISTEMA DEBE persistir el producto con exactamente una variante y SKU único en la misma transacción auditable.
- CUANDO configura variantes, códigos de barras, atributos o etiquetas, EL SISTEMA DEBE preservar unicidad, integridad de FK y consistencia al actualizar.
- CUANDO el usuario carece de `catalog.view_cost`, EL SISTEMA NO DEBE exponer `cost_price`, `last_cost`, margen ni markup en props, logs o UI, aunque tenga permiso de consulta o gestión ordinaria.
- CUANDO ocurre fallo de auditoría/validación/permisos/licencia, EL SISTEMA NO DEBE dejar cambios parciales.
- CUANDO un identificador ya existe, se editan relaciones prohibidas o se intenta borrar datos relacionados, EL SISTEMA DEBE responder con error controlado, sin pérdidas silenciosas.
- CUANDO se solicita una búsqueda de producto por nombre/SKU/barcode, EL SISTEMA DEBE devolver resultados válidos con aislamiento por permisos y paginación, sin prometer latencia no medida.

## Exclusiones y seguridad operativa

NO migraciones/seeders ni BD DEV remota, NO leer `.env`/secretos, NO despliegue/FTP, NO DTE/SII, NO stock/current_stock/Kardex ni POS/caja/ventas, NO combos P1 funcionales, promociones, importación CSV ni sincronización WooCommerce, NO modificar contratos Parte 1/2, permisos, licencias comerciales, campos de tablas, HTML manual/roadmap o CONTEXTO-CHATGPT (ChatGPT exclusivo). MariaDB real, FULLTEXT y concurrencia simultánea: NOT_RUN y pendientes predespliegue. Toda prueba únicamente en SQLite :memory: y CI; no ejecutar SQL contra DB externa sin autorización expresa del propietario. Solo escalar decisiones NUEVAS y MATERIALES de negocio con preguntas concretas y recomendación.
