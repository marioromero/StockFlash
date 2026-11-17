# CATPROD-001 — Revisión externa ChatGPT (2026-11-17)

- Feature Packet vigente: `docs/work/features/CATPROD-001.md`, `APPROVED`, `AUTO_GIT`, derivada de requisitos ya aprobados; **sin nuevas decisiones comerciales**.
- Código revisado: `a2c60730b4dc0c3d99a8086db71a218690493e3f` publicado en `main`.
- Handoff: SFH1 issue #24, `i=002`, QA OC `APROBADO`, gates reportados `19/19 PASS`, suite local `465 passed / 3999 assertions`.
- Dictamen externo: **CORRECCIONES REQUERIDAS** sobre el alcance ya autorizado. **NO VERIFIED**.
- Límites: SQLite `:memory:`/CI únicamente. Sin acceso a BD remota, sin migraciones/seeders, sin despliegue; HTML editorial de ChatGPT permanece protegido.

## H-01 — Integridad del origen: el administrador puede atribuir un origen externo ficticio (requerido)

**Evidencia:** `resources/js/Pages/Catalog/Products.vue:434-440,962-977` deja seleccionar `pos|channel|import`; `app/Http/Requests/Catalog/ProductRequest.php` valida los tres valores y `app/Support/Catalog/ProductCatalogService.php:432-477` los persiste en altas y cambios. El contrato Parte 2 §5.4 describe `origin` como «Dónde nació». El packet CATPROD-001 establece explícitamente «El `origin` no puede elevar privilegios de canales ni fingir creación por integraciones externas».

**Riesgo:** un usuario con `catalog.manage` puede crear manualmente un artículo indicando origen canal/importación, o alterar retrospectivamente la procedencia en una edición. Aunque no conceda permisos, la información de procedencia queda falsa.

**Corrección:** en el CRUD manual de `/catalog/products`, derivar `origin=pos` en el servidor al crear, sin confiar en el payload; preservar el origen existente al editar y no ofrecer selector modificable en la UI. No alterar modelos, esquema ni integraciones futuras; una vía real de canal/importación podrá establecer su propia procedencia en su feature autorizada. Pruebas de payload manipulado (alta y edición) con `channel/import` y preservación de un producto preexistente cuyo origen sea externo.

## H-02 — Margen/markup actual calculado sobre precio sin descontar impuesto (requerido)

**Evidencia:** `app/Support/Catalog/ProductPresenter.php:124-134` calcula diferencia `price-cost` y `markup_percent=((price-cost)/cost)*100` directamente desde `product_variants.price`. Según Parte 2 §5.5, ese campo puede incluir impuesto cuando `prices_include_tax=true`. Parte 1 **RN-25** exige `margen%=(precio_neto-costo)/precio_neto` y `markup%=(precio_neto-costo)/costo`; costo es neto. Ejemplo reproducible de cálculo: precio configurado con impuesto 19% = 119, costo neto = 100 -> precio neto 100, margen 0% y markup 0%; implementación arroja markup 19%. No hay evidencia de cobertura de conversión según configuración general y tasa fiscal de variante/producto.

**Corrección:** calcular el precio neto en función de `prices_include_tax` de CFG-003, la tasa aplicable (variante → producto → predeterminada según contrato) y exención; calcular margen monetario neto, `margin_percent` y `markup_percent` según RN-25. No reinterpretar importe con impuesto como rentabilidad neta. Manejar denominadores 0, costo 0, precios bajos y redondeos; no exponer ninguno de estos datos sin `catalog.view_cost`. Tests con precios inclusivos y exclusivos, exento, herencia de tasa, variantes y actor sin permiso. No implementar emisión DTE/SII.

## H-03 — Falta la calculadora de precio por margen o markup P0 de CAT-04 (requerido)

**Evidencia:** contrato aprobado Parte 1 §8.2, `CAT-04`: «Costo neto; calculadora de precio por margen o markup; margen visible solo con `catalog.view_cost`». CATPROD-001 incluye CAT-04 en alcance. `resources/js/Pages/Catalog/Products.vue` solo muestra margen monetario/markup de lectura en detalle; alta/edición de variantes recibe precio y costo manuales, pero no ofrece cálculo por % objetivo.

**Corrección:** añadir calculadora comprensible en alta/edición de variante (y alta inicial de producto), accesible solo con `catalog.view_cost`: seleccionar margen% o markup% objetivo, derivar precio base usando **costo neto y precio neto**, luego convertir a precio guardado según `prices_include_tax` y tasa aplicable. Evitar divisiones imposibles (margen objetivo >=100%, costos/cero, redondeos/precisión). El resultado es una sugerencia/valor del campo `price`, no una regla automática de venta; validarlo en servidor al guardar. Agregar pruebas matemáticas representativas y compilación TS/build. No crear columnas, tablas, API o decisiones comerciales nuevas.

## Verificación CI y aceptación

- El mensaje `i=002` informa `ci=SUCCESS`, pero no incluye URLs/IDs de las dos ejecuciones GitHub Actions asociadas al SHA. El conector de esta sesión no entrega las ejecuciones por `push`, y la consulta de statuses respondió 403. **CI exacta todavía NO verificada independientemente por ChatGPT.**
- Tras corrección: repetir `sf_verify.py CATPROD-001`, suite completa, QA independiente y publicación `AUTO_GIT` a `main`; proporcionar SHA exacto y **URLs de ambos workflows/resultado SUCCESS** en comentario SFH1. Si falla CI, arreglar en el mismo alcance.
- No marcar `VERIFIED` ni cerrar issue antes de revisión externa. `GUIA_PENDIENTE_CHATGPT` se redactará desde el código final corregido; el roadmap de autoría GPT se actualizará cuando corresponda.
- **No bloquear:** concurrencia real MariaDB, FULLTEXT, navegador físico y despliegue permanecen `NOT_RUN` / diferidos según el packet.

## Referencias
- Contratos: `docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md` (RN-25, CAT-04), `docs/product/especificacion-v2.2-parte-2-CONSOLIDADO-EN-REVISION.md` (§5.4–5.5).
- Packet: `docs/work/features/CATPROD-001.md`.
- Evidencia OC: `docs/work/CATPROD-001-REVIEW-20261117.md`, `docs/work/CATPROD-001-VERIFY-20261117-135642.md`.
