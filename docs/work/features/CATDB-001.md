# CATDB-001 — Esquema base de Catálogo P0 (Parte 2 §5.1–5.6)

status: VERIFIED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Por qué ahora

Se completaron dependencias CORE-03/04/05/06/08/09/11/13. La etapa R3 contempla **catálogo base** antes de la gestión de Bodega (R4) y POS (R6). No hay tablas de catálogo en migrations actuales. Esta ola construye el esquema P0 existente en el contrato; la siguiente ola será el CRUD de catálogo y variantes con UI, no se inicia Bodega/POS aún. No se piden decisiones nuevas al propietario: v2.2 ya especifica las tablas, tipos y relaciones.

## Fuente exacta / alcance cerrado

- Parte 1 §8.2 CAT-01, CAT-02, CAT-03, CAT-08 y §4.2 `catalog → core`; Parte 2 §1.2 lote 04 y §5.1–5.6.
- Crear las **11 tablas P0** de §5.1–5.6: `tax_rates`, `categories`, `brands`, `tags`, `attributes`, `attribute_values`, `products`, `product_variants`, `product_barcodes`, `product_tag`, `product_variant_attribute_values`. Mantener este orden de creación y revertirlo en orden inverso. No crear aún `combo_components`, `promotions`, `promotion_conditions`, `price_lists`, `price_list_items` (P1/fases posteriores) ni las otras tablas de R4.
- Tipos, longitudes, valores predeterminados, `ENUM`, restricciones UNIQUE, índices y FK (CASCADE/SET NULL/RESTRICT) deben coincidir **literalmente con las tablas §5.1–5.6**; no inventar columnas, enum members o claves. Respetar convenciones globales Parte 2 §0.1 (id, timestamps cuando apliquen, SD `deleted_at` para categories/brands/products/product_variants, dinero DECIMAL(14,2), costos DECIMAL(14,4), porcentajes DECIMAL(5,2)).
- `products.name` requiere FULLTEXT para MariaDB (Parte 2 §5.4 y §15.2). Usar creación condicional por driver para mantener suite SQLite, sin falsificar soporte FULLTEXT en SQLite; tests de MySQL/MariaDB y EXPLAIN quedan pendientes hasta contar con ambiente **local/aislado explícitamente autorizado**. No depender de SQL MariaDB específico en dominio.

## Implementación autorizada

1. Implementar migración o migraciones Laravel a continuación de las 20 migraciones CORE existentes, con `up()/down()` reversibles en una BD de prueba vacía. Referenciar el lote 04 aprobado; no editar ni reescribir migraciones 000001–000020, `docs/product` ni estructura base ya verificada.
2. Cumplir constraints exactas: `categories.parent_id` self FK RESTRICT, `slug` categorías/marcas/tags único, `product_variants.sku` único, `product_barcodes.barcode` único, valores únicos `(attribute_id,value)`, pivotes PK compuestas, `products.category_id/brand_id/tax_rate_id` y `product_variants.tax_rate_id` SET NULL, variante→producto y barcodes→variante CASCADE. Mantener soft delete en tablas marcadas SD sin interpretar que `unique` se libera por baja lógica.
3. Probar en SQLite de test/CI (DB `:memory:`): `migrate`, `migrate:rollback` seguro en entorno efímero, 11 tablas existentes, columnas/enum/defaults/índices, inserciones válidas y errores reales por UNIQUE/FK, borrados FK RESTRICT/CASCADE/SET NULL y soft delete. No afirmar que `enum` y FULLTEXT se comportan igual en SQLite que MariaDB; documentar lo que solo se verificó estáticamente y lo pendiente.
4. **Seguridad operativa — prohibición absoluta:** no ejecutar `php artisan migrate`, rollback, seeders ni SQL contra la BD DEV remota ya existente ni staging/producción, no leer `.env`, no usar credenciales, no regenerar tablas con `migrate:fresh` fuera de bases **efímeras y comprobadas** de testing. Prueba SQLite local en memoria y CI alcanza para reportar implementación técnica con limitación explícita de MariaDB pendiente; ninguna creación real de tablas se autoriza en infraestructura ajena a esas pruebas.
5. Sin productos/ventas de demostración, sin datos fiscales reales, sin seed de impuestos no aprobado expresamente, sin negocio de inventario/stock, sin UI, API, endpoints, permisos o PlanGuard; **no contar ni bloquear variantes por licencia en este packet** (se hará en la feature funcional correspondiente). No bibliotecas, scripts, nuevos agentes ni pipelines. Evitar sobreingeniería.
6. Añadir gates `CATDB-001` declarativos al `GATES_BY_PACKET` existente: estado APPROVED/VERIFIED y activo; 11 tablas/fks/índices verificados por tests, SQLite local, tests de no alteración del lote 01, contratos, PHP suite, npm typecheck y build, protected paths, diff check. QA independiente y publicación AUTO_GIT al aprobar; comprobar CI del SHA publicado. Reportar expresamente `maria=NOT_RUN` y `remote_db=UNTOUCHED`. No requiere nuevo texto en la guía HTML porque no hay UI; ChatGPT actualizará seguimiento editorial cuando exista pantalla.

## Criterios EARS

- CUANDO se migra una BD aislada vacía, EL SISTEMA DEBE crear exactamente las 11 tablas de Catálogo P0 con los tipos, claves e índices aprobados, sin modificar otras tablas de CORE.
- CUANDO se intenta duplicar SKU, código de barras o slug, LA BD DEBE impedirlo con sus índices únicos.
- CUANDO se eliminan registros, LA BD DEBE aplicar las reglas de FK del contrato y conservar los registros marcados SD por baja lógica.
- CUANDO se revierte la migración P0 en BD efímera, DEBEN desaparecer sus tablas sin tocar el núcleo.
- CUANDO no existe MariaDB aislada autorizada, OpenCode DEBE dejar sin ejecutar migraciones remotas y reportar que la compatibilidad real con MariaDB sigue pendiente predespliegue.

## Límites

No modificar BD remota ni desplegar; no cambiar contratos ni esquema P0, no inventar reglas de impuestos ni defaults comerciales adicionales, no implementar etapa R4, combos/promociones/listas de precios P1, fiscal, UI o catalog CRUD todavía. No modificar `public/docs/index.html`, `resources/roadmap/index.html` o `docs/harness/CONTEXTO-CHATGPT.md` (ChatGPT). No pedir nueva aprobación administrativa; SOLO escalar preguntas de negocio materiales NUEVAS, con opciones concretas/recomendación.

## Cierre externo por ChatGPT — 2026-11-17

status: VERIFIED
reviewer_externo: ChatGPT
registro: `docs/work/CATDB-001-CLOSURE-20261117.md`
code_sha: `93fe5de92e63ec4cc8c8bd53df5001fab0382774`
gates: 15/15 PASS
php_tests: 383 passed / 2929 assertions
qa: `docs/work/CATDB-001-REVIEW-20261116-R2.md` APROBADO, 0 bloqueantes, B1 MariaDB FULLTEXT cerrado
ci: PHP/Node y contratos 2/2 SUCCESS exact SHA
limites: MariaDB real, FULLTEXT efectivo y EXPLAIN NO ejecutados. Sin CRUD ni UI; remote_db UNTOUCHED; sin despliegue.
