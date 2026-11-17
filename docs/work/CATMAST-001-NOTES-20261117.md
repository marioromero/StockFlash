# CATMAST-001 — Notas técnicas (2026-11-17)

feature: CATMAST-001 — Maestros de Catálogo P0 (CAT-02/CAT-05)
status: IMPLEMENTADO_EN_ARBOL (packet APPROVED y activo; sin commit/push en esta sesión)
base_sha: `9815d5c5aff39beeebf7b08644550b1018c06ba2`
environment: SQLite `:memory:` exclusivamente para pruebas; MariaDB real/remota NO ejecutada (`maria=NOT_RUN`, `remote_db=UNTOUCHED`)
migraciones: NINGUNA nueva ni modificada; se reutilizan las 11 tablas de CATDB-001.

## Alcance implementado

- Rutas protegidas `GET /catalog/masters` (`catalog.view`) y escrituras POST/PUT/DELETE por recurso (`catalog.manage`):
  `tax-rates`, `categories`, `brands`, `tags`, `attributes`, `attribute-values`.
- Pantalla Inertia/Vue única `resources/js/Pages/Catalog/Masters.vue` con cinco agrupaciones
  («Impuestos», «Categorías», «Marcas», «Etiquetas», «Atributos y valores»).
- Enlace «Maestros de catálogo» en Dashboard visible con `catalog.view` o `catalog.manage`.
- Modelos Eloquent, Form Requests con allowlist y controlador acotado. Sin CRUD genérico.

## Decisiones técnicas

- Columna contractual del porcentaje: `tax_rates.rate` DECIMAL(5,2). No existe ni se acepta `percentage`
  (se marca `prohibited`); no se crean columnas ni migraciones.
- `branch_id` en query/body ya NO participa de `CheckPermission`: el ámbito es solo
  `$request->route('branch')` o `$user->default_branch_id`. El catálogo es global y no usa `branch.access`.
  La ocultación de UI es solo ergonomía; la autorización real es server-side.
- No se infiere ningún entitlement desde `installation_modules.is_enabled`; un módulo habilitado no otorga permisos.
- Slugs: explícito si viene; al crear se genera con `Str::slug(name)` de forma estable. En edición, si `slug`
  no viene, NO se modifica aunque cambie `name`. La regla unique incluye registros soft-deleted y no se reutilizan.
- Jerarquía de categorías: se impiden padre propio, ciclos (incluyendo cadenas con soft-deleted) y FK inválidas.
- Tasa predeterminada: al guardar `is_default = true` se limpian las demás en la misma transacción; no se exige
  una tasa default permanente y se permite quitar el flag explícitamente. No se siembran IVA19/EXENTO.
- `is_exempt` se persiste tal cual; no se implementa cálculo fiscal ni ventas.
- Bajas seguras: categorías/marcas con SoftDeletes; tags/atributos/valores/tasas con borrado físico solo cuando
  no hay referencias. Se bloquea con validación es-CL si existen hijas, productos, pivotes o referencias.
- Toda mutación (incluido cambio de default) ocurre en `DB::transaction` con `AuditLogger::record(...)`,
  `module_code = 'catalog'`, `branch_id = null`, eventos `catalog.<tabla>.<acción>`, `auditable_type = tabla`.
  Si la auditoría falla, la excepción revierte la transacción.
- RN-70: se reutiliza `LicenseReadOnlyRule`; suspensión sin gracia o con gracia vencida bloquea escrituras.
  Sin fila de licencia no se bloquea (según contrato de este packet).
- Pruebas solo SQLite `:memory:`; no se insertan productos/variantes desde código de producción (solo fixtures
  de prueba para verificar bloqueos de baja).

## Archivos principales

- `routes/web.php` (bloque Catálogo dentro del grupo `auth`/`password.change`/`verify.session`).
- `app/Http/Middleware/CheckPermission.php` (ámbito sin `branch_id` de request; helper reutilizable).
- `app/Http/Controllers/CatalogMastersController.php`.
- `app/Http/Requests/Catalog/{TaxRate,Category,Brand,Tag,Attribute,AttributeValue}Request.php`.
- `app/Models/{TaxRate,Category,Brand,Tag,Attribute,AttributeValue}.php`.
- `resources/js/Pages/Catalog/Masters.vue`, `resources/js/Pages/Dashboard.vue`.
- `tests/Feature/Catalog/CatalogMastersTestCase.php`,
  `CatalogMastersAuthorizationTest.php`, `CatalogMastersCrudTest.php`.
- `scripts/sf_verify.py` (gates declarativos `CATMAST-001`).

## Riesgos y límites

- Validación funcional solo en SQLite `:memory:`; concurrencia real, locks y restricciones MariaDB/InnoDB quedan
  para predespliegue en entorno autorizado.
- Sin validación visual en navegador real en esta sesión; typecheck/build cubren compilación Vue.
- La UI asume el permiso efectivo de `catalog.manage` calculado server-side con la misma regla de `CheckPermission`;
  cualquier cambio futuro de RBAC debe mantener esa correspondencia.
- No se tocaron migraciones, seeders, `config/modules.php`, RBAC/permisos, contratos, HTML editorial ni roadmap HTML.

## Handoff

- Ejecutar `py -3 scripts/sf_verify.py CATMAST-001` y QA independiente antes de publicar.
- Emitir `GUIA_PENDIENTE_CHATGPT — CATMAST-001` con rutas/campos reales; ChatGPT redacta la guía.
- No declarar VERIFIED desde OpenCode. Sin commit/push/deploy en esta sesión.
- remote_db=UNTOUCHED; maria=NOT_RUN.
