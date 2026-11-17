# CATPROD-001 - Revision QA independiente (2026-11-17)

- Autor: `stockflash-reviewer` (revision independiente; no implemento, no corrijo, no hago commit/push).
- Feature Packet: `docs/work/features/CATPROD-001.md` (status APPROVED, `approval_basis: existing_approved_contract_owner_delegation_2026-11-16`, `new_business_decisions: NONE`, AUTO_GIT, ONE_SMALL_WAVE).
- Veredicto: **APROBADO** (0 hallazgos bloqueantes; 6 observaciones no bloqueantes).
- `maria = NOT_RUN`; `remote_db = UNTOUCHED`; no se ejecutaron migraciones; no se declaro VERIFIED.

## 1. Estado exacto revisado

- Base: `HEAD = 7889f5e990594bcdb8676060c915b5c9fb0305d1` = `origin/main` (verificado con `git rev-parse HEAD` y `git rev-parse origin/main`).
- Arbol de trabajo al inicio de la revision: 5 archivos rastreados modificados y 26 archivos sin seguimiento (22 del cambio CATPROD-001 + 4 reportes `CATPROD-001-VERIFY-*` previos del orquestador). Esta revision solo agrega este dictamen y `docs/work/CATPROD-001-VERIFY-20261117-132643.md`. `database/`, `config/`, `composer.json`, `package.json`/`package-lock.json` sin cambios (`git status --porcelain -- database config composer.json package.json package-lock.json` vacio).
- Protegidos sin cambios: `AGENTS.md`, `opencode.json`, `docs/governance/CONSTITUTION.md`, `docs/product`, `docs/branding`, `docs/harness/CONTEXTO-CHATGPT.md`, `public/docs/index.html`, `resources/roadmap/index.html` (`git diff --quiet HEAD -- ...` => sin cambios).
- Nota de trazabilidad: la ejecucion de gates de esta revision genero `docs/work/CATPROD-001-VERIFY-20261117-132643.md` (salida propia de `scripts/sf_verify.py`). No modifique ningun archivo del cambio bajo revision.

### Archivos revisados (hash de objeto git, primeros 12 hex)

| Archivo | Hash | Bytes |
| --- | --- | --- |
| app/Http/Controllers/Catalog/ProductController.php (nuevo) | 30fb4a202005 | 9122 |
| app/Http/Controllers/Catalog/ProductVariantController.php (nuevo) | b922330cf1a6 | 2242 |
| app/Http/Controllers/Catalog/ProductBarcodeController.php (nuevo) | a800ba6023c6 | 2207 |
| app/Http/Requests/Catalog/ProductRequest.php (nuevo) | ff01f3832361 | 11459 |
| app/Http/Requests/Catalog/ProductVariantRequest.php (nuevo) | 6509b13b83bb | 6218 |
| app/Http/Requests/Catalog/ProductBarcodeRequest.php (nuevo) | 4a35adf63790 | 1839 |
| app/Models/Product.php (nuevo) | 76179cb7e448 | 1667 |
| app/Models/ProductVariant.php (nuevo) | be9e67b468b9 | 1925 |
| app/Models/ProductBarcode.php (nuevo) | 83fcd58af320 | 770 |
| app/Support/Catalog/ProductCatalogService.php (nuevo) | 841016c21d47 | 24865 |
| app/Support/Catalog/ProductPresenter.php (nuevo) | 3ea02d16ce2b | 5487 |
| app/Support/Catalog/CatalogWriteGuard.php (nuevo) | 465aca55e4ac | 1157 |
| app/Support/Catalog/CatalogUniqueViolation.php (nuevo) | 9b8329b1d18e | 2867 |
| resources/js/Pages/Catalog/Products.vue (nuevo) | 4d0ef9ebf0bf | 60130 |
| resources/js/Pages/Dashboard.vue (modificado) | c0d7f8934d85 | 8152 |
| routes/web.php (modificado) | 62893201a6cd | 13921 |
| scripts/sf_verify.py (modificado, solo `GATES_BY_PACKET["CATPROD-001"]`) | 68f227e659c7 | 105994 |
| tests/Feature/Catalog/ProductTestCase.php (nuevo) | 01a8d0f8a691 | 5926 |
| tests/Feature/Catalog/ProductAuthorizationTest.php (nuevo) | 0a46f909cacf | 7766 |
| tests/Feature/Catalog/ProductCrudTest.php (nuevo) | d68cce5281a9 | 11582 |
| tests/Feature/Catalog/ProductVariantTest.php (nuevo) | b441cd5b7632 | 7712 |
| tests/Feature/Catalog/ProductBarcodeTest.php (nuevo) | 99ff9408dd8c | 5820 |
| tests/Feature/Catalog/ProductSearchTest.php (nuevo) | 9ac03fe54cf8 | 4365 |
| tests/Feature/Catalog/ProductAuditRollbackTest.php (nuevo) | 68358c82dba6 | 4780 |
| docs/work/CATPROD-001-NOTES-20261117.md (nuevo) | caf030775245 | 7660 |
| docs/work/GUIA-HTML-ESTADO.md (modificado) | 4c90d3be7b29 | 8143 |
| docs/work/ROADMAP-HTML-ESTADO.md (modificado) | 066c973748d5 | 1949 |

## 2. Comandos ejecutados y resultados

| Comando | Resultado |
| --- | --- |
| `git rev-parse HEAD` / `git rev-parse origin/main` | `7889f5e990594bcdb8676060c915b5c9fb0305d1` en ambos |
| `git status --short` + `git ls-files --others --exclude-standard` | 5 modificados + 21 nuevos; sin cambios en `database/` |
| `php artisan test tests/Feature/Catalog` | **101 passed / 1558 assertions** (17.5 s) |
| `php artisan test` | **461 passed / 3940 assertions** (77.5 s) |
| `php artisan test tests/Feature/Auth` | 45 passed / 108 assertions |
| `php artisan test tests/Feature/Users` | 79 passed / 564 assertions |
| `php artisan test tests/Feature/Core` | 97 passed / 529 assertions |
| `php artisan test tests/Feature/Branches` | 53 passed / 388 assertions |
| `php artisan test tests/Feature/Settings` | 59 passed / 640 assertions |
| `py -3 scripts/sf_verify.py CATPROD-001` | **GATES_PASS 19/19** (CPRD1..CPRD19 PASS; incluye contratos, typecheck, build, protegidos, `database/`, diff-check) -> `docs/work/CATPROD-001-VERIFY-20261117-132643.md` |
| `git diff --quiet HEAD -- <protegidos>`; `git status --porcelain -- database config composer.json package.json` | protegidos intactos; dependencias y `database/` sin cambios |

Confrontacion con `docs/work/CATPROD-001-NOTES-20261117.md`: los conteos declarados (Catalog 101/1558, suite 461/3940, regresiones 45/108, 79/564, 97/529, 53/388, 59/640, gates 19) coinciden exactamente con lo reproducido. Las afirmaciones `maria=NOT_RUN`, `remote_db=UNTOUCHED`, "sin navegador real", "no se declara VERIFIED" y "no se edito HTML editorial" son fieles al estado.

## 3. Checklist criterio por criterio

### Criterio 1 - Rutas, middlewares y ambito RBAC: CUMPLE
- `/catalog/products*` dentro del grupo `['auth','password.change','verify.session']` (`routes/web.php:162`, bloque `routes/web.php:165-196`). GET con `can.permission:catalog.view`; 9 mutaciones con `can.permission:catalog.manage`.
- Sin `branch.access`; `CheckPermission::allows($user,$permission,$request->route('branch'))` no lee `branch_id` de query/body (`app/Http/Middleware/CheckPermission.php:34,49-78`). En controladores/service el ambito nunca se toma de la request (`ProductController.php:242-244`, `ProductVariantController.php:71-76`).
- Enlace Dashboard condicionado a `catalog.view` efectivo, derivado de `auth.permissions` acotado por servidor a rol global o sucursal por defecto (`Dashboard.vue:114-120`; `HandleInertiaRequests.php:63-99`).
- 403 en rutas no autorizadas y `branch_id` de query/body sin efecto: pruebas reales (`ProductAuthorizationTest.php:32-38,40-51,91-120,140-169,171-189`).

### Criterio 2 - Producto acotado al contrato: CUMPLE
- Solo columnas contractuales (`Product.php:18-21`, `ProductCatalogService.php:432-477`); sin migraciones ni columnas nuevas (`database/` intacto).
- Categoria/marca/tasa existentes y vigentes: `Rule::exists(...)->whereNull('deleted_at')->where('is_active',1)` (`ProductRequest.php:74-98,127-131`).
- `origin` restringido a `pos|channel|import` (`ProductRequest.php:102`) y sin uso en autorizacion ni en modulos (grep: solo persistencia/auditoria/props). No crea stock/inventario (grep sin `current_stock`/`stock_movements`/`kardex`).

### Criterio 3 - Variantes: CUMPLE
- SKU obligatorio <=100 y unico (incluye eliminados), `price` 14,2, `cost_price` 14,4 protegido, `last_cost` fuera de `#[Fillable]` y sin regla de validacion (`ProductVariant.php:18-22`, `ProductVariantRequest.php:47-79`, `ProductRequest.php:110-146`).
- Enums exactos (`unit`, `type`) alineados con el esquema; simple = exactamente 1 y variable >= 2 revalidado en request **y** de forma transaccional en servidor (`ProductRequest.php:228-249`, `ProductCatalogService.php:605-629,172-179,252-265`).
- `combo` solo tipo contractual con >=1 variante, sin composicion P1 ni descuentos inventados.

### Criterio 4 - Codigos, atributos y etiquetas: CUMPLE
- Multiples codigos por variante, `barcode` unico global (schema + rule) y tipo enum exacto (`ProductBarcodeRequest.php:36-49`).
- Maximo un `is_primary` por variante validado en transaccion con desplazamiento y auditoria (`ProductCatalogService.php:300-311,361-370,647-678`); no se agrego UNIQUE de esquema.
- Un valor por atributo por variante con FK valida, en request y servicio (`ProductRequest.php:254-275`, `ProductVariantRequest.php:145-166`, `ProductCatalogService.php:580-603`).
- Etiquetas sin duplicados: `distinct` + `unique` en servicio + PK del pivote (`ProductRequest.php:103-104`, `ProductCatalogService.php:544-548`).

### Criterio 5 - Form Requests, allowlist y 422: CUMPLE (con hallazgo no bloqueante H1/H2)
- Allowlist por `validated()`: campos extra y `last_cost` se descartan; probado (`ProductCrudTest.php:52-68`).
- Longitudes, rangos, precision, enums y FK validados; mensajes es-CL.
- Duplicados (SKU/barcode/internal_code) devuelven 422, incluida la violacion UNIQUE real mapeada desde `QueryException` (`CatalogUniqueViolation.php:17-80`), con prueba de rollback del desplazamiento primario (`ProductBarcodeTest.php:116-140`).

### Criterio 6 - Transacciones y auditoria atomica: CUMPLE
- Producto+variantes+pivotes+etiquetas en una transaccion; cada mutacion y su auditoria comparten transaccion; fallo de auditoria revierte todo (`ProductCatalogService.php:33-68,80-111,120-152,164-194,210-235,244-280,292-324,339-383,392-422`).
- Auditoria con IDs/campos y `module_code='catalog'`; `branch_id=null` por ser catalogo comun. Pruebas reales de rollback (`ProductAuditRollbackTest.php:15-77`).

### Criterio 7 - Seguridad de costos: CUMPLE
- Sin `catalog.view_cost` no existen claves `cost_price`, `last_cost`, `margin`, `markup_percent` en props (`ProductPresenter.php:118-132`), ni en auditoria (`ProductCatalogService.php:704-725`), ni en UI (`Products.vue:229-249,293-297,472-475`).
- Un `cost_price` enviado sin permiso no se valida ni entra en `validated()`, y al editar se preserva el costo existente (`ProductCatalogService.php:534-536`); probado end-to-end (`ProductVariantTest.php:86-111`).
- Con permiso, `last_cost` es solo lectura (no editable por fillable ni por reglas).

### Criterio 8 - Bajas y RN-70: CUMPLE
- Soft delete de producto y variante; identificadores unicos siguen ocupados (internal_code/SKU/barcode sin filtro de `deleted_at`) y no se reutilizan (`ProductCrudTest.php:261-290`, `ProductVariantTest.php:179-196`).
- Sin cascadas destructivas de negocio: barcodes y pivotes se conservan; solo se marca `deleted_at`.
- RN-70: `CatalogWriteGuard::assertWritable()` dentro de cada transaccion de escritura (`CatalogWriteGuard.php:18-30`), reutilizando `LicenseReadOnlyRule`; pruebas fuera y dentro de gracia (`ProductAuditRollbackTest.php:79-104`).

### Criterio 9 - Pruebas SQLite :memory: significativas: CUMPLE (con H4/H5 no bloqueantes)
- 101 pruebas nuevas ejecutadas realmente sobre SQLite `:memory:` (`CatalogMastersTestCase.php:24-31`), cubriendo permisos global/sucursal, view_cost, simple/variable/combo, duplicados con colision directa en DB, FK/enums/precision/negativos, atributo repetido, multiples primarios, tags, rollback de auditoria, RN-70, busqueda nombre/SKU/barcode, paginacion con filtros, soft delete sin reutilizar, payload extra y 403.
- Sin pruebas tautologicas relevantes; dos aserciones/nombres mejorables (H4, H5) que no afectan la validez de los criterios.

### Criterio 10 - UI Vue: CUMPLE (con H3/H6 no bloqueantes)
- Listado con busqueda nombre/SKU/codigo interno/barcode y paginacion con filtros preservados; alta/edicion de producto, variante, atributos, codigos y etiquetas; detalle de producto.
- es-CL, `aria-label`/`role=status|alert`, `th scope=col`, foco visible, tabla responsive con `data-label` y media query a 760 px (`Products.vue:1631-1684`).
- Costos solo bajo `can_view_cost` en template y en payload.
- `npm run typecheck` y `npm run build` PASS (gates CPRD15/CPRD16).

### Criterio 11 - Limites operativos: CUMPLE
- Sin dependencias nuevas (`composer.json`, `package.json`, `package-lock.json` intactos).
- Sin agentes/scripts/protocolos nuevos: solo la clave `CATPROD-001` en `GATES_BY_PACKET` de `scripts/sf_verify.py`.
- Protegidos, contratos, permisos y licencias sin cambios; `maria=NOT_RUN`, `remote_db=UNTOUCHED` declarados y verificados; no se afirma latencia <300 ms en codigo ni en notas.
- Marca: la pantalla replica exactamente tokens y estructura de `Catalog/Masters.vue` (ya aprobada); sin PNG/logos inventados; no se introdujo drift nuevo.

## 4. Hallazgos

### Bloqueantes
Ninguno.

### No bloqueantes

**H1 (MENOR) - `cost_price: null` produce 500 en lugar de 422.**
Evidencia: `app/Http/Requests/Catalog/ProductRequest.php:138-146` y `app/Http/Requests/Catalog/ProductVariantRequest.php:71-79` aceptan `nullable`; `prepareForValidation` convierte `""` a `null` (`ProductRequest.php:53-57`, `ProductVariantRequest.php:32-36`); `ProductCatalogService.php:490-492` inserta el valor tal cual en una columna `cost_price DECIMAL(14,4) NOT NULL DEFAULT 0` (`database/migrations/2026_11_16_000021_create_catalog_p0_tables.php:112`). Un cliente con `catalog.view_cost` que envie `null` (o `""`) provoca NOT NULL -> `QueryException` no-UNIQUE -> se relanza sin mapear a 422. Detectado por inspeccion estatica; no reproducido con prueba automatizada. La UI no lo dispara (omite el campo vacio, `Products.vue:947-949,1036-1038`). Sugerencia: en creacion normalizar `null` a 0 y en edicion omitir la clave cuando sea `null`.

**H2 (MENOR) - Rango maximo declarado de `cost_price` excede DECIMAL(14,4).**
Evidencia: `ProductRequest.php:144` y `ProductVariantRequest.php:77` usan `max:999999999999.9999` (12 enteros), mientras `DECIMAL(14,4)` admite 10 enteros (max 9999999999.9999). Con MariaDB en modo estricto un valor de 11-12 enteros pasaria validacion y fallaria en insercion. MariaDB real es `NOT_RUN` (pendiente predespliegue); en SQLite no se manifiesta. `price`/`min_price` (14,2) si son correctos.

**H3 (MENOR) - Orden de codigos de barras no prioriza el primario.**
Evidencia: `app/Support/Catalog/ProductPresenter.php:110-115` aplica `sortByDesc('is_primary')` y luego `sortBy('id')`; el segundo orden reemplaza al primero, por lo que la lista queda ordenada solo por `id`. Efecto visual: el chip "(principal)" puede no aparecer primero. No altera datos ni permisos.

**H4 (MENOR) - Asercion tautologica en una prueba.**
Evidencia: `tests/Feature/Catalog/ProductCrudTest.php:67` `assertDatabaseMissing('products', ['name' => 'Inventado'])`, nombre que nunca se envia en el payload. Las aserciones con valor real de la prueba (payload extra, `last_cost` nulo, precio intacto) si son validas; la linea citada no aporta evidencia. Sugerencia: eliminar o reemplazar por `assertDatabaseHas('products', ['id' => ...])` con columnas no enumeradas comprobadas via `only()`.

**H5 (MENOR) - Nombre de prueba promete algo que no mide.**
Evidencia: `tests/Feature/Catalog/ProductSearchTest.php:97-110` `test_listado_carga_skus_y_tags_sin_n_mas_uno` solo verifica forma del payload, no numero de consultas. El N+1 si esta evitado estructuralmente (`ProductController.php:44-51` con `with`/`withCount`), pero la garantia no esta medida. Opcionalmente usar `DB::listen`/`DB::enableQueryLog` para fijar el limite.

**H6 (MENOR) - UX: formulario de variante visible cuando no puede tener exito.**
Evidencia: `resources/js/Pages/Catalog/Products.vue:282` renderiza "Nueva variante" con solo `canManage`, y el producto `simple` siempre rechaza una segunda variante (`ProductVariantRequest.php:105-106`). Ademas, en modo solo lectura por licencia los inputs de esa seccion siguen editables aunque el submit este deshabilitado (`Products.vue:729,342-347`). No afecta autorizacion (servidor rechaza) ni integridad.

### Observaciones (sin accion obligatoria)
- `origin` es editable a `channel|import` por cualquier gestor: es una etiqueta de origen, no otorga canales ni privilegios y queda auditada. El packet lista `origin` como campo editable, por lo que se considera conforme; conviene aclararlo en la guia de ChatGPT.
- Typo cosmetico en nombre de prueba: `test_agrega_varante_a_producto_variable_con_atributos` (`ProductVariantTest.php:11`).
- `GUIA-HTML-ESTADO.md` y `ROADMAP-HTML-ESTADO.md` solo actualizan metadatos/estado y handoff; `public/docs/index.html` y `resources/roadmap/index.html` permanecen intactos, coherente con la regla 17/19.

## 5. Limites de esta revision

- No se ejecuto MariaDB/MariaDB concurrente, FULLTEXT ni medicion de latencia: `maria=NOT_RUN`.
- No se abrio ni modifico ninguna base remota: `remote_db=UNTOUCHED`.
- No se ejecuto navegador real ni revision visual/responsive con dispositivo: validacion de UI por codigo, typecheck y build.
- No se ejecutaron migraciones, seeders, commits, push ni despliegue.
- Esta revision no declara `VERIFIED`; el dictamen tecnico final corresponde a la revision externa de ChatGPT sobre SHA publicado y CI.
---

# ADDENDUM 1 - Re-revision del ciclo correctivo H1..H6 (2026-11-17)

- Autor: `stockflash-reviewer` (QA independiente; no implemento, no corrijo, no hago commit/push).
- Motivo: el implementador aplico un ciclo correctivo sobre el mismo arbol de trabajo para resolver las 6 observaciones no bloqueantes del dictamen anterior (seccion 4). Este addendum no reemplaza ni borra el dictamen previo.
- Veredicto final tras el ciclo correctivo: **APROBADO** (0 bloqueantes; H1..H6 resueltos; 0 hallazgos nuevos).
- `maria = NOT_RUN`; `remote_db = UNTOUCHED`; no se ejecutaron migraciones; no se declara VERIFIED.

## A1.1 Estado exacto re-revisado

- Base sin cambios: `HEAD = 7889f5e990594bcdb8676060c915b5c9fb0305d1` = `origin/main` (verificado con `git rev-parse HEAD` y `git rev-parse origin/main`).
- Arbol de trabajo: siguen los mismos 5 tracked modificados; los no rastreados ahora incluyen este dictamen, 7 reportes `CATPROD-001-VERIFY-*`, `docs/work/CATPROD-001-NOTES-20261117.md` actualizado y los archivos del cambio. Sin migraciones, seeders ni archivos nuevos fuera de esa lista.
- `database/`, `config/`, `composer.json`, `package.json`, `package-lock.json`: sin cambios (`git status --porcelain -- database/ config composer.json package.json package-lock.json` vacio).
- Protegidos sin cambios: `AGENTS.md`, `opencode.json`, `docs/governance/CONSTITUTION.md`, `docs/product`, `docs/branding`, `docs/harness/CONTEXTO-CHATGPT.md`, `public/docs/index.html`, `resources/roadmap/index.html` (`git diff --quiet HEAD -- ...` => sin cambios).
- Alcance del ciclo correctivo confirmado: exactamente los 10 archivos declarados cambiaron; los 15 archivos restantes del cambio conservan el mismo hash de objeto git que en la revision anterior (`UNCHANGED_OUTSIDE_CORRECTIVE_SET=15/15`).
- Trazabilidad: esta re-revision genero `docs/work/CATPROD-001-VERIFY-20261117-135031.md` (salida propia de `scripts/sf_verify.py`).

### Hashes de los archivos corregidos (git hash-object, primeros 12 hex)

| Archivo | Hash anterior | Hash corregido | Bytes |
| --- | --- | --- | --- |
| app/Http/Requests/Catalog/ProductRequest.php | ff01f3832361 | f26cff16c97b | 12107 |
| app/Http/Requests/Catalog/ProductVariantRequest.php | 6509b13b83bb | d95204bf89e4 | 6813 |
| app/Support/Catalog/ProductCatalogService.php | 841016c21d47 | 33df22c41f5e | 25121 |
| app/Support/Catalog/ProductPresenter.php | 3ea02d16ce2b | 5f4bcec45477 | 5538 |
| resources/js/Pages/Catalog/Products.vue | 4d0ef9ebf0bf | 9bfc4f35245e | 60384 |
| tests/Feature/Catalog/ProductCrudTest.php | d68cce5281a9 | d5a622d3704c | 14075 |
| tests/Feature/Catalog/ProductVariantTest.php | b441cd5b7632 | a3efc8e31b26 | 9640 |
| tests/Feature/Catalog/ProductBarcodeTest.php | 99ff9408dd8c | d6fba697088f | 6826 |
| tests/Feature/Catalog/ProductSearchTest.php | 9ac03fe54cf8 | 06cc91694635 | 4445 |
| docs/work/CATPROD-001-NOTES-20261117.md | caf030775245 | 75c2726b8552 | 9845 |

## A1.2 Verificacion H1..H6

**H1 - RESUELTO.** `cost_price` con `catalog.view_cost` usa `sometimes|required|numeric|decimal:0,4` (`ProductRequest.php:138-149` para creacion, `ProductVariantRequest.php:71-82` para variante), por lo que `null` o `""` (que `prepareForValidation` convierte a `null`) fallan `required` y responden 422 con mensaje es-CL (`ProductRequest.php:215`, `ProductVariantRequest.php:134`). El servicio refuerza: al crear usa 0 si el costo no viene (`ProductCatalogService.php:490-494`) y al actualizar omite `null`/vacio para no tocar la columna NOT NULL (`ProductCatalogService.php:536-539`). Pruebas nuevas: 422 sin escritura con `null` y `''` en creacion y edicion, verificando que el costo previo `500.0000` se conserva (`ProductVariantTest.php:114-136`); sin `catalog.view_cost` ambos payloads se ignoran y el costo existente se preserva (`ProductVariantTest.php:138-158`).

**H2 - RESUELTO.** Limites alineados al esquema: `price`/`min_price` DECIMAL(14,2) con max `999999999999.99` (12 enteros) y `cost_price` DECIMAL(14,4) con max `9999999999.9999` (10 enteros) en ambos Form Requests (`ProductRequest.php:117,125,147`, `ProductVariantRequest.php:54,58,80`), con mensajes es-CL de maximo (`ProductRequest.php:214,218`, `ProductVariantRequest.php:126,132,137`). Prueba nueva rechaza 11 enteros en costo y 13 enteros en precio/minimo, en creacion y en actualizacion, sin modificar el costo previo (`ProductCrudTest.php:191-218`).

**H3 - RESUELTO.** Orden estable por clave multiple: `sortBy([['is_primary','desc'],['id','asc']])` (`ProductPresenter.php:110-117`), por lo que el primario queda primero y el resto por id. Prueba nueva de endpoint verifica `barcodes.0` = primario y `barcodes.1` = secundario cuando el primario se creo despues (`ProductBarcodeTest.php:57-81`).

**H4 - RESUELTO.** Se elimino la asercion tautologica (`name => 'Inventado'` ya no existe en la suite). La prueba envia payload extra real (`percentage`, `invented_column`, `current_stock`, `branch_id`, `last_cost`) y verifica: una sola fila de producto y variante, `origin` intacto, y `assertArrayNotHasKey` para `percentage`, `invented_column`, `current_stock` y `branch_id` en producto y `percentage`, `invented`, `current_stock` en variante, ademas de `last_cost` nulo y precio intacto (`ProductCrudTest.php:52-90`). Evidencia real de no persistencia y no elevacion.

**H5 - RESUELTO.** La prueba se renombro a `test_listado_incluye_skus_y_tags_del_producto` y declara explicitamente "Verifica la forma del payload; no mide el numero de consultas SQL" (`ProductSearchTest.php:97-99`), sin afirmar una medicion N+1 no realizada. El N+1 sigue evitado estructuralmente por `with`/`withCount` (`ProductController.php:44-51`, sin cambios).

**H6 - RESUELTO.** El formulario "Nueva variante" se oculta en producto `simple` salvo al editar la variante existente (`Products.vue:282-286`); los formularios de codigo y de producto se ocultan con `read_only` (`Products.vue:354-355`, `Products.vue:385`), y el formulario de variante tambien (`Products.vue:283`). Las acciones de fila quedan deshabilitadas en solo lectura (`Products.vue:265,273-275`). No se perdio accesibilidad: se conservan `role=status|alert`, `aria-labelledby`, `aria-label`, `aria-current`, textos es-CL y estilos `:focus-visible` para enlaces, botones, inputs y paginacion (`Products.vue`, 4 reglas `focus-visible`). `npm run typecheck` y `npm run build` PASS en gates.

Sin regresiones detectadas en los criterios 1..11 del dictamen original: rutas/middlewares, RBAC sin `branch_id` de request, costos protegidos, transacciones/auditoria atomica, unicidad y 422, RN-70, soft delete y catalogo sin stock siguen iguales (los archivos que los implementan no fueron tocados).

## A1.3 Comandos ejecutados y resultados

| Comando | Resultado |
| --- | --- |
| `php artisan test tests/Feature/Catalog` | **105 passed / 1617 assertions** (18.5 s) |
| `php artisan test` | **465 passed / 3999 assertions** (74.5 s) |
| `php artisan test tests/Feature/Auth` | 45 passed / 108 assertions |
| `php artisan test tests/Feature/Users` | 79 passed / 564 assertions |
| `php artisan test tests/Feature/Core` | 97 passed / 529 assertions |
| `php artisan test tests/Feature/Branches` | 53 passed / 388 assertions |
| `php artisan test tests/Feature/Settings` | 59 passed / 640 assertions |
| `py -3 scripts/sf_verify.py CATPROD-001` | **GATES_PASS 19/19** (CPRD1..CPRD19 PASS) -> `docs/work/CATPROD-001-VERIFY-20261117-135031.md` |
| `git rev-parse HEAD` / `origin/main` | `7889f5e990594bcdb8676060c915b5c9fb0305d1` en ambos |
| `git diff --quiet HEAD -- <protegidos>`; `git status --porcelain -- database/ config composer.json package.json` | protegidos intactos; `database/` y dependencias sin cambios |
| `git hash-object` de los 15 archivos no corregidos | identicos a la revision anterior (15/15) |

Delta respecto a la revision anterior: +4 pruebas y +59 aserciones (Catalog 101/1558 -> 105/1617; suite 461/3940 -> 465/3999), coherente con las 4 pruebas nuevas y el renombre de H5. `docs/work/CATPROD-001-NOTES-20261117.md` declara exactamente estos conteos, `maria=NOT_RUN`, `remote_db=UNTOUCHED` y el SHA base correcto (confrontado y coincidente).

## A1.4 Hallazgos del addendum

- Bloqueantes: **ninguno**.
- Nuevos no bloqueantes: **ninguno**. Las correcciones no introdujeron cambios de contrato, permisos, esquema, dependencias ni rutas.
- Observaciones cosmeticas que persisten del dictamen original (sin accion obligatoria): typo en `test_agrega_varante_a_producto_variable_con_atributos` (`ProductVariantTest.php:11`) y `origin` editable a `channel|import` como etiqueta sin privilegios (conforme al packet, ya auditado).

## A1.5 Limites

- No se ejecuto MariaDB real, concurrencia simultanea, FULLTEXT ni medicion de latencia: `maria=NOT_RUN`.
- No se abrio ni modifico ninguna base remota: `remote_db=UNTOUCHED`.
- No se ejecuto navegador real; la validacion de UI es por codigo, typecheck y build.
- No se ejecutaron migraciones, seeders, commits, push ni despliegue.
- Este addendum no declara `VERIFIED`; el dictamen tecnico externo y el registro final corresponden a ChatGPT sobre el SHA publicado y su CI.