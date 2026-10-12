# ADR-DOC-001 — Reconciliación de requisitos y ejecución de agentes
**Estado:** APPROVED (autoridad del propietario, 2026-10-12).
**Alcance:** correcciones técnicas de DOC-001, no cierra ADR-01..06 ni cambia condiciones comerciales acordadas.

## Decisiones
1. El registro de venta, pagos, stock y caja en modo `tax_documentation_mode=external` no genera `fiscal_documents`, no invoca FiscalEngine y no certifica emisión DTE. El comprobante interno se construye desde `sales`, `sale_items`, `sale_payments` y usa `sales.sale_number` como número visible por sucursal. No se crea una tabla ni secuencia fiscal para un recibo interno no tributario.
2. `fiscal_documents` corresponde al subsistema Fiscal integrado. La modalidad Vitrina puede usar una simulación de prueba; `internal_receipt` deja de ser adaptador del Fiscal productivo. Declaración externa voluntaria y regularización tributaria no se confunden con emisión fiscal verificada.
3. `sale_external_tax_records` pertenece al lote de migración 09, después de `sales`, con FK a `sales` y `users` (lote 01). Sin ejecución de migraciones en DOC-001.
4. Una Feature Packet aprobada autoriza sus subtareas ordinarias y pruebas sin repetir permiso de alcance para cada una; los controles `ask` por herramienta, las colisiones y las decisiones fuera del contrato siguen vigentes. BOOT-001 comienza SIN framework y debe crearlo sin sobreescribir el harness.
5. Vue 3 + Inertia + TypeScript aprobado. Compilar assets con Node 24 LTS en CI cuando se cree el pipeline; Node 20 del hosting no es un requisito para compilar. Versión Laravel estable compatible con PHP 8.3 se fija durante BOOT-001 y queda registrada en lockfile.

## No decidido
ADR-01 cómputo de 500 ventas/mes, ADR-02 capacidades Free, ADR-03 emisión externa/pago mixto multireferencia, ADR-04 Fiscal autónomo, ADR-05 gracia/suspensión/offline y contrato central físico, ADR-06 alcance Compras/Analítica. Ninguno se completa por inferencia.

## Trazabilidad
Partes 1, 2 y Anexo v2.2 en `docs/product/`; auditoría DOC-001 en `docs/governance/`; ruta de salida en `docs/harness/ROADMAP-STOCKFLASH.md`. Las especificaciones v2.1 históricas no se modifican.
