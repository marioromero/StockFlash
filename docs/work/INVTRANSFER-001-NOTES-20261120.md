# INVTRANSFER-001 — Notas técnicas (2026-11-20)

packet: INVTRANSFER-001
feature: Interbranch stock transfer lifecycle (send, receive, partial differences)
contract: Parte 1 §7.4 RN-26 / §8.3 INV-06; Parte 2 §7.5
base_sha: `03c9bb8fb94f9c3f2e6c8114aede428c8ee03def`
correction_base_sha: `d2b051307a471bfd9bce4ce90d79970fd0325a18`
status: IMPLEMENTED_LOCAL_SQLITE_ONLY — NOT VERIFIED
maria=NOT_RUN
remote_db=KNOWN_HISTORICAL_EXCEPTION_NOT_UNTOUCHED
GUIA_PENDIENTE_CHATGPT
HQAEFF-001: applied for token-efficient implementation; final gates/QA/CI remain orchestrator-owned.

## Alcance implementado

- Migración única `2026_11_20_000024_create_stock_transfer_tables.php`.
- `stock_transfers`: `id`, `number VARCHAR(30)` NOT NULL, `from_branch_id`/`to_branch_id` FK `branches`, `status` ENUM aprobado con default `draft` e índice, usuarios nullable FK `users` SET NULL, tiempos nullable, `notes VARCHAR(500)` nullable, UNIQUE `(from_branch_id, number)`. Sin `created_at`/`updated_at`.
- `stock_transfer_items`: `id`, `transfer_id` FK CASCADE, `variant_id` FK `product_variants`, `requested_qty`/`shipped_qty`/`received_qty` DECIMAL(12,3) default 0, `unit_cost_snapshot` DECIMAL(14,4) nullable (permanece NULL en esta ola). Sin columnas ni índices extra.
- Servicio `StockTransferService` con transacciones de 3 intentos, `StockLedger`, `InventoryWriteGuard`, `DocumentSequenceAllocator` y `AuditLogger`. El envío agrega movimientos `transfer_out` negativos solo en origen y la recepción `transfer_in` positivos solo en destino, con `source_type=stock_transfer_item` y `source_id` del ítem.
- Consulta `StockTransferQueryService`, controlador `StockTransferController`, FormRequests de creación/recepción, rutas y pantallas Inertia `Inventory/Transfers` y `Inventory/TransferShow`.

## Decisiones documentadas

- **Número canónico vs borrador.** `number` es NOT NULL + UNIQUE por origen, pero el número contractual se asigna al pasar a `requested`. Se usa un marcador interno único `DRAFT-` + 24 hexadecimales mayúsculas (30 caracteres) al crear el borrador. Al solicitar, bajo lock de la fila y en la misma transacción, se reemplaza por `DocumentSequenceAllocator::allocate('transfer', from_branch_id)`. La API y la UI devuelven `number = null` solo para `status = draft` y para el marcador interno exacto `/^DRAFT-[0-9A-F]{24}$/` que puede permanecer en un borrador cancelado. Un número canónico real que empieza con `DRAFT-` (código de sucursal legal) se muestra normalmente desde `requested`; la longitud máxima canónica (prefijo <= 10 + padding 6..18) nunca alcanza los 30 caracteres del marcador. El número nunca se acepta del cliente.
- **Prefijo para códigos de origen largos.** `DocumentSequenceAllocator` admite `prefixOverride` documentado y rechaza códigos de sucursal > 10 caracteres, mientras `BranchRequest` permite códigos inmutables de hasta 30. Dentro de la transacción de solicitud se lee el código del origen ya validado/bloqueado: si mide <= 10 se mantiene `allocate('transfer', origin)` sin override (filas y prefijos existentes intactos); si supera 10 se usa un prefijo técnico determinista de exactamente 10 caracteres, `'T'.substr(str_pad((string) $originBranchId, 9, '0', STR_PAD_LEFT), -9)`. El ámbito y la unicidad siguen siendo `(transfer, branch, scope_id)` y `(from_branch_id, number)`; no se modifica la validación de códigos de sucursal ni el esquema.
- **Envío completo.** Esta ola implementa el envío de la totalidad de lo solicitado; no existe edición ni envío parcial. `shipped_qty = requested_qty` por línea y se exige que todas las líneas tengan cantidad positiva.
- **Comprobación de existencia acotada a traspasos.** Antes de escribir, se bloquean las filas `current_stock` del origen en orden ascendente de variante y se exige `available >= requested_qty`. Si falta la fila, disponible = 0. No se aplica el interruptor POS RN-22 de stock negativo; la decisión queda limitada a este flujo y no crea una regla global.
- **Recepción y faltantes.** Se declara exactamente una cantidad no negativa por ítem. Solo cantidades positivas generan un `transfer_in` en destino. `received` si todas las cantidades coinciden con lo enviado; en caso contrario `partially_received`. `shipped_qty - received_qty` queda como diferencia pendiente visible; no se generan ajustes, reversas, reclamos ni transiciones adicionales.
- **Idempotencia.** El lock de la fila del traspaso y la validación de estado impiden doble envío/recepción. Además, el ledger usa `source_type = stock_transfer_item` y `source_id = stock_transfer_items.id`, con el tipo de movimiento como parte de la clave de origen, por lo que cada ítem no puede publicar dos veces la misma salida/entrada en la misma sucursal.
- **Variantes históricas.** El detalle conserva variantes/productos inactivos o eliminados lógicamente. Un envío nuevo revalida que las variantes sigan activas, controlen stock y sean simple/variable; una recepción de un envío ya realizado no elimina el histórico.

## Pruebas y límites

- Pruebas nuevas en SQLite `:memory:` exclusivamente: esquema, ciclo borrador/solicitud/envío/recepción/cancelación, numeración por origen, rollback de correlativo, autorización por sucursal, módulo/licencia, lectura 403/200, campos forjados, decimales inválidos, stock insuficiente, doble envío/recepción, recepción parcial/cero, rollback por fallo de auditoría, mapeo Kardex, props sin costos/PII y no escritura directa de `current_stock`/`stock_movements`.
- Corrección externa i=003: se agregaron regresiones para códigos de origen de 11 y 30 caracteres (borrador → solicitud → envío con prefijo técnico, correlativos únicos y sin movimiento hasta el envío), fallo de auditoría en la solicitud (estado `draft`, marcador oculto, sin fila/consumo de `document_sequences`, sin stock ni auditoría canónica, reintento exitoso `000001`), y código de origen `DRAFT-` (borrador oculto; número canónico `DRAFT-000001` visible en detalle y listado desde `requested` y en `in_transit`, `received` y `partially_received`; borrador cancelado conserva oculto su marcador interno).
- Suite objetivo ejecutada: `php artisan test tests/Feature/Inventory` con `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=`.
- `npm run typecheck` ejecutado.
- Deferred: MariaDB real (bloqueos, concurrencia, índices, deadlocks), navegador/UX real, build Vite, deploy, instalación, migraciones/seeders reales y base remota.
- No se ejecutó `scripts/sf_verify.py`, no hay QA independiente ni CI exact-SHA en estas notas. El estado técnico NO es `VERIFIED`; la certificación corresponde al orquestador/ChatGPT.

## Disposición revisión externa i=003

- **A) AUTHORIZED_VALID_BRANCH_BLOCKED: corregido.** La solicitud usa `DocumentSequenceAllocator` con `prefixOverride` técnico solo cuando el código de origen supera 10 caracteres; los códigos <= 10 conservan el comportamiento y las filas existentes. Regresiones de 11/30 caracteres y rollback por fallo de auditoría de solicitud agregadas.
- **B) LEGITIMATE_NUMBER_HIDDEN: corregido.** Ambos servicios ocultan el número solo en `draft` o cuando coincide exactamente el marcador interno `DRAFT-` + 24 hexadecimales; los números canónicos con prefijo `DRAFT-` se conservan desde `requested` y en todos los estados posteriores, incluido un borrador cancelado que conserva oculto su placeholder. Regresiones de detalle/listado agregadas.
- No se reabrieron otras observaciones de QA. Sin cambios de esquema, contratos, harness ni `sf_verify`; sin base remota, deploy ni commit/push desde este rol.
