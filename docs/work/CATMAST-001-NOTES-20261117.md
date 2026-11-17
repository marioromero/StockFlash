# CATMAST-001 — Notas técnicas (2026-11-17)

feature: CATMAST-001 — Maestros de Catálogo P0 (CAT-02/CAT-05)
status: IMPLEMENTADO_EN_ARBOL (packet APPROVED y activo; sin commit/push en esta sesión)
base_sha: `9a967499064527e5bd5c2e14c2281d5fa4807cf7`
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

## Ciclo correctivo QA — hallazgos H-01..H-06 (SFH1 issue #23, i=003)

Solicitado por `FIX CATMAST_RACE_INTEGRITY`. Base `HEAD = origin/main = 9a967499064527e5bd5c2e14c2281d5fa4807cf7`.
Sin nuevas tablas/campos/migraciones; `database/` intacto. Sin commit/push ni VERIFIED.

- **H-01 (TOCTOU ciclos):** `CatalogMastersController::assertCategoryHierarchyIsValid()` se ejecuta dentro de
  `DB::transaction` y bloquea todas las filas de `categories` (incluidas soft-deleted, orden por `id`) antes de
  revalidar existencia, soft-delete, padre propio y cadena de ciclos. `destroyCategory()` toma el mismo lock de
  jerarquía. La validación de `CategoryRequest` queda como UX; el guard transaccional es la autoridad.
  `DB::transaction($operation, 3)` reintenta deadlocks. La serialización ya no depende de `license_states`.
- **H-02 (carrera default):** `displaceOtherDefaultTaxRates()` bloquea todas las filas de `tax_rates`
  (`orderBy('id')->lockForUpdate()`) antes de limpiar defaults; funciona sin fila de licencia y con tabla vacía
  (gap/next-key lock InnoDB; SQLite ya serializa). El desplazamiento y el nuevo default ocurren en la misma
  transacción.
- **H-03 (UNIQUE → 422):** `rethrowUniqueViolation()` reconoce SQLSTATE `23000`/`23505` y vendor MySQL `1062`, y
  mapea tanto el formato SQLite (`tabla.columna`) como los índices reales (`tax_rates_code_unique`,
  `categories_slug_unique`, `brands_slug_unique`, `tags_slug_unique`, `attributes_name_unique`,
  `attribute_values_attribute_id_value_unique`) a `ValidationException` 422. Prueba de colisión directa con
  `DB::table` para cada recurso (sin afirmar MariaDB real).
- **H-04 (padre soft-deleted):** `CategoryRequest` usa
  `Rule::exists('categories','id')->whereNull('deleted_at')`; el guard transaccional repite la comprobación y
  rechaza con mensaje es-CL, sin escritura.
- **H-05 (auditoría default desplazado):** cada tasa desplazada emite
  `catalog.tax_rates.default_displaced` con `code` + `is_default` true→false, dentro de la misma transacción.
  Si la auditoría falla, rollback total (prueba con excepción del stub de `AuditLogger`).
- **H-06 (enlace Dashboard):** `HandleInertiaRequests::permissionCodes()` ahora acota usuarios por sucursal a
  `default_branch_id`, igual que `CheckPermission::allows` (los globales siguen usando el rol global). Dashboard
  muestra el enlace solo con `catalog.view` (la ruta GET la exige; `catalog.manage` por sí solo recibiría 403).
  Se mantienen los literales `catalog.view`, `catalog.manage` y `canViewCatalog` en `Dashboard.vue`.

Archivos tocados en este ciclo:
- `app/Http/Controllers/CatalogMastersController.php`
- `app/Http/Requests/Catalog/CategoryRequest.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/Pages/Dashboard.vue`
- `tests/Feature/Catalog/CatalogMastersIntegrityTest.php` (nuevo, 7 pruebas)
- `tests/Feature/Catalog/CatalogMastersAuthorizationTest.php` (2 pruebas H-06 añadidas)
- `docs/work/CATMAST-001-NOTES-20261117.md`

Pruebas: suite Catalog **55 pruebas / 1.047 aserciones** y suite completa **414 pruebas / 3.423 aserciones** en
SQLite `:memory:`; las pruebas son deterministas y no afirman concurrencia real. `typecheck`, `build` y
`check_contracts` verdes; `sf_verify.py CATMAST-001` → `GATES_PASS` 17/17. `maria=NOT_RUN` (locks MariaDB/InnoDB
reales no ejecutados); `remote_db=UNTOUCHED`.

## Handoff

- Ejecutar `py -3 scripts/sf_verify.py CATMAST-001` y QA independiente antes de publicar.
- Emitir `GUIA_PENDIENTE_CHATGPT — CATMAST-001` con rutas/campos reales; ChatGPT redacta la guía.
- No declarar VERIFIED desde OpenCode. Sin commit/push/deploy en esta sesión.
- remote_db=UNTOUCHED; maria=NOT_RUN.
