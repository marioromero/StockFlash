# CATMAST-001 — Maestros de Catálogo P0 (categorías, marcas, etiquetas, atributos e impuestos)

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Objetivo y contrato

Primera característica **visible** de Catálogo tras CATDB-001 VERIFIED. Entrega una única sección administrativa coherente para crear y mantener los maestros necesarios antes de los productos/variantes; no abrir cinco microfeatures artificiales.

Fuentes aprobadas: Parte 1 §8.2 CAT-02 (categorías jerárquicas, marcas, etiquetas) y CAT-05 (tasas/impuestos), §6.2–6.3 `catalog.view` y `catalog.manage`, §4.2 `catalog → core`; Parte 2 §5.1–5.3 (tablas `tax_rates`, `categories`, `brands`, `tags`, `attributes`, `attribute_values`). No ampliar esquema. `CATDB-001` migró estas tablas en código; suite actual las prueba solo en SQLite :memory:.

## Entregable de interfaz y lógica

1. Pantalla Inertia/Vue es-CL única, p. ej. `/catalog/masters`, con navegación por cinco agrupaciones («Impuestos», «Categorías», «Marcas», «Etiquetas», «Atributos y valores»). Diseño responsive, teclado y mensajes de validación claros. Enlazar desde Dashboard solo a usuarios que pueden ver Catálogo. Lectura solo para `catalog.view`, creación/edición para `catalog.manage`, **verificación siempre en servidor**; el input `branch_id` no puede alterar el ámbito de autorización del usuario. El Catálogo es común a la instalación, pero permiso del usuario por sucursal sigue dependiendo de su rol real. Reutilizar CheckPermission/EnsureBranchAccess cuando corresponda sin crear bypass.
2. CRUD acotado de `categories`: padre opcional, nombre, slug único, tope de descuento opcional 0–100, impuesto predeterminado opcional, orden, activo; impedir padre propio, ciclos o referencias inválidas. `brands`: nombre, slug único, descripción/logo URL opcionales, activa. `tags`: nombre, slug único. `attributes`: nombre único y `attribute_values` únicos por atributo. Asegurar que cada formulario solo permita columnas aprobadas, sin mass-assignment de atributos imprevistos.
3. `tax_rates`: código único, nombre, porcentaje 0–100 (decimal 5,2), exento, predeterminado, código fiscal opcional y activo. El contrato identifica `IVA19` y `EXENTO`, pero **no sembrar ni inventar tasas por defecto en una base real**; permitir administrarlas mediante esta pantalla. Al marcar una tasa default, limpiar flags de default anteriores en misma transacción, sin exigir que exista siempre una tasa default si la instalación no tiene datos. Evitar eliminación/modificación destructiva de tasas ya referenciadas por categorías/productos/variantes.
4. Bajas seguras: categorías/marcas marcadas `SD` usan soft-delete conforme contrato, cuidando referencias y jerarquía; tags/atributos/valores e impuestos no tienen soft-delete. Evitar eliminación destructiva cuando existen vínculos/productos/variantes que se verían afectados, **sin cambiar FKs aprobadas**; preferir bloqueo controlado y explicación, no cascadas silenciosas. En este packet las tablas `products`/`product_variants` no reciben nuevas filas.
5. Al crear slug, usar entrada explícita o generación estable por nombre como convención técnica; ante colisión reportar validación, **no reusar** un slug de registro soft-deleted automáticamente. No renombrar slugs existentes silenciosamente al editar un nombre. Validar FK a maestros, estados, límites y formatos. Evitar N+1 excesivos en árbol de categorías; no crear estructuras genéricas de CRUD por sobreingeniería.
6. Toda mutación en transacción con `AuditLogger`: actor, fecha, recurso, id, campos cambiados y códigos/IDs según política, sin copiar secretos ni PII; rollback si auditoría falla. Reutilizar `LicenseReadOnlyRule` de CORELIC-001: suspensión fuera de gracia bloquea escrituras. **Disponibilidad comercial:** no inferir entitlements desde `installation_modules.is_enabled`; no abrir a usuarios sin derecho vigente. Para pruebas usar estados de licencia/módulo explícitos en SQLite aislado, sin licencia/credenciales reales ni bypass de producción.
7. Pruebas: permisos VIEW/MANAGE (global y por sucursal), 403 direct URL POST/PUT/DELETE, ámbito de sucursal no manipulable, campos allowlist, constraints uniques/FK, jerarquía y ciclos, relaciones protegidas al borrar, default de tasa atómico, tasas exentas, auditoría/rollback, RN-70 solo lectura, payload extra, UI props sin secretos ni costos, regresión AUTH/USERS/COREMOD/CORELIC/CATDB y suite PHP completa, typecheck + Vite + contratos.
8. Incluir gates declarativos `CATMAST-001` en `GATES_BY_PACKET` existente, QA independiente, `AUTO_GIT` tras PASS y GitHub Actions exact SHA. Informar `GUIA_PENDIENTE_CHATGPT` con rutas/campos reales, SIN editar `public/docs/index.html`, `resources/roadmap/index.html` ni `CONTEXTO-CHATGPT.md`; ChatGPT actualizará la guía al cierre.

## EARS

- CUANDO usuario `catalog.view` abre Maestros, EL SISTEMA DEBE mostrar solo los maestros existentes y no otorgar botones/escrituras sin `catalog.manage`.
- CUANDO operador con `catalog.manage` guarda un maestro válido y autorizado, EL SISTEMA DEBE persistir exactamente campos permitidos y auditar atómicamente.
- CUANDO se define un padre de categoría, EL SISTEMA DEBE impedir ciclos y no eliminar jerarquías o variantes inadvertidamente.
- CUANDO un maestro está referenciado, EL SISTEMA NO DEBE destruir referencias/datos de productos o atributos de variantes.
- CUANDO falla validación, permiso, licencia o auditoría, EL SISTEMA NO DEBE guardar cambios parciales.
- CUANDO se edita `is_default` de impuesto, EL SISTEMA NO DEBE dejar varias tasas predeterminadas en una misma instalación.

## Límites y seguridad

Prohibido: migraciones nuevas, cambios a CORE o Parte 2, edición/ejecución de BD remota, lectura de `.env`/credenciales, DTE/SII, venta/impuestos efectivos sobre transacciones, productos/variantes, stock, importaciones/exports, etiquetas impresas, reglas comerciales nuevas, deploy, cambios a contratos, agentes/índices/scripts/plugins nuevos, sistema CRUD genérico o endpoints públicos de integración. Toda prueba solo en SQLite :memory:/CI ya aislados; MariaDB real se validará en entorno autorizado antes de deploy. Si surge una **decisión comercial genuinamente nueva**, documentar pregunta precisa y opciones recomendadas; NO pedir aprobación genérica ni el orden.
