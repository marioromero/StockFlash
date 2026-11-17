# CATPROD-001 - Revision QA independiente R2 (ciclo correctivo H-01/H-02/H-03)

- Autor: `stockflash-reviewer` (revision independiente). No implemento, no corrijo codigo de implementacion, no hago commit/push, no ejecuto migraciones ni toco BD remota. No declaro `VERIFIED`.
- Feature Packet: `docs/work/features/CATPROD-001.md` (APPROVED, AUTO_GIT, ONE_SMALL_WAVE, `new_business_decisions: NONE`).
- Revision externa origen: `docs/work/CATPROD-001-EXTERNAL-REVIEW-20261117.md` (H-01, H-02, H-03 obligatorios).
- Veredicto: **APROBADO** (0 hallazgos bloqueantes; 1 hallazgo medio diferible y 2 menores).
- Limites: `maria = NOT_RUN`; `remote_db = UNTOUCHED`; sin navegador real; pruebas solo SQLite `:memory:`.

## 1. Estado exacto revisado

- `git rev-parse HEAD` = `8be4e8157905cf4bc4f482e5d5e837547b8bae16`; `origin/main` = mismo SHA.
- Arbol: 9 archivos rastreados modificados y 6 sin seguimiento (5 del cambio + 1 reporte de gates previo), mas el reporte de gates generado por esta revision (ver 2).
- Fingerprints `git hash-object` coinciden exactamente con los informados por el coordinador (no hubo divergencia):

| Archivo | SHA-1 |
| --- | --- |
| app/Http/Controllers/Catalog/ProductController.php | `98e8f5f58059dc567d3dff5da535bdb965b9e62e` |
| app/Http/Requests/Catalog/ProductRequest.php | `a38e777f3a63cf9de6daaf76e696de65eefedf8b` |
| app/Support/Catalog/ProductCatalogService.php | `ada3e2b2af2ad04333b30057a844fd657a552059` |
| app/Support/Catalog/ProductPresenter.php | `d38a1f74cd2a3a3929e734284d4dd0e00d0b6770` |
| app/Support/Catalog/CatalogPricing.php (nuevo) | `9d250f4a15ef49e733c78375ed22a94c88036e67` |
| resources/js/Pages/Catalog/Products.vue | `2a9ded6098a1309188cd9a77a201ddef259a6fa3` |
| tests/Feature/Catalog/ProductOriginTest.php (nuevo) | `84df06091cb15ac41dd4241fbe18e50d1d5156da` |
| tests/Feature/Catalog/ProductPricingTest.php (nuevo) | `2c8546a6a1df0b774f83b001ab251192f374a60f` |

- Sin cambios en `database/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `.agents/` ni `scripts/` (este ultimo incluye `scripts/sf_verify.py` con hash `68f227e659c72be53c48bda3a2f20fe9b5d61099`, sin tocar).
- Protegidos intactos: `AGENTS.md`, `opencode.json`, `docs/governance/CONSTITUTION.md`, `docs/product/`, `docs/branding/`, `docs/harness/CONTEXTO-CHATGPT.md`, `public/docs/index.html`, `resources/roadmap/index.html` (`git status --porcelain -- ...` vacio).
- Trazabilidad: mi ejecucion de `sf_verify.py` genero `docs/work/CATPROD-001-VERIFY-20261117-144959.md`. Este dictamen es el unico archivo que redacte; no modifique codigo ni docs de implementacion.

## 2. Comandos ejecutados y resultados (ejecutados por mi, no reportados)

| Comando | Resultado |
| --- | --- |
| `git status --short` + `git ls-files --others --exclude-standard` | 9 modificados + 6 untracked; sin cambios en `database/` ni dependencias |
| `php artisan test tests/Feature/Catalog` | **116 passed / 1810 assertions** (22.33 s) |
| `php artisan test` | **476 passed / 4192 assertions** (88.79 s) |
| `npm run typecheck` | PASS (`vue-tsc --noEmit`, sin errores) |
| `npm run build` | PASS (796 modulos; `app-D78l3EQq.js` 391.66 kB; 3.07 s) |
| `py -3 scripts/sf_verify.py CATPROD-001` | **GATES_PASS** (19/19, exit 0) -> `docs/work/CATPROD-001-VERIFY-20261117-144959.md` |
| `git diff HEAD --check` | exit 0 (solo warnings LF/CRLF de Git en Windows) |
| `git status --porcelain -- database composer.json composer.lock package.json package-lock.json AGENTS.md opencode.json docs/governance docs/product docs/branding docs/harness/CONTEXTO-CHATGPT.md public/docs/index.html resources/roadmap/index.html .agents scripts` | vacio |
| `php artisan tinker --execute` (CatalogPricing, solo lectura) | verificado calculo neto 119/100 -> margen 0 y fallback de tasa (seccion 4) |

Los conteos reproducidos coinciden con `docs/work/CATPROD-001-NOTES-20261117.md` y con el gate `CATPROD-001-VERIFY-20261117-144207.md`.

## 3. Checklist de las correcciones obligatorias

### H-01 - Integridad del origen: CUMPLE

- Alta manual: `ProductCatalogService::productCreateAttributes()` fija literalmente `'origin' => 'pos'` y no lee el payload (`app/Support/Catalog/ProductCatalogService.php:443-449`).
- Edicion: `productUpdateAttributes()` ya no incluye `origin` (`ProductCatalogService.php:455-470`); el atributo existente se preserva, incluido `channel`/`import` preexistente.
- Validacion: `ProductRequest` ya no tiene regla para `origin` (`app/Http/Requests/Catalog/ProductRequest.php:67-105`), por lo que un payload `origin=channel|import|api` cae en el allowlist como campo extra ignorado. El mensaje `origin.in` fue eliminado.
- UI: el selector editable desaparecio; el origen solo se muestra como texto informativo (`resources/js/Pages/Catalog/Products.vue:150`, `originLabel` en `:1373`). `productPayload()` ya no envia `origin`.
- Sin cambios de modelo, esquema ni integraciones: `Product.php`, migraciones y rutas sin diff; no se crean canales ni publicaciones.
- Evidencia de pruebas: `tests/Feature/Catalog/ProductOriginTest.php` (3 tests) cubre payload manipulado en alta con `channel`, `import` y `pos`, edicion con origen externo preexistente preservado y edicion POS que intenta `channel`. `ProductCrudTest.php` mantiene la asercion `origin === 'pos'` tras payload con columnas extra.

### H-02 - Margen/markup sobre precio neto RN-25: CUMPLE (con N-01 diferible)

- Configuracion: `CatalogPricing::pricesIncludeTax()` lee `system_configs.prices_include_tax` (CFG-003) y aplica el default contractual `true` si la fila falta (`CatalogPricing.php:20-33`).
- Tasa efectiva: `resolveTax()` aplica variante -> producto -> `is_default`; una tasa `is_exempt` fuerza `rate=0`; la tasa se acota a 0..100 y se protege con `is_finite` (`CatalogPricing.php:42-76`).
- Formulas: `netPrice = price/(1+t)` si el precio incluye impuesto y no es exento; `margin = neto - costo`; `margin_percent = (neto-costo)/neto`; `markup_percent = (neto-costo)/costo` (`CatalogPricing.php:83-144`). Coincide con Parte 1 RN-25 y Parte 2 5.5 (costo neto).
- Denominadores: `margin_percent = null` si `neto = 0`; `markup_percent = null` si `costo = 0`; sin INF/NAN (`is_finite`, `finiteAmount`). Precio y costo son no negativos por validacion.
- Exposicion: `ProductPresenter::variant()` retorna la base sin `cost_price`, `last_cost`, `net_price`, `margin`, `margin_percent`, `markup_percent`, `effective_tax_rate` ni `tax_source` cuando `!$canViewCost` (`ProductPresenter.php:138-166`). `detail()` solo carga tasas y config si hay permiso (`:53-54`). Auditoria agrega costo solo con `catalog.view_cost` (`ProductCatalogService.php:710-723`). La UI muestra neto/margenes solo bajo `can_view_cost` (`Products.vue:229-255`).
- Pruebas: `ProductPricingTest.php` (8 tests) cubre inclusivo 119/100 -> 0%/0% (el ejemplo exacto de la revision externa), exclusivo 100/80 -> 20%/25%, herencia producto y predeterminada, prioridad de variante, exento, costo 0, precio 0, redondeo a 4 decimales (10/8 -> neto 8.4034, 4.80%/5.04%) y actor sin `view_cost` con claves ausentes. `ProductAuthorizationTest` verifica props de costo/margen permitidas. Los tests son de comportamiento real (HTTP + Inertia), no triviales.

### H-03 - Calculadora CAT-04: CUMPLE

- Ubicacion: bloque de calculadora en edicion de variante (`Products.vue:324-351`) y en cada variante inicial del alta de producto (`:527-549`), ambos bajo `v-if="can_view_cost"`.
- Matematicas: `suggestedPriceFor()` deriva `neto = costo/(1-margen)` o `neto = costo*(1+markup)` y convierte a `price` multiplicando por `(1+tasa)` solo si `prices_include_tax` y no exento (`Products.vue:1268-1311`); usa la misma resolucion de tasa que el servidor (`:1222-1265`).
- Rechazos: costo vacio/0/negativo, objetivo vacio/negativo, margen >= 100%, objetivo > 10000%, resultados no finitos, negativos o sobre `999999999999.99`; muestra error accesible (`role="alert"`).
- Es sugerencia: solo reemplaza el campo editable `price`; no se envia ningun campo de calculadora (payload de variante sin `calculator_*`) y el servidor valida el precio final con `decimal:0,2`, `min:0` y maximo contractual (`ProductVariantRequest.php:54`; `ProductRequest.php:117`).
- Sin estructura nueva: no hay columnas, tablas, rutas, API ni reglas de validacion nuevas; solo props derivadas y UI. No hay migraciones ni cambios en `routes/`.

## 4. Area explicita: tasa referenciada desactivada (`is_active = false`)

Comportamiento actual verificado: `ProductPresenter::activeTaxRates()` carga **solo** tasas activas (`ProductPresenter.php:172-186`); `CatalogPricing::resolveTax()` recibe ese mapa y, si el id referenciado no esta, cae en `source='default'` (`CatalogPricing.php:47-60`). La UI replica lo mismo con `props.catalogs.tax_rates` (`Products.vue:1222-1240`).

Evidencia ejecutada (tinker, sin escritura): `resolveTax(99, 2, [1 => default 19%, 2 => 10.50%])` devuelve `["id" => 1, "rate" => 19.0, "source" => "default"]`; es decir, con variante apuntando a una tasa ausente y producto apuntando a la tasa 2, se elige la predeterminada y se omite el nivel producto.

Alcanzabilidad: CATMAST permite desactivar una tasa referenciada (`CatalogMastersController::updateTaxRate` no valida referencias; solo `destroyTaxRate` bloquea el borrado, lineas 145-155). Por tanto el estado es alcanzable: producto/variante conserva `tax_rate_id` inactivo tras la desactivacion.

Efectos:
- El margen/markup mostrado se calcula con la tasa predeterminada, no con la tasa referenciada ni con la del producto. Si la predeterminada es exenta y el precio guardado incluia impuesto, el margen puede sobreestimarse en el monto del impuesto (mismo orden de error que motivo H-02).
- El caso mas claramente incorrecto es variante con tasa inactiva y producto con tasa activa: el orden contractual "variante -> producto -> predeterminada" se rompe porque se salta producto.
- Consecuencia UX adicional: al editar una variante cuya tasa esta inactiva, el `<select>` no tiene esa opcion (solo activas) y muestra "Heredar del producto / predeterminada" mientras el modelo conserva el id inactivo; guardar sin reasignar responde 422 (correcto por contrato, pero confuso) y la calculadora usa la tasa predeterminada.
- No hay impacto en datos persistidos, ventas, stock, caja ni fiscal: el valor es derivado en lectura y no existe aun consumidor POS/fiscal.

Clasificacion: **NO bloqueante para H-02 (mejora diferible)**. Argumento: el contrato solo define la cadena para referencias vigentes y el packet exige tasas activas al asignar; el estado requiere una accion administrativa posterior en otro maestro (CATMAST), no es alcanzable desde esta pantalla; no hay datos persistidos ni transaccion afectada; y la matriz de aceptacion exigida por H-02 (inclusivo/exclusivo, exento, herencia, variante, cero, redondeo, permisos) pasa completa. Recomendacion obligatoria antes de que POS/fiscal reutilice `CatalogPricing`: (a) resolver la tasa referenciada aunque este inactiva, o al menos caer a producto antes de predeterminada; (b) exponer `tax_source`/aviso en el detalle; (c) agregar test de tasa inactiva. Si el propietario define que una tasa desactivada deja de aplicar retroactivamente, esa politica debe quedar en ADR/documentacion, no inferirse.

## 5. Hallazgos

### Bloqueantes
Ninguno.

### No bloqueantes

**N-01 (MEDIA, diferible) - Resolucion de tasa con referencia inactiva omite el nivel producto y usa la predeterminada.**
Evidencia: `ProductPresenter.php:172-186` (filtro `is_active`), `CatalogPricing.php:47-60` (fallback directo a default), `Products.vue:1222-1240`; prueba manual en seccion 4; `CatalogMastersController.php:117-141` permite desactivar tasas referenciadas. Impacto: margen/markup potencialmente incorrecto en una configuracion alcanzable; sin efecto en datos ni permisos. Accion: corregir orden de fallback (variante -> producto -> predeterminada) y agregar prueba; decidir/documentar semantica de tasa desactivada antes de POS/fiscal. Ver argumento de no bloqueo en seccion 4.

**N-02 (MENOR) - Brecha de pruebas y duplicacion de la matematica de la calculadora.**
`ProductPricingTest.php` prueba `CatalogPricing::priceFromNet()` en servidor, pero no hay prueba automatizada de la logica duplicada en Vue (`suggestedPriceFor`, `Products.vue:1268-1311`) ni del caso de tasa inactiva. Hoy ambas formulas coinciden (revision manual), pero pueden divergir sin deteccion. No afecta la aceptacion (typecheck/build requeridos pasan). Sugerencia: prueba unitaria JS o extraer contrato compartido en una feature de mantenimiento.

**N-03 (MENOR) - Observabilidad de la tasa efectiva en el detalle.**
La tabla de detalle muestra tasa y neto (`Products.vue:246-255`) pero no `tax_source`; un usuario con `catalog.view_cost` no distingue si la tasa mostrada es de variante, producto, predeterminada o `none`, lo que oculta el fallback de N-01. Ademas `pricing.prices_include_tax` se envia a todo usuario con `catalog.view` (`ProductController.php:239-242`); no es costo ni PII y se considera aceptable, pero conviene documentarlo. Observacion adicional sin accion: `pricesIncludeTax()` interpretaria `config_value=''` como `false`; CFG-003 escribe `'1'`/`'0'`, por lo que no es alcanzable por la UI.

## 6. Calidad de tests y regresiones

- Pruebas nuevas no triviales: `ProductOriginTest` (payload manipulado, preservacion de origen externo) y `ProductPricingTest` (formulas, herencia, exencion, ceros, redondeo, permisos). Ambas usan requests reales y aserciones Inertia/DB.
- Brecha declarada en N-02: falta el caso de tasa inactiva y no hay prueba JS de la calculadora.
- Regresiones verificadas por suite completa: simple = 1 variante / variable = N (request + servicio), barcode primario unico, atributos/tags, RN-70, CATDB/CATMAST/AUTH/USERS/COREMOD/CORELIC y Settings (476/4192 en total). No se modificaron rutas, permisos ni middlewares.

## 7. Limites de esta revision

- `maria = NOT_RUN`: no se ejecuto MariaDB real, FULLTEXT, concurrencia ni latencia; SQLite `:memory:` exclusivamente.
- `remote_db = UNTOUCHED`: no se abrio ni modifico ninguna BD remota; no se ejecutaron migraciones ni seeders.
- Sin navegador real: no se valido comportamiento visual/responsive ni la interaccion tactil de la calculadora; solo typecheck/build y revision estatica.
- No se consulto CI de GitHub ni se declara `VERIFIED`; la publicacion y el CI quedan fuera de este dictamen.
- No se modifico codigo, contratos, protegidos, HTML editorial ni roadmap.

## 8. Veredicto

**APROBADO** - 0 hallazgos bloqueantes. N-01 (media, diferible) debe resolverse antes de que POS/fiscal consuma `CatalogPricing`; N-02/N-03 son menores. No equivale a `VERIFIED`.