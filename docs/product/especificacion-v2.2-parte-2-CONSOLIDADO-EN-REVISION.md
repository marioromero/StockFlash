# Especificación General v2.2 — POS + Inventario Modular Omnicanal
## Parte 2 de 2 · Modelo de datos completo

**Estado:** v2.2 **CONSOLIDADO EN REVISIÓN · no PUNTO 0 definitivo** · Base histórica: 04-10-2026 · **Motor:** MariaDB 10.11 LTS+ (mínimo técnico 10.6) · InnoDB · `utf8mb4_unicode_ci`
**Documento hermano:** `especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md`. **Anexo:** `especificacion-v2.2-anexo-CONSOLIDADO-EN-REVISION.md`. Los IDs `XXX-NN` y `RN-NN` de este documento son los de la Parte 1.
Reemplaza la v2 y su documento de delta.

---

**Estado de trabajo:** v2.2 con decisiones del owner integradas; v2.1 original disponible en `historico-v2.1`. La v2.2 no se considera PUNTO 0 final hasta resolver el uso mensual. Ver requisitos COM-01..07 de Parte 1.

## 0. Convenciones y equivalencias

### 0.1 Convenciones

| Elemento | Regla |
|---|---|
| Nombres | Tablas y columnas en **inglés snake_case**; tablas en plural |
| Columnas implícitas | Toda tabla tiene `id BIGINT UNSIGNED AUTO_INCREMENT PK`, salvo las de **PK compuesta**. Toda tabla tiene `created_at` y `updated_at` (`DATETIME NULL`, Laravel), salvo las **AO/AO-P** (solo `created_at`) y las de PK compuesta. **No se repiten abajo** |
| `SD` | Incluye `deleted_at DATETIME NULL` (borrado lógico) |
| `AO` | *Append-only permanente*: sin `UPDATE` ni `DELETE`; se corrige con un registro nuevo; **no se purga** |
| `AO-P` | *Append-only podable*: sin `UPDATE` ni `DELETE` para la aplicación y los usuarios; **solo el job `retention:prune`** puede eliminar registros vencidos |
| `PII` | La tabla o columna contiene datos personales |
| Dinero | `DECIMAL(14,2)`; costos unitarios `DECIMAL(14,4)`; cantidades `DECIMAL(12,3)`; porcentajes `DECIMAL(5,2)` |
| Booleanos | `TINYINT(1) UNSIGNED` |
| JSON | `JSON` (alias de `LONGTEXT` con `JSON_VALID` en MariaDB) |
| Cifrados | Salida de Laravel Crypt: `VARCHAR(255)` para valores cortos, `TEXT` para secretos |
| FK | `RESTRICT` por defecto; `CASCADE` en detalle hijo; `SET NULL` en referencias opcionales |
| Fechas | `DATETIME` en UTC. Las columnas `DATE` de resúmenes contienen la **fecha local** `America/Santiago` |
| Portabilidad | Sin SQL específico de MariaDB en el dominio (ARQ-07). Las columnas `VIRTUAL` son optimizaciones reemplazables; si el motor cambia, se sustituyen por validación en la aplicación |
| Estructura de tablas | Toda tabla se define con **Campo · Tipo · Restricciones · Descripción**. Las columnas de auditoría implícitas y las FK a `users` de "creado por" se omiten solo cuando la descripción lo indica |

### 0.2 Equivalencias respecto de documentos anteriores

| Anterior | Ahora |
|---|---|
| `tiendas`, `canal_woos`, `stores` | `sales_channels` |
| `store_branches`, `order_routing_mode`, `dispatch_rules` | Eliminados de P1 (cumplimiento desde una sola sucursal por canal; P2) |
| Mapeos por entidad (`product_store_mappings`, etc.) | `channel_mappings` (una tabla con `entity_type`) |
| `sync_events` | `channel_sync_events` (bandeja de salida exclusiva de canales) |
| `stock_snapshots`, `daily_stock_summaries` | `daily_stock_valuation_summaries` |
| `web_price`, `publish_to_web` | Eliminados: precio base + `price_lists`; publicación en `channel_publications` |
| `promotions.channel`, `price_lists.channel` | `price_lists.applies_to` + `sales_channel_id`; promociones solo POS |
| `sales.customer_snapshot` | `sales.customer_name_snapshot` |
| `users.role_id`, `users.is_global` | `users.global_role_id` |
| `mfa_recovery_codes_encrypted` | `user_mfa_recovery_codes` |
| `privacy_requests`, `security_incidents` | Eliminadas |
| `fiscal_documents.status='voided'` | Eliminado (el original permanece `issued`) |
| `spatie/laravel-permission` | RBAC propio (`roles`, `permissions`, `role_permissions`, `branch_user`) |

**Estados de venta (v1):** `borrador`→`status=draft`; `pendiente_despacho` y `en_transito`→`fulfillment_status`; `completada`→`status=completed`; `cancelada`→`status=cancelled`; `devuelta`→`return_status=full`.

---

**Delta estructural v2.2:** se mantienen las 80 tablas iniciales de v2.1 y se añade `sale_external_tax_records` (81ª tabla propia, P0). Se agregan campos a `sales`, `license_states`, `plan_definitions` y un ajuste de tokenización de ventas pausadas. La política final del cupo mensual puede exigir una tabla o índice adicional y está ABIERTA hasta ADR. Estas propuestas son de modelo para nuevas migraciones; no ejecutar cambios en bases productivas existentes sin un plan de migración probado.

## 1. Índice de tablas y requerimientos que las justifican

### 1.1 Índice

**Lote** = lote de migración (sección 1.2). Total: **80 tablas propias**, más las estándar de Laravel (`migrations`, `sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs`, `notifications`).

| Tabla | Módulo | Lote | Fase | Requerimientos |
|---|---|:-:|:-:|---|
| `system_configs` | core | 01 | P0 | CORE-09 |
| `installation_info` | core | 01 | P0 | CORE-17, LIC-01, BKP-08 |
| `installation_modules` | core | 01 | P0 | CORE-06 |
| `license_states` | core | 01 | P0 | CORE-08, LIC-03 |
| `plan_definitions` | core | 01 | P0 | CORE-07 |
| `branches` | core | 01 | P0 | CORE-05 |
| `roles` | core | 01 | P0 | CORE-04 |
| `permissions` | core | 01 | P0 | CORE-04 |
| `role_permissions` | core | 01 | P0 | CORE-04 |
| `users` | core | 01 | P0 | CORE-01, CORE-03, SEC-03 |
| `user_mfa_recovery_codes` | core | 01 | P1 | SEC-03 |
| `branch_user` | core | 01 | P0 | CORE-04 |
| `reason_codes` | core | 01 | P1 | CORE-12 |
| `pos_stations` | pos | 01 | P0 | CSH-02, POS-02 |
| `document_sequences` | core | 01 | P0 | CORE-13 |
| `integration_health` | core | 01 | P1 | INT-01, INT-02 |
| `backup_runs` | core | 01 | P1 | BKP-01, BKP-04 |
| `system_updates` | core | 01 | P1 | UPD-08 |
| `authorization_overrides` | core | 01 | P0 | CORE-11, SEC-12 |
| `audit_logs` | core | 01 | P0 | CORE-10, PRV-06, SEC-09 |
| `sales_channels` | channels | 02 | P0 | CHN-02 |
| `customer_groups` | customers | 03 | P0 | CUS-02 |
| `customers` | customers | 03 | P0 | CUS-01, CUS-03, PRV-02…05 |
| `customer_addresses` | customers | 03 | P1 | CUS-01, FIS-08 |
| `customer_consents` | customers | 03 | P1 | CUS-04 |
| `sellers` | sellers | 03 | P0 | SEL-01 |
| `branch_seller` | sellers | 03 | P0 | SEL-01 |
| `tax_rates` | catalog | 04 | P0 | CAT-05 |
| `categories` | catalog | 04 | P0 | CAT-02, CAT-06 |
| `brands` | catalog | 04 | P0 | CAT-02 |
| `tags` | catalog | 04 | P0 | CAT-02 |
| `attributes` | catalog | 04 | P0 | CAT-01 |
| `attribute_values` | catalog | 04 | P0 | CAT-01 |
| `products` | catalog | 04 | P0 | CAT-01, CAT-08 |
| `product_variants` | catalog | 04 | P0 | CAT-01, CAT-04, CAT-06, CAT-07, CAT-11 |
| `product_barcodes` | catalog | 04 | P0 | CAT-03 |
| `product_tag` | catalog | 04 | P0 | CAT-02 |
| `product_variant_attribute_values` | catalog | 04 | P0 | CAT-01 |
| `combo_components` | combos | 04 | P1 | PRC-03, INV-13 |
| `promotions` | promotions | 04 | P1 | PRC-01, PRC-02 |
| `promotion_conditions` | promotions | 04 | P1 | PRC-01 |
| `price_lists` | price_lists | 04 | P1 | PRC-05, PRC-06 |
| `price_list_items` | price_lists | 04 | P1 | PRC-05 |
| `payment_methods` | sales | 05 | P0 | SAL-05, CHN-04 |
| `branch_variant_settings` | inventory | 06 | P0 | INV-07, INV-19 |
| `current_stock` | inventory | 06 | P0 | INV-02, INV-03 |
| `stock_reservations` | inventory | 06 | P0 | INV-17, INV-18 |
| `stock_movements` | inventory | 06 | P0 | INV-01, INV-08 |
| `stock_transfers` | inventory | 06 | P0 | INV-06 |
| `stock_transfer_items` | inventory | 06 | P0 | INV-06 |
| `stock_counts` | inventory | 06 | P1 | INV-10 |
| `stock_count_items` | inventory | 06 | P1 | INV-10 |
| `suppliers` | purchasing | 07 | P1 | PUR-01 |
| `purchase_orders` | purchasing | 07 | P2 | PUR-03 |
| `purchase_order_items` | purchasing | 07 | P2 | PUR-03 |
| `goods_receipts` | purchasing | 07 | P1 | PUR-02 |
| `goods_receipt_items` | purchasing | 07 | P1 | PUR-02 |
| `cash_sessions` | cash_control | 08 | P0 | CSH-03, CSH-05 |
| `cash_session_totals` | cash_control | 08 | P0 | CSH-05, CSH-06 |
| `cash_movements` | cash_control | 08 | P0 | CSH-04 |
| `sale_external_tax_records` | sales | 05 | P0 v2.2 | COM-05, COM-06 (referencia externa opcional, declarativa) |
| `sales` | sales | 09 | P0 | SAL-01…04, SAL-16, SAL-21 |
| `sale_items` | sales | 09 | P0 | SAL-01, SAL-06 |
| `sale_payments` | sales | 09 | P0 | SAL-05, SAL-20 |
| `sale_returns` | sales | 09 | P0 | SAL-10, SAL-22, CHN-15 |
| `sale_return_items` | sales | 09 | P0 | SAL-10, SAL-22 |
| `sale_review_issues` | sales | 09 | P1 | SAL-21 (RN-76) |
| `fiscal_documents` | fiscal | 10 | P0 (modelo) | FIS-02…09, BKP-09…11 |
| `fiscal_document_attempts` | fiscal | 10 | P0 | FIS-02, FIS-06 |
| `channel_publications` | channels | 11 | P1 | CHN-08 |
| `channel_mappings` | channels | 11 | P0 | WOO-04, WOO-05 |
| `channel_remote_items` | channels | 11 | P1 | WOO-05 |
| `channel_payment_maps` | channels | 11 | P1 | CHN-04, CHN-13 |
| `channel_sync_events` | channels | 11 | P0 | ARQ-02, WOO-03 |
| `webhook_receipts` | channels | 11 | P1 | WOO-02, CHN-09 |
| `daily_sales_summaries` | reports | 12 | P0 | REP-01, REP-02 |
| `daily_payment_summaries` | reports | 12 | P0 | REP-02, CSH-06 |
| `daily_seller_sales_summaries` | reports | 12 | P0 | SEL-03 |
| `daily_product_sales_summaries` | reports | 12 | P1 | REP-03 |
| `daily_stock_valuation_summaries` | reports | 12 | P1 | REP-02 |
| `report_runs` | reports | 12 | P0 | REP-01 |

### 1.2 Orden de migraciones (ejecutable)

Cada lote depende solo de lotes anteriores. Dentro de un lote, el orden de la lista es el de ejecución.

| Lote | Nombre | Tablas (en orden) | Dependencias que usa |
|:-:|---|---|---|
| 01 | `core_base` | `system_configs`, `installation_info`, `installation_modules`, `license_states`, `plan_definitions`, `branches`, `roles`, `permissions`, `role_permissions`, `users`, `user_mfa_recovery_codes`, `branch_user`, `reason_codes`, `pos_stations`, `document_sequences`, `integration_health`, `backup_runs`, `system_updates`, `authorization_overrides`, `audit_logs` | `users`→`roles`,`branches`; `pos_stations`→`branches`; `authorization_overrides`→`users`,`branches`,`pos_stations`,`reason_codes`; `system_updates`→`backup_runs` |
| 02 | `channels_base` | `sales_channels` | →`branches` |
| 03 | `customers_sellers` | `customer_groups`, `customers`, `customer_addresses`, `customer_consents`, `sellers`, `branch_seller` | →`users`, `branches` |
| 04 | `catalog` | `tax_rates`, `categories`, `brands`, `tags`, `attributes`, `attribute_values`, `products`, `product_variants`, `product_barcodes`, `product_tag`, `product_variant_attribute_values`, `combo_components`, `promotions`, `promotion_conditions`, `price_lists`, `price_list_items` | `promotion_conditions`→`customer_groups`(03); `price_lists`→`sales_channels`(02), `customer_groups`(03), `branches`(01) |
| 05 | `payment_methods` | `payment_methods` | — |
| 06 | `inventory` | `branch_variant_settings`, `current_stock`, `stock_reservations`, `stock_movements`, `stock_transfers`, `stock_transfer_items`, `stock_counts`, `stock_count_items` | `stock_reservations`→`sales_channels`(02); `stock_movements`→`stock_reservations`, `reason_codes` |
| 07 | `purchasing` | `suppliers`, `purchase_orders`, `purchase_order_items`, `goods_receipts`, `goods_receipt_items` | →`branches`, `users`, `product_variants` |
| 08 | `cash` | `cash_sessions`, `cash_session_totals`, `cash_movements` | →`pos_stations`, `authorization_overrides`, `payment_methods`(05) |
| 09 | `sales` | `sales`, `sale_items`, `sale_payments`, `sale_returns`, `sale_return_items`, `sale_review_issues` | →`sales_channels`(02), `customers`/`sellers`(03), `promotions`(04), `payment_methods`(05), `cash_sessions`(08); `sale_review_issues`→`sales`, `sale_items`, `sale_returns` |
| 10 | `fiscal` | `fiscal_documents`, `fiscal_document_attempts` | →`sales`, `sale_returns`(09) |
| 11 | `channel_ops` | `channel_publications`, `channel_mappings`, `channel_remote_items`, `channel_payment_maps`, `channel_sync_events`, `webhook_receipts` | →`sales_channels`(02), `product_variants`(04), `payment_methods`(05) |
| 12 | `reports` | las 6 tablas de resumen | →`branches`, `product_variants`, `payment_methods`, `sellers` |

**Verificación:** no existe ninguna FK hacia una tabla de un lote posterior. Las referencias polimórficas (`stock_movements.source_*`, `stock_reservations.source_*`, `channel_mappings.internal_id`, `authorization_overrides.reference_*`, `audit_logs.auditable_*`) no tienen FK por diseño.

---

## 2. Reglas de integridad transversales

1. **AO (permanente):** `stock_movements`, `authorization_overrides`, `customer_consents`, `cash_movements`, `fiscal_document_attempts`. Sin `UPDATE`, `DELETE` ni purga.
2. **AO-P (podable):** `audit_logs`. Inmutable durante su período de conservación; **solo `retention:prune`** elimina registros vencidos (24 meses por defecto). Ningún usuario ni proceso de negocio los modifica o borra.
3. **Sin borrado físico** de datos transaccionales o fiscales (PRV-11). Maestros con `SD`; `sales`, `sale_items`, `sale_payments`, `sale_returns` y `fiscal_documents` **no llevan `deleted_at`**: se anulan por estado.
4. **Numeración:** `document_sequences` se consume dentro de la transacción con `SELECT … FOR UPDATE`. Los números son únicos **por sucursal** (RN-74).
5. **Orden de bloqueo:** `current_stock` siempre por `(branch_id, variant_id)` ascendente.
6. **Idempotencia:** `sales.client_uuid`; `sales (channel_id, external_order_id)`; `sale_items (sale_id, external_line_id)`; `sale_payments.channel_payment_key` (un pago principal de canal por venta); `sale_returns (channel_id, external_refund_id)`; `webhook_receipts (channel_id, delivery_id)`; `channel_remote_items (channel_id, external_key)`; `channel_mappings` (dos únicos); `channel_sync_events (channel_id, pending_dedupe_key)`; `stock_reservations (source_type, source_id, source_line_key)`; `sale_review_issues.active_key`; `fiscal_documents.idempotency_key` y `active_key`.
7. **Bandejas de salida transaccionales (ARQ-02):** canales → `channel_sync_events`; fiscal → `fiscal_documents` en `pending`. Se escriben en la misma transacción que el cambio de negocio.
8. **Reservas y kardex:** el kardex registra solo movimientos **físicos**. `current_stock.on_hand` = último `balance_after`; `current_stock.reserved` = suma de `stock_reservations` activas. El job de reconciliación verifica ambas igualdades. Las reservas de **pedidos online** no se liberan por vencimiento local: se reconcilian con el canal (RN-30).
9. **Instantáneas mínimas:** `sale_items` guarda nombre, SKU, costo y tasa; `sales` guarda solo `customer_name_snapshot` y `shipping_snapshot`; los datos tributarios viven en `fiscal_documents.receiver_snapshot`.
10. **PII:** ver la sección 14. Las columnas `PII` se enmascaran por defecto y su lectura se audita.
11. **`CHECK` sugeridos** (MariaDB ≥ 10.2.1 los aplica): cantidades de línea > 0; porcentajes entre 0 y 100; `current_stock.reserved ≥ 0`; `sale_items.quantity ≥ returned_quantity`; `sale_items`: `variant_id` no nulo **o** `external_line_id` no nulo (solo las líneas externas pueden carecer de variante); `sale_payments`: `status='confirmed'` ⇒ `paid_at` no nulo; `price_lists`: `applies_to='channel'` ⇔ `sales_channel_id` no nulo.
12. **Una fuente de verdad:** roles → `roles`/`branch_user`/`users.global_role_id`; stock físico → `stock_movements`; reservado → `stock_reservations`; precio → `price_lists` + `price`; pago online → el canal (importado a `sale_payments`); documento fiscal → `fiscal_documents`; canal → `sales_channels`.
13. **Columnas generadas con restricción única** (se verifican con la prueba ARQ-13 en MariaDB 10.6 y en la versión LTS): `fiscal_documents.active_key`, `cash_sessions.open_station_key`, `cash_sessions.open_user_key`, `channel_sync_events.pending_dedupe_key`, `channel_mappings.external_key`, `channel_remote_items.external_key`, `sale_payments.channel_payment_key` y `sale_review_issues.active_key`. La prueba inserta filas que deben colisionar y filas que no. Si una expresión no pasa, esa columna se declara `PERSISTENT`; si aún falla, la unicidad se valida en la aplicación bajo bloqueo. Las columnas generadas **sin** restricción única (`current_stock.available`, `stock_count_items.difference`) son conveniencias.

---

## 3. Claves de configuración (`system_configs`)

`is_secret=1` cifra el valor al guardar y nunca se muestra completo. Estructura de la tabla: sección 4.1.

### 3.1 Instalación y negocio

| Clave | Tipo | Defecto | Descripción |
|---|---|---|---|
| `business_name` | string | — | Nombre legal del comercio |
| `country` / `currency_code` / `currency_decimals` | string / string / int | `CL` / `CLP` / `0` | País, moneda y decimales |
| `timezone` / `locale` | string | `America/Santiago` / `es-CL` | Zona de negocio e idioma |
| `responsible_name` / `responsible_email` / `responsible_phone` | string | — | Responsable de la instalación (**PII; nunca se envía al control central**) |
| `is_setup_completed` | bool | `false` | Onboarding terminado |
| `is_demo` | bool | `false` | Instalación demo |
| `fiscal_id` / `fiscal_name` / `commercial_activity` / `fiscal_address` / `fiscal_commune` | string | — | Datos tributarios del comercio |
| `prices_include_tax` | bool | `true` | El precio de catálogo incluye impuesto |
| `cash_rounding_step` | int | `1` | Redondeo del efectivo (10 es habitual en Chile) |
| `receipt_header` / `receipt_footer` / `receipt_paper` | string | — / — / `80mm` | Comprobante (`58mm`, `80mm`, `A4`) |
| `deployment_profile` | string | `shared_hosting` | Solo informativo para el panel de capacidad (ARQ-10); no altera el dominio |

### 3.2 Precios, descuentos y autorizaciones

| Clave | Tipo | Defecto | Descripción |
|---|---|---|---|
| `default_max_discount_percent` | decimal | `100` | Tope global cuando nada más aplica |
| `discount_caps_apply_to_promotions` | bool | `false` | ⚑ Aplicar topes a líneas solo con promoción |
| `discount_stacking_mode` | string | `best_of` | `best_of`; `stack` reservado |
| `block_sale_below_cost` | bool | `true` | Exigir autorización bajo el costo |
| `override_min_authority` | int | `50` | Autoridad mínima para autorizar por PIN |
| `pin_length` / `pin_max_attempts` | int | `4` / `5` | Largo e intentos del PIN |
| `cost_method` | string | `average` | `average` o `last` |
| `stock_adjust_pin_value_threshold` | decimal | `0` | Valor a costo que exige PIN en un ajuste (`0` = desactivado) |

### 3.3 Ventas, stock y caja

| Clave | Tipo | Defecto | Descripción |
|---|---|---|---|
| `sales_flow_mode` | string | `both` | `direct`, `two_step`, `both` (la sucursal puede sobrescribir) |
| `ticket_reserves_stock` / `ticket_expiry_minutes` | bool / int | `true` / `240` | Reserva y vencimiento del ticket |
| `held_sale_expiry_hours` | int | `24` | Vencimiento de ventas en espera |
| `seller_code_policy` | string | `optional` | `none`, `optional`, `required` |
| `edit_ticket_at_cash` | bool | `false` | Editar el ticket al cobrar |
| `allow_negative_stock` | bool | `true` | Permitir forzar ventas de mostrador sin stock |
| `return_window_days` | int | `30` | Plazo de devoluciones (`0` = sin límite) |
| `reservation_sweep_seconds` | int | `60` | Frecuencia del job: libera tickets vencidos y **reconcilia** reservas online vencidas; no libera estas últimas por reloj local |
| `cash_blind_close` | bool | `true` | Cierre ciego |
| `cash_difference_tolerance` | decimal | `0` | Diferencia tolerada sin aprobación |
| `cash_movement_pin_threshold` | decimal | `0` | Monto que exige PIN (`0` = desactivado) |
| `cash_session_max_hours` | int | `16` | Alerta de sesión larga |

### 3.4 Fiscal, canales y reportes

| Clave | Tipo | Defecto | Descripción |
|---|---|---|---|
| `fiscal_adapter` | string | `internal_receipt` | `internal_receipt` o el código del proveedor |
| `fiscal_provider_config` | json (secreto) | — | Credenciales, ambiente y **configuración por sucursal** del proveedor (RN-56) |
| `fiscal_authority_confirmed` | bool | `false` | El Admin confirmó que toda emisión fiscal paralela está desactivada (RN-49); se audita |
| `fiscal_failure_policy` | string | `allow_pending` | `allow_pending` o `block_sale` (RN-54) |
| `fiscal_default_document` | string | `boleta` | Tipo por defecto cuando no se pide factura |
| `fiscal_backup_destination` | string | `none` | Destino de la réplica incremental de XML fiscales: `none` o `local_path` (P1); `sftp` y `s3` llegan con BKP-03 |
| `fiscal_backup_path` | string | — | Ruta del destino cuando es `local_path` |
| `fiscal_replica_max_lag_hours` | int | `24` | Horas máximas con XML sin replicar antes de advertir |
| `content_source_channel_id` | int | vacío | Canal fuente de contenido entrante (descripción e imágenes); vacío = el POS es el maestro (RN-44) |
| `channel_dry_run` | bool | `false` | Registrar envíos al canal sin ejecutarlos (`true` en demo) |
| `channel_poll_minutes` | int | `15` | Frecuencia del sondeo de respaldo |
| `channel_poll_overlap_minutes` | int | `10` | Solape de lectura del sondeo (RN-41) |
| `channel_reconcile_time` | string | `03:00` | Reconciliación nocturna |
| `channel_stock_debounce_seconds` | int | `60` | Ventana de fusión de envíos de stock |
| `webhook_max_body_kb` | int | `1024` | Tamaño máximo de un webhook |
| `webhook_valid_warn_per_min` | int | `600` | Umbral operativo para **alertar/activar backpressure** por ráfaga de webhooks válidos; no responde 429 por sí solo |
| `webhook_invalid_sig_limit_per_min` | int | `30` | Firmas inválidas por IP; estas sí se limitan |
| `integration_red_connection_minutes` | int | `15` | Umbral rojo: conexión caída |
| `integration_red_pending_minutes` | int | `30` | Umbral rojo: pendiente más antiguo |
| `integration_red_reconcile_hours` | int | `36` | Umbral rojo: reconciliación sin ejecutar |
| `report_refresh_minutes` | int | `30` | Frecuencia de agregación de reportes |

### 3.5 Privacidad, seguridad, retención, respaldo y control central

| Clave | Tipo | Defecto | Descripción |
|---|---|---|---|
| `marketing_notice_text` | text | — | Texto del opt-in de comunicaciones comerciales mostrado en el POS |
| `marketing_notice_version` | string | `1` | Versión del texto; se incrementa al cambiarlo y se guarda en cada consentimiento |
| `password_min_length` | int | `15` | Largo mínimo (SEC-01); la interfaz y validación admiten al menos 64 caracteres |
| `session_idle_minutes_pos` / `session_idle_minutes_admin` | int | `30` / `60` | Inactividad (SEC-05) |
| `session_absolute_hours` | int | `12` | Duración absoluta de la sesión |
| `mfa_required_roles` | json | `["central_admin"]` | Roles con MFA obligatorio |
| `bulk_export_alert_threshold` | int | `500` | Registros exportados que disparan aviso |
| `retention_webhook_ok_hours` | int | `72` | Depuración de la carga de un webhook procesado |
| `retention_webhook_failed_days` | int | `14` | Conservación de la carga de un webhook fallido |
| `retention_drafts_days` | int | `30` | Borradores, ventas en espera y tickets vencidos |
| `retention_sync_events_days` | int | `30` | Eventos de canal cerrados (`done`) en `channel_sync_events` |
| `retention_shipping_days` | int | `180` | Limpieza de `shipping_snapshot` tras la entrega |
| `retention_audit_months` | int | `24` | Auditoría y eventos de seguridad |
| `retention_export_files_days` | int | `7` | Archivos de exportación con datos personales |
| `retention_fiscal_pdf_days` | int | `90` | Caché de PDF fiscales |
| `customer_inactive_months` | int | `0` | Inactividad para sugerir anonimización (`0` = desactivado; P2) |
| `backup_time` / `backup_keep_daily` / `backup_keep_weekly` | string / int / int | `02:00` / `7` / `4` | Respaldo |
| `backup_destination` | string | `local` | `local`, `sftp`, `s3` (P2) |
| `central_heartbeat_enabled` | bool | `true` | Latido diario al control central |
| `license_stale_warning_days` | int | `7` | Aviso por días sin verificar |

---

## 4. Núcleo (`core`)

### 4.1 `system_configs` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| config_key | VARCHAR(100) | NOT NULL, UNIQUE | Clave (`key` es palabra reservada) |
| config_value | TEXT | NULL | Valor (cifrado si `is_secret`) |
| value_type | ENUM('string','int','decimal','bool','json','text') | NOT NULL, DEFAULT 'string' | Cómo castear el valor |
| module_code | VARCHAR(30) | NULL, INDEX | Módulo al que pertenece |
| is_secret | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Valor cifrado y oculto |
| description | VARCHAR(255) | NULL | Texto para el panel |
| updated_by_user_id | BIGINT UNSIGNED | NULL | Último usuario que lo cambió (sin FK: la tabla es la primera del lote; el cambio queda además en `audit_logs`) |

### 4.2 `installation_info` · P0
Una sola fila.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| installation_id | CHAR(36) | NOT NULL, UNIQUE | UUID v4 generado al instalar; no deriva de datos personales |
| app_version | VARCHAR(20) | NOT NULL | Versión instalada |
| schema_version | VARCHAR(40) | NULL | Última migración aplicada |
| release_channel | ENUM('stable','beta') | NOT NULL, DEFAULT 'stable' | Canal de versiones |
| installed_at | DATETIME | NOT NULL | Fecha de instalación |
| last_update_at | DATETIME | NULL | Última actualización |
| last_update_status | ENUM('none','ok','failed','rolled_back') | NOT NULL, DEFAULT 'none' | Resultado de la última actualización |
| recovery_package_exported_at | DATETIME | NULL | Última descarga del paquete de recuperación de secretos (BKP-08) |

### 4.3 `installation_modules` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| module_code | VARCHAR(30) | NOT NULL, UNIQUE | Código del módulo |
| is_enabled | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Activo en esta instalación |
| enabled_at | DATETIME | NULL | Última activación |
| disabled_at | DATETIME | NULL | Última desactivación |
| settings | JSON | NULL | Ajustes del módulo |

### 4.4 `license_states` · P0 · ajustado v2.2
Caché local del estado de licencia (una fila vigente).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| installation_key | VARCHAR(100) | NOT NULL, UNIQUE | Llave de licencia (secreto; nunca en registros) |
| status | ENUM('active','expiring_soon','suspended') | NOT NULL, DEFAULT 'active', INDEX | Estado |
| plan_code | VARCHAR(30) | NOT NULL, DEFAULT 'demo', INDEX | `demo` (vitrina), `free` (persistente), `bodega`, `starter`, `pro`, `enterprise`, `unlimited` |
| max_branches | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Sucursales activas (`0` = ilimitado) |
| max_pos_stations | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Estaciones POS activas |
| max_channels | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Canales activos |
| max_users | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Usuarios activos |
| max_products | INT UNSIGNED | NOT NULL, DEFAULT 0 | **Variantes vendibles activas** (`0` = ilimitado); nunca productos maestros |
| max_monthly_sales | INT UNSIGNED | NOT NULL, DEFAULT 0 | Cupo de ventas mensuales (`0` = ilimitado); política de cómputo y bloqueo PENDIENTE ADR |
| products_enabled | JSON | NOT NULL, DEFAULT '["*"]' | Entitlements de productos comerciales; el plan `free` solo habilita capacidades básicas aprobadas |
| modules_enabled | JSON | NOT NULL, DEFAULT '["*"]' | Módulos incluidos (`["*"]` = todos) |
| latest_version | VARCHAR(20) | NULL | Versión más reciente informada |
| min_supported_version | VARCHAR(20) | NULL | Versión mínima compatible |
| expires_at | DATE | NULL | Vencimiento (`NULL` = sin vencimiento) |
| grace_until | DATE | NULL | Fin de la gracia; luego, solo lectura (RN-70) |
| message | VARCHAR(255) | NULL | Mensaje del servicio o del stub |
| signed_payload | TEXT | NULL | Respuesta firmada recibida (LIC-05, adelantado a primera comercialización) |
| signature | VARCHAR(255) | NULL | Firma Ed25519 (LIC-05, adelantado a primera comercialización) |
| is_simulated | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | `1` = stub local |
| notified_expiry_warning | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Aviso ya mostrado |
| last_verified_at | DATETIME | NULL | Última verificación correcta |

### 4.5 `plan_definitions` · P0 · ajustado v2.2
Catálogo local informativo; la fuente de verdad de una instalación es `license_states`.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NOT NULL, UNIQUE | `demo`, `free`, `bodega`, `starter`, `pro`, `enterprise` |
| name | VARCHAR(100) | NOT NULL | Nombre |
| description | TEXT | NULL | Descripción comercial |
| max_branches / max_pos_stations / max_channels / max_users | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Límites (`0` = ilimitado) |
| max_products | INT UNSIGNED | NOT NULL, DEFAULT 0 | Límite de **variantes vendibles activas** |
| max_monthly_sales | INT UNSIGNED | NOT NULL, DEFAULT 0 | Límite mensual de ventas; `free=500`, demo y sin límite = `0`, cómputo requiere ADR |
| products_enabled | JSON | NOT NULL | Productos comerciales contratados, separados de módulos técnicos |
| modules_enabled | JSON | NOT NULL | Módulos incluidos |
| sort_order | TINYINT UNSIGNED | NOT NULL, DEFAULT 0 | Orden de presentación |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Visible |

### 4.6 `branches` · P0 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NOT NULL, UNIQUE | Código corto; prefijo por defecto de la numeración |
| name | VARCHAR(150) | NOT NULL | Nombre |
| type | ENUM('store','warehouse','online') | NOT NULL, DEFAULT 'store', INDEX | Tienda, bodega u online |
| address | VARCHAR(255) | NULL | Dirección |
| commune | VARCHAR(100) | NULL, INDEX | Comuna |
| city | VARCHAR(100) | NULL | Ciudad |
| region | VARCHAR(100) | NULL | Región |
| phone | VARCHAR(30) | NULL | Teléfono |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Operativa |
| requires_cash_control | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Exige sesión de caja para vender |
| sales_flow_mode | ENUM('direct','two_step','both') | NULL | `NULL` hereda de `system_configs` |
| seller_code_policy | ENUM('none','optional','required') | NULL | `NULL` hereda |
| allow_negative_stock | TINYINT(1) UNSIGNED | NULL | `NULL` hereda |
| settings | JSON | NULL | Ajustes adicionales |

### 4.7 `roles` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NOT NULL, UNIQUE | `seller`, `cashier`, `warehouse_keeper`, `supervisor`, `central_admin` |
| name | VARCHAR(80) | NOT NULL | Nombre visible |
| authority_level | TINYINT UNSIGNED | NOT NULL | 10, 20, 20, 50, 100; decide quién autoriza por PIN |
| max_discount_percent | DECIMAL(5,2) | NULL | Tope de descuento manual del rol (`NULL` = sin tope propio) |
| max_return_amount | DECIMAL(14,2) | NULL | Tope de devolución por documento |
| description | VARCHAR(255) | NULL | Descripción |
| is_system | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Rol de sistema (no editable ni eliminable) |

### 4.8 `permissions` y `role_permissions` · P0
`permissions`: `code VARCHAR(100) NOT NULL UNIQUE` (ej.: `sales.void`) · `module_code VARCHAR(30) NOT NULL INDEX` · `description VARCHAR(255) NULL`. Sincronizada desde `config/modules.php`.
`role_permissions` (PK compuesta): `role_id BIGINT UNSIGNED FK roles CASCADE` · `permission_id BIGINT UNSIGNED FK permissions CASCADE`. La acumulación de permisos entre roles se aplica al sembrar.

### 4.9 `users` · P0 · SD · PII

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| name | VARCHAR(150) | NOT NULL | Nombre |
| email | VARCHAR(150) | NOT NULL, UNIQUE | Acceso |
| password | VARCHAR(255) | NOT NULL | Hash |
| remember_token | VARCHAR(100) | NULL | Laravel |
| password_changed_at | DATETIME | NULL | Último cambio |
| global_role_id | BIGINT UNSIGNED | NULL, FK `roles` | Rol global: **no nulo = usuario global** (ve todas las sucursales). Los demás usuarios tienen rol por sucursal en `branch_user` |
| default_branch_id | BIGINT UNSIGNED | NULL, FK `branches` SET NULL | Sucursal inicial |
| pin_hash | VARCHAR(255) | NULL | Hash del PIN de autorización |
| pin_failed_attempts | TINYINT UNSIGNED | NOT NULL, DEFAULT 0 | Intentos de PIN fallidos |
| pin_locked_until | DATETIME | NULL | Bloqueo del PIN |
| mfa_enabled | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | MFA activo |
| mfa_secret_encrypted | TEXT | NULL | Secreto TOTP, **cifrado** (el sistema debe poder leerlo) |
| mfa_confirmed_at | DATETIME | NULL | Confirmación del MFA |
| max_discount_percent | DECIMAL(5,2) | NULL | Sobrescribe el del rol |
| max_return_amount | DECIMAL(14,2) | NULL | Sobrescribe el del rol |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Puede iniciar sesión |
| failed_login_attempts | TINYINT UNSIGNED | NOT NULL, DEFAULT 0 | Intentos fallidos de acceso |
| locked_until | DATETIME | NULL | Bloqueo temporal |
| must_change_password | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Forzar cambio |
| last_login_at | DATETIME | NULL | Último acceso |
| last_login_ip | VARCHAR(45) | NULL | IP del último acceso |
| anonymized_at | DATETIME | NULL | Anonimizado (PRV-09); conserva el `id` |

### 4.10 `user_mfa_recovery_codes` · P1
Códigos de recuperación: se verifican sin recuperar su valor, por eso se guardan **como hash** y son de un solo uso.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| user_id | BIGINT UNSIGNED | NOT NULL, FK `users` CASCADE, INDEX | Usuario |
| code_hash | VARCHAR(255) | NOT NULL | Hash del código |
| used_at | DATETIME | NULL | Uso (`NULL` = disponible) |

### 4.11 `branch_user` · P0 (PK compuesta `(user_id, branch_id)`)

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| user_id | BIGINT UNSIGNED | PK, FK `users` CASCADE | Usuario |
| branch_id | BIGINT UNSIGNED | PK, FK `branches` CASCADE | Sucursal |
| role_id | BIGINT UNSIGNED | NOT NULL, FK `roles` | Rol del usuario en esa sucursal |

### 4.12 `reason_codes` · P1

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| scope | ENUM('override','stock_adjustment','waste','void','return','cash_movement') | NOT NULL | Dónde se usa |
| code | VARCHAR(30) | NOT NULL | Código corto |
| label | VARCHAR(120) | NOT NULL | Texto visible |
| requires_note | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Exige comentario |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |
| sort_order | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Orden |

Único `(scope, code)`.

### 4.13 `pos_stations` · P0 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` CASCADE | Sucursal |
| code | VARCHAR(30) | NOT NULL, UNIQUE | Código |
| name | VARCHAR(100) | NOT NULL | Nombre (ej. "Caja 1") |
| printer_profile | ENUM('thermal58','thermal80','a4') | NULL | Perfil de impresión |
| device_token_hash | VARCHAR(64) | NULL | Hash del token que identifica el dispositivo |
| last_seen_at | DATETIME | NULL | Última actividad |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Activa |

### 4.14 `document_sequences` · P0
Una fila por tipo de documento y ámbito (RN-74).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| doc_type | ENUM('ticket','sale','return','transfer','count','goods_receipt','cash_session','internal_receipt','purchase_order') | NOT NULL | Tipo (las cotizaciones usan la serie `ticket` con otro prefijo) |
| scope_type | ENUM('installation','branch') | NOT NULL | Ámbito |
| scope_id | BIGINT UNSIGNED | NOT NULL, DEFAULT 0 | `0` en `installation`; el `branches.id` en `branch` |
| prefix | VARCHAR(10) | NOT NULL, DEFAULT '' | Prefijo visible (por defecto, el código de la sucursal) |
| next_number | BIGINT UNSIGNED | NOT NULL, DEFAULT 1 | Próximo correlativo |
| padding | TINYINT UNSIGNED | NOT NULL, DEFAULT 6 | Largo con ceros |

Único `(doc_type, scope_type, scope_id)`: **ninguna columna es nula**, así que la restricción protege realmente la secuencia global. El servicio crea la fila del ámbito antes de usarla.

### 4.15 `integration_health` · P1
Una fila por integración; alimenta el panel de estado (INT-01).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| integration_key | VARCHAR(60) | NOT NULL, UNIQUE | `channel:3`, `fiscal`, `license`, `backup` |
| type | ENUM('channel','fiscal','license','backup') | NOT NULL | Tipo (no existe monitoreo de correo en P1) |
| status | ENUM('ok','warning','error','unknown') | NOT NULL, DEFAULT 'unknown', INDEX | Semáforo |
| last_checked_at | DATETIME | NULL | Última comprobación |
| last_success_at | DATETIME | NULL | Último éxito |
| last_error_at | DATETIME | NULL | Último error |
| last_error_message | VARCHAR(500) | NULL | Mensaje legible |
| pending_jobs | INT UNSIGNED | NOT NULL, DEFAULT 0 | Pendientes |
| oldest_pending_at | DATETIME | NULL | Pendiente más antiguo |
| details | JSON | NULL | Estado de webhooks, última reconciliación, etc. |

### 4.16 `backup_runs` · P1

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| type | ENUM('daily','weekly','pre_update','manual') | NOT NULL | Origen |
| status | ENUM('running','ok','failed') | NOT NULL, INDEX | Resultado |
| disk | VARCHAR(30) | NOT NULL | Disco de `Storage` |
| path | VARCHAR(500) | NULL | Ubicación |
| size_bytes | BIGINT UNSIGNED | NULL | Tamaño |
| is_encrypted | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Cifrado |
| includes_erasure_ledger | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Incluye el libro de supresiones |
| integrity_checked_at | DATETIME | NULL | Verificación de integridad |
| integrity_ok | TINYINT(1) UNSIGNED | NULL | Resultado |
| started_at | DATETIME | NULL | Inicio |
| finished_at | DATETIME | NULL | Fin |
| error | TEXT | NULL | Error |

### 4.17 `system_updates` · P1

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| from_version | VARCHAR(20) | NOT NULL | Versión de origen |
| to_version | VARCHAR(20) | NOT NULL | Versión de destino |
| status | ENUM('started','ok','failed','rolled_back') | NOT NULL | Resultado |
| package_hash | CHAR(64) | NULL | SHA-256 del paquete |
| signature_valid | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Firma verificada |
| backup_run_id | BIGINT UNSIGNED | NULL, FK `backup_runs` SET NULL | Respaldo previo |
| started_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien ejecutó |
| started_at | DATETIME | NULL | Inicio |
| finished_at | DATETIME | NULL | Fin |
| notes | TEXT | NULL | Detalle o error |

### 4.18 `authorization_overrides` · P0 · AO

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| type | ENUM('discount_over_cap','price_override','below_cost','void_sale','void_item','return','no_sale','cash_movement','stock_adjustment','cash_difference','other') | NOT NULL, INDEX | Acción autorizada |
| requested_by_user_id | BIGINT UNSIGNED | NOT NULL, FK `users` | Solicitante |
| authorized_by_user_id | BIGINT UNSIGNED | NOT NULL, FK `users`, INDEX | Autorizador |
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal |
| pos_station_id | BIGINT UNSIGNED | NULL, FK `pos_stations` SET NULL | Estación |
| reference_type | VARCHAR(50) | NULL | Entidad afectada (`sale`, `sale_item`, `cash_session`, …) |
| reference_id | BIGINT UNSIGNED | NULL | ID de la entidad (sin FK) |
| old_value | VARCHAR(100) | NULL | Valor previo |
| new_value | VARCHAR(100) | NULL | Valor autorizado |
| reason_code_id | BIGINT UNSIGNED | NULL, FK `reason_codes` SET NULL | Motivo estándar |
| reason | VARCHAR(255) | NULL | Motivo libre |

Índices: `(branch_id, created_at)`, `(reference_type, reference_id)`.

### 4.19 `audit_logs` · P0 · AO-P

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien actúa (`NULL` = proceso) |
| branch_id | BIGINT UNSIGNED | NULL | Contexto |
| module_code | VARCHAR(30) | NULL, INDEX | Módulo |
| event | VARCHAR(60) | NOT NULL, INDEX | Ej.: `customer.revealed`, `customer.exported`, `customer.anonymized`, `price.changed`, `auth.failed`, `role.changed`, `backup.downloaded`, `fiscal.authority_confirmed` |
| auditable_type | VARCHAR(60) | NULL | Entidad |
| auditable_id | BIGINT UNSIGNED | NULL | ID (sin FK) |
| old_values | JSON | NULL | Valores previos (sin contraseñas, PIN, tokens ni RUT completo) |
| new_values | JSON | NULL | Valores nuevos |
| is_personal_data_access | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0, INDEX | Acceso o cambio de datos personales |
| is_security_event | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0, INDEX | Evento de seguridad |
| ip_address | VARCHAR(45) | NULL | IP |
| user_agent | VARCHAR(255) | NULL | Navegador |

Índices: `(auditable_type, auditable_id)`, `(user_id, created_at)`, `(is_security_event, created_at)`.

---

## 5. Catálogo, combos, promociones y listas de precio

### 5.1 `tax_rates` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(20) | NOT NULL, UNIQUE | `IVA19`, `EXENTO` |
| name | VARCHAR(80) | NOT NULL | Nombre |
| rate | DECIMAL(5,2) | NOT NULL | Porcentaje |
| is_exempt | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Exento |
| is_default | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Tasa por defecto |
| fiscal_code | VARCHAR(10) | NULL | Código para el emisor DTE |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |

### 5.2 `categories` · P0 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| parent_id | BIGINT UNSIGNED | NULL, FK `categories` RESTRICT | Categoría padre |
| name | VARCHAR(150) | NOT NULL | Nombre |
| slug | VARCHAR(170) | NOT NULL, UNIQUE | URL amigable |
| max_discount_percent | DECIMAL(5,2) | NULL | Tope heredable por sus productos |
| default_tax_rate_id | BIGINT UNSIGNED | NULL, FK `tax_rates` SET NULL | Impuesto por defecto |
| sort_order | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Orden |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |

### 5.3 `brands`, `tags`, `attributes`, `attribute_values` · P0

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `brands` · SD | name | VARCHAR(150) | NOT NULL | Nombre |
| | slug | VARCHAR(170) | NOT NULL, UNIQUE | URL amigable |
| | description | VARCHAR(255) | NULL | Descripción |
| | logo_url | VARCHAR(500) | NULL | Logo |
| | is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |
| `tags` | name | VARCHAR(100) | NOT NULL | Nombre |
| | slug | VARCHAR(120) | NOT NULL, UNIQUE | URL amigable |
| `attributes` | name | VARCHAR(100) | NOT NULL, UNIQUE | Ej.: Color, Talla |
| `attribute_values` | attribute_id | BIGINT UNSIGNED | NOT NULL, FK `attributes` CASCADE | Atributo |
| | value | VARCHAR(100) | NOT NULL | Ej.: Rojo. Único `(attribute_id, value)` |

### 5.4 `products` · P0 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| category_id | BIGINT UNSIGNED | NULL, FK `categories` SET NULL, INDEX | Categoría principal |
| brand_id | BIGINT UNSIGNED | NULL, FK `brands` SET NULL, INDEX | Marca |
| type | ENUM('simple','variable','combo') | NOT NULL, DEFAULT 'simple', INDEX | Tipo |
| name | VARCHAR(255) | NOT NULL, FULLTEXT | Nombre |
| description | TEXT | NULL | Contenido maestro. Por defecto el POS es el maestro; solo el canal designado en `content_source_channel_id` puede actualizarlo (RN-44) |
| internal_code | VARCHAR(60) | NULL, UNIQUE | Código propio |
| image_url | VARCHAR(500) | NULL | Imagen (URL); misma regla de autoridad que `description` |
| tax_rate_id | BIGINT UNSIGNED | NULL, FK `tax_rates` SET NULL | Impuesto (`NULL` hereda) |
| tracks_stock | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | `0` = servicio |
| is_sellable | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | `0` = insumo |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Vigente |
| origin | ENUM('pos','channel','import') | NOT NULL, DEFAULT 'pos' | Dónde nació |

### 5.5 `product_variants` · P0 · SD
Unidad vendible. Un producto simple tiene exactamente una variante. No existe precio web ni indicador de publicación: el precio por canal sale de `price_lists` y la publicación de `channel_publications`.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| product_id | BIGINT UNSIGNED | NOT NULL, FK `products` CASCADE | Producto |
| sku | VARCHAR(100) | NOT NULL, UNIQUE | Código de la variante |
| price | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Precio base general (con o sin impuesto según `prices_include_tax`) |
| cost_price | DECIMAL(14,4) | NOT NULL, DEFAULT 0 | Costo **neto** promedio vigente |
| last_cost | DECIMAL(14,4) | NULL | Último costo de compra |
| unit | ENUM('unit','kg','g','lt','ml','m','box','other') | NOT NULL, DEFAULT 'unit' | Unidad |
| sold_by_weight | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Se vende por peso |
| max_discount_percent | DECIMAL(5,2) | NULL | Tope del producto |
| min_price | DECIMAL(14,2) | NULL | Precio mínimo de venta |
| allow_price_override | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | `0` = precio bloqueado incluso con PIN |
| tax_rate_id | BIGINT UNSIGNED | NULL, FK `tax_rates` SET NULL | Sobrescribe el del producto |
| combo_deducts_components | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Solo combos: descuenta stock de componentes |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Vigente |

Margen y markup se **calculan**; no se guardan.

### 5.6 `product_barcodes`, `product_tag` y `product_variant_attribute_values` · P0

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `product_barcodes` | variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` CASCADE | Variante |
| | barcode | VARCHAR(64) | NOT NULL, UNIQUE | Código |
| | type | ENUM('ean13','ean8','upc','code128','internal','other') | NOT NULL, DEFAULT 'ean13' | Simbología |
| | is_primary | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Principal |
| `product_tag` (PK compuesta) | product_id | BIGINT UNSIGNED | PK, FK `products` CASCADE | Producto |
| | tag_id | BIGINT UNSIGNED | PK, FK `tags` CASCADE | Etiqueta |
| `product_variant_attribute_values` (PK compuesta) | variant_id | BIGINT UNSIGNED | PK, FK `product_variants` CASCADE | Variante |
| | attribute_value_id | BIGINT UNSIGNED | PK, FK `attribute_values` CASCADE | Valor de atributo |

### 5.7 `combo_components` · P1

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| combo_variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` CASCADE | Variante del combo |
| component_variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` RESTRICT | Componente |
| quantity | DECIMAL(12,3) | NOT NULL | Cantidad por combo |
| sort_order | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Orden |

Único `(combo_variant_id, component_variant_id)`. La aplicación impide combos anidados y que un combo se contenga a sí mismo.

### 5.8 `promotions` · P1 · SD
**Solo POS** (RN-08): un pedido online conserva los precios y descuentos con que lo vendió el canal.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| name | VARCHAR(150) | NOT NULL | Nombre |
| type | ENUM('percent_off','amount_off','fixed_price','buy_x_pay_y') | NOT NULL | Regla |
| value | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Porcentaje, monto o precio fijo |
| buy_qty | SMALLINT UNSIGNED | NULL | N×M: unidades que se llevan |
| pay_qty | SMALLINT UNSIGNED | NULL | N×M: unidades que se pagan |
| coupon_code | VARCHAR(40) | NULL, UNIQUE | Cupón (P2) |
| priority | SMALLINT | NOT NULL, DEFAULT 0 | Desempate |
| min_cart_amount | DECIMAL(14,2) | NULL | Monto mínimo del carrito |
| starts_at | DATETIME | NOT NULL | Inicio |
| ends_at | DATETIME | NULL | Término (`NULL` = sin término) |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Vigente |

### 5.9 `promotion_conditions` · P1
Sin filas para una promoción = aplica a todo el catálogo.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| promotion_id | BIGINT UNSIGNED | NOT NULL, FK `promotions` CASCADE | Promoción |
| variant_id | BIGINT UNSIGNED | NULL, FK `product_variants` CASCADE | Variante |
| category_id | BIGINT UNSIGNED | NULL, FK `categories` CASCADE | Categoría |
| brand_id | BIGINT UNSIGNED | NULL, FK `brands` CASCADE | Marca |
| tag_id | BIGINT UNSIGNED | NULL, FK `tags` CASCADE | Etiqueta |
| branch_id | BIGINT UNSIGNED | NULL, FK `branches` CASCADE | Sucursal (`NULL` = todas) |
| customer_group_id | BIGINT UNSIGNED | NULL, FK `customer_groups` CASCADE | Grupo de clientes |
| min_qty | DECIMAL(12,3) | NOT NULL, DEFAULT 1 | Cantidad mínima |

### 5.10 `price_lists` · P1 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NOT NULL, UNIQUE | Código |
| name | VARCHAR(120) | NOT NULL | Nombre |
| applies_to | ENUM('all','pos','online','channel') | NOT NULL, DEFAULT 'all' | Alcance de canal: todos, POS, cualquier canal online o uno específico |
| sales_channel_id | BIGINT UNSIGNED | NULL, FK `sales_channels` CASCADE | Canal específico; obligatorio si `applies_to='channel'` |
| branch_id | BIGINT UNSIGNED | NULL, FK `branches` CASCADE | Sucursal (`NULL` = todas) |
| customer_group_id | BIGINT UNSIGNED | NULL, FK `customer_groups` SET NULL | Grupo de clientes (mayorista, especial) |
| priority | SMALLINT | NOT NULL, DEFAULT 0 | Prioridad explícita |
| valid_from | DATE | NULL | Inicio de vigencia |
| valid_to | DATE | NULL | Fin de vigencia |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |

Resolución: RN-09 (prioridad → especificidad → `id`).

### 5.11 `price_list_items` · P1

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| price_list_id | BIGINT UNSIGNED | NOT NULL, FK `price_lists` CASCADE | Lista |
| variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` CASCADE | Variante |
| price | DECIMAL(14,2) | NOT NULL | Precio |
| min_qty | DECIMAL(12,3) | NOT NULL, DEFAULT 1 | Precios por volumen (P2); en P1 siempre `1` |

Único `(price_list_id, variant_id, min_qty)`.

---

## 6. Clientes y vendedores

### 6.1 `customer_groups` · P0 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NOT NULL, UNIQUE | Código |
| name | VARCHAR(100) | NOT NULL | Ej.: Mayorista, Empleados, Frecuente |
| default_discount_percent | DECIMAL(5,2) | NOT NULL, DEFAULT 0 | Descuento por defecto |
| max_discount_percent | DECIMAL(5,2) | NULL | Tope del grupo |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |

### 6.2 `customers` · P0 · SD · PII

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| type | ENUM('person','company') | NOT NULL, DEFAULT 'person' | Tipo |
| name | VARCHAR(150) | NOT NULL | Nombre o razón social |
| trade_name | VARCHAR(150) | NULL | Nombre de fantasía |
| fiscal_id_encrypted | VARCHAR(255) | NULL | RUT normalizado, **cifrado** |
| fiscal_id_hash | CHAR(64) | NULL, UNIQUE | `HMAC-SHA256(rut_normalizado, BLIND_INDEX_KEY)`; busca por RUT sin exponerlo |
| email | VARCHAR(150) | NULL, INDEX | Correo (no único: la fusión de duplicados es una herramienta) |
| phone | VARCHAR(30) | NULL | Teléfono |
| business_activity | VARCHAR(150) | NULL | Giro (solo si pide factura) |
| fiscal_address | VARCHAR(255) | NULL | Domicilio tributario (solo si pide factura) |
| fiscal_commune / fiscal_city | VARCHAR(100) | NULL | Comuna y ciudad tributarias |
| customer_group_id | BIGINT UNSIGNED | NULL, FK `customer_groups` SET NULL | Grupo |
| default_discount_percent | DECIMAL(5,2) | NULL | Descuento asignado (`NULL` hereda del grupo) |
| max_discount_percent | DECIMAL(5,2) | NULL | Tope propio (`NULL` hereda del grupo) |
| marketing_opt_in | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Caché del último consentimiento de comunicaciones |
| origin | ENUM('pos','online','import') | NOT NULL, DEFAULT 'pos' | Origen |
| last_activity_at | DATETIME | NULL | Última compra |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |
| blocked_at | DATETIME | NULL | Bloqueado: no se usa comercialmente (RN-64) |
| anonymized_at | DATETIME | NULL | Anonimizado (RN-62) |

### 6.3 `customer_addresses` · P1 · PII

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| customer_id | BIGINT UNSIGNED | NOT NULL, FK `customers` CASCADE | Cliente |
| type | ENUM('billing','shipping','other') | NOT NULL, DEFAULT 'shipping' | Tipo |
| label | VARCHAR(60) | NULL | Etiqueta |
| street | VARCHAR(255) | NOT NULL | Calle y número |
| commune | VARCHAR(100) | NULL | Comuna |
| city | VARCHAR(100) | NULL | Ciudad |
| region | VARCHAR(100) | NULL | Región |
| postal_code | VARCHAR(12) | NULL | Código postal |
| is_default | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Predeterminada |

### 6.4 `customer_consents` · P1 · AO
Solo opt-in de funciones opcionales que el propio software ofrece (RN-60). Sin base jurídica.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| customer_id | BIGINT UNSIGNED | NOT NULL, FK `customers` CASCADE, INDEX | Cliente |
| purpose | ENUM('marketing','loyalty') | NOT NULL | Función opcional |
| status | ENUM('granted','revoked') | NOT NULL | Aceptación o revocación |
| channel | ENUM('pos','online','paper','import') | NOT NULL | Dónde se registró |
| notice_version | VARCHAR(20) | NOT NULL | Versión del texto mostrado (`marketing_notice_version`); `external` si viene del canal o de una importación |
| recorded_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien lo registró |

El estado vigente es el último registro por `(customer_id, purpose)`; `customers.marketing_opt_in` es su caché.

### 6.5 `sellers` y `branch_seller` · P0

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `sellers` · SD · PII | code | VARCHAR(20) | NOT NULL, UNIQUE | Identifica, **no autentica** (RN-14) |
| | name | VARCHAR(150) | NOT NULL | Nombre |
| | user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Usuario vinculado |
| | commission_percent | DECIMAL(5,2) | NOT NULL, DEFAULT 0 | Comisión |
| | is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |
| | anonymized_at | DATETIME | NULL | Anonimizado (PRV-09) |
| `branch_seller` (PK compuesta) | seller_id | BIGINT UNSIGNED | PK, FK `sellers` CASCADE | Vendedor |
| | branch_id | BIGINT UNSIGNED | PK, FK `branches` CASCADE | Sucursal |

Fila reservada `sellers.code='NONE'` ("Sin asignar") para agregados.

---

## 7. Inventario y compras

### 7.1 `branch_variant_settings` · P0 (PK compuesta `(branch_id, variant_id)`)

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | PK, FK `branches` CASCADE | Sucursal |
| variant_id | BIGINT UNSIGNED | PK, FK `product_variants` CASCADE | Variante |
| reorder_point | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Umbral de stock crítico |
| reorder_qty | DECIMAL(12,3) | NULL | Cantidad sugerida de reposición |
| max_stock | DECIMAL(12,3) | NULL | Máximo deseado |
| safety_stock | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Colchón que **no se publica** a canales online; nunca afecta al POS |
| location_label | VARCHAR(50) | NULL | Ubicación (pasillo, estante), P2 |

### 7.2 `current_stock` · P0 (PK compuesta `(branch_id, variant_id)`)
Caché transaccional; se actualiza bajo bloqueo de fila en la misma transacción que el movimiento o la reserva.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | PK, FK `branches` CASCADE | Sucursal |
| variant_id | BIGINT UNSIGNED | PK, FK `product_variants` CASCADE | Variante |
| on_hand | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Existencia física = último `balance_after` |
| reserved | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Suma de `stock_reservations` activas |
| available | DECIMAL(12,3) | VIRTUAL `on_hand - reserved` | Disponible (puede ser negativo: RN-32) |
| last_movement_at | DATETIME | NULL | Último movimiento |
| updated_at | DATETIME | NOT NULL | Última recalculación; la fija la aplicación en la misma transacción (se declara aquí porque la tabla es de PK compuesta) |

### 7.3 `stock_reservations` · P0
Documento operativo de reserva (RN-28). Una fila por línea de documento.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal que reserva |
| variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` | Variante |
| quantity | DECIMAL(12,3) | NOT NULL | Cantidad reservada |
| status | ENUM('active','consumed','released','expired') | NOT NULL, DEFAULT 'active', INDEX | Estado |
| source_type | ENUM('ticket','online_order') | NOT NULL | Origen (no existen reservas manuales) |
| source_id | BIGINT UNSIGNED | NOT NULL | `sales.id` del ticket o pedido (sin FK) |
| source_line_key | VARCHAR(40) | NOT NULL | `sale_items.id` de la línea, como texto |
| hold_owner | ENUM('internal','channel') | NOT NULL, DEFAULT 'internal' | `channel`: el canal retiene ese stock por su cuenta (RN-30) |
| channel_id | BIGINT UNSIGNED | NULL, FK `sales_channels` SET NULL | Canal (pedidos online) |
| expires_at | DATETIME | NULL | **Tickets:** vencimiento (se libera). **Pedidos online:** momento de **reconciliar con el canal**, nunca de liberar por sí solo (RN-30). `NULL` = hasta despacho |
| resolved_at | DATETIME | NULL | Cuándo dejó de estar activa |
| resolved_reason | ENUM('converted','cancelled','expired','manual_release') | NULL | Motivo |
| created_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Creador (`NULL` = automático) |

Único `(source_type, source_id, source_line_key)`: la misma línea no se reserva dos veces aunque llegue por webhook, reintento o sondeo. `released` y `expired` se reactivan en la **misma fila**; `consumed` es final. Índices: `(status, expires_at)`, `(branch_id, variant_id, status)`.

### 7.4 `stock_movements` · P0 · AO
Kardex: **solo movimientos físicos**. Inmutable; se corrige con `reversal`.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal |
| variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` | Variante |
| type | ENUM('initial','purchase_receipt','adjustment','count_adjustment','waste','sale_pos','sale_online','customer_return','supplier_return','transfer_out','transfer_in','reversal') | NOT NULL, INDEX | Tipo |
| quantity | DECIMAL(12,3) | NOT NULL | Variación **con signo** de la existencia |
| balance_after | DECIMAL(12,3) | NOT NULL | Existencia resultante (calculada bajo el bloqueo de `current_stock`) |
| unit_cost | DECIMAL(14,4) | NULL | Costo del movimiento (entradas: compra; salidas: costo promedio vigente) |
| source_type | VARCHAR(50) | NULL | Documento de origen (`sale_item`, `goods_receipt_item`, `stock_transfer_item`, `stock_count_item`, `sale_return_item`, …) |
| source_id | BIGINT UNSIGNED | NULL | ID del origen (**sin FK**, para no acoplar el kardex a otros módulos) |
| reverses_movement_id | BIGINT UNSIGNED | NULL, FK `stock_movements` | Movimiento que corrige |
| reservation_id | BIGINT UNSIGNED | NULL, FK `stock_reservations` SET NULL | Reserva consumida por este movimiento |
| user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Responsable (`NULL` = automático) |
| reason_code_id | BIGINT UNSIGNED | NULL, FK `reason_codes` SET NULL | Motivo |
| note | VARCHAR(255) | NULL | Nota |
| occurred_at | DATETIME | NOT NULL | Momento de negocio |

Índices: `(branch_id, variant_id, id)`, `(variant_id, occurred_at)`, `(source_type, source_id)`, `(type, occurred_at)`.

### 7.5 `stock_transfers` y `stock_transfer_items` · P0

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `stock_transfers` | number | VARCHAR(30) | NOT NULL | Correlativo; único `(from_branch_id, number)` |
| | from_branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Origen |
| | to_branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Destino |
| | status | ENUM('draft','requested','in_transit','received','partially_received','cancelled') | NOT NULL, DEFAULT 'draft', INDEX | Estado |
| | requested_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Solicitó |
| | shipped_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Envió |
| | received_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Recibió |
| | requested_at / shipped_at / received_at | DATETIME | NULL | Tiempos |
| | notes | VARCHAR(500) | NULL | Notas |
| `stock_transfer_items` | transfer_id | BIGINT UNSIGNED | NOT NULL, FK `stock_transfers` CASCADE | Traspaso |
| | variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` | Variante |
| | requested_qty / shipped_qty / received_qty | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Cantidades |
| | unit_cost_snapshot | DECIMAL(14,4) | NULL | Costo al enviar |

La diferencia `shipped_qty − received_qty` queda pendiente de resolver (ajuste o reclamo).

### 7.6 `stock_counts` y `stock_count_items` · P1

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `stock_counts` | number | VARCHAR(30) | NOT NULL | Correlativo; único `(branch_id, number)` |
| | branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal |
| | type | ENUM('full','cycle','spot') | NOT NULL | Tipo |
| | status | ENUM('draft','counting','review','applied','cancelled') | NOT NULL, DEFAULT 'draft', INDEX | Estado |
| | is_blind | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Conteo ciego |
| | snapshot_at | DATETIME | NOT NULL | Momento de la foto |
| | created_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Creó |
| | applied_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Aplicó |
| | applied_at | DATETIME | NULL | Aplicación |
| | notes | VARCHAR(500) | NULL | Notas |
| `stock_count_items` | count_id | BIGINT UNSIGNED | NOT NULL, FK `stock_counts` CASCADE | Conteo |
| | variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` | Variante |
| | system_qty | DECIMAL(12,3) | NOT NULL | Existencia a `snapshot_at` |
| | counted_qty | DECIMAL(12,3) | NULL | Contado |
| | difference | DECIMAL(12,3) | VIRTUAL `counted_qty - system_qty` | Diferencia |
| | counted_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Contó |
| | counted_at | DATETIME | NULL | Momento |
| | note | VARCHAR(255) | NULL | Nota |

Al aplicar: ajuste = `contado − (foto + movimientos posteriores a la foto)` (RN-27).

### 7.7 `suppliers` · P1 · SD · PII

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NULL, UNIQUE | Código |
| name | VARCHAR(150) | NOT NULL | Nombre o razón social |
| fiscal_id_encrypted | VARCHAR(255) | NULL | RUT **cifrado** (puede ser persona natural) |
| fiscal_id_hash | CHAR(64) | NULL, UNIQUE | Índice ciego del RUT |
| contact_name | VARCHAR(150) | NULL | Contacto (opcional) |
| email | VARCHAR(150) | NULL | Correo |
| phone | VARCHAR(30) | NULL | Teléfono |
| address | VARCHAR(255) | NULL | Dirección |
| commune / city | VARCHAR(100) | NULL | Comuna y ciudad |
| payment_terms_days | SMALLINT UNSIGNED | NULL | Plazo de pago |
| notes | VARCHAR(500) | NULL | Notas |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |
| anonymized_at | DATETIME | NULL | Anonimizado (PRV-09) |

### 7.8 `goods_receipts` y `goods_receipt_items` · P1

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `goods_receipts` | number | VARCHAR(30) | NOT NULL | Correlativo; único `(branch_id, number)` |
| | branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal que recibe |
| | supplier_id | BIGINT UNSIGNED | NOT NULL, FK `suppliers` | Proveedor |
| | purchase_order_id | BIGINT UNSIGNED | NULL, FK `purchase_orders` SET NULL | Orden asociada (opcional) |
| | document_type | ENUM('invoice','delivery_note','other') | NULL | Documento del proveedor |
| | document_number | VARCHAR(30) | NULL | Número del documento |
| | status | ENUM('draft','posted','cancelled') | NOT NULL, DEFAULT 'draft' | Estado |
| | received_at | DATETIME | NOT NULL | Fecha de recepción |
| | received_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Recibió |
| | notes | VARCHAR(500) | NULL | Notas |
| `goods_receipt_items` | receipt_id | BIGINT UNSIGNED | NOT NULL, FK `goods_receipts` CASCADE | Recepción |
| | variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` | Variante |
| | quantity | DECIMAL(12,3) | NOT NULL | Cantidad |
| | unit_cost | DECIMAL(14,4) | NOT NULL | Costo neto unitario |
| | po_item_id | BIGINT UNSIGNED | NULL, FK `purchase_order_items` SET NULL | Línea de la orden |

Al contabilizar: movimiento `purchase_receipt` y actualización del costo promedio. Una recepción contabilizada que se corrige genera un `reversal`; el estado `cancelled` indica que el documento quedó sin efecto, no que el kardex se haya borrado.

### 7.9 `purchase_orders` y `purchase_order_items` · P2

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `purchase_orders` | number | VARCHAR(30) | NOT NULL, UNIQUE | Correlativo **por instalación** |
| | supplier_id | BIGINT UNSIGNED | NOT NULL, FK `suppliers` | Proveedor |
| | branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal de destino |
| | status | ENUM('draft','sent','partially_received','received','cancelled') | NOT NULL, DEFAULT 'draft' | Estado |
| | ordered_at | DATETIME | NULL | Emisión |
| | expected_at | DATE | NULL | Fecha esperada |
| | subtotal / tax_amount / total | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Montos |
| | notes | VARCHAR(500) | NULL | Notas |
| | created_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Creó |
| `purchase_order_items` | po_id | BIGINT UNSIGNED | NOT NULL, FK `purchase_orders` CASCADE | Orden |
| | variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` | Variante |
| | qty_ordered / qty_received | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Cantidades |
| | unit_cost | DECIMAL(14,4) | NOT NULL | Costo neto unitario |
| | tax_rate_id | BIGINT UNSIGNED | NULL, FK `tax_rates` SET NULL | Impuesto |
| | line_total | DECIMAL(14,2) | NOT NULL | Total de la línea |

---

## 8. Ventas y caja

### 8.1 `payment_methods` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| code | VARCHAR(30) | NOT NULL, UNIQUE | `cash`, `debit_card`, `credit_card`, `bank_transfer`, `webpay`, `cash_on_delivery`, `on_account`, `voucher`, `other` |
| name | VARCHAR(80) | NOT NULL | Nombre visible |
| kind | ENUM('cash','card','transfer','credit','voucher','other') | NOT NULL | Clase |
| affects_cash_drawer | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Mueve efectivo del cajón |
| requires_reference | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Exige referencia |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Vigente |
| sort_order | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Orden |

### 8.2 `cash_sessions` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| number | VARCHAR(30) | NOT NULL | Correlativo; único `(branch_id, number)` |
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal |
| pos_station_id | BIGINT UNSIGNED | NULL, FK `pos_stations` SET NULL | Estación |
| user_id | BIGINT UNSIGNED | NOT NULL, FK `users` | Cajero responsable |
| status | ENUM('open','closed') | NOT NULL, DEFAULT 'open', INDEX | Estado |
| opening_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Efectivo inicial |
| expected_cash | DECIMAL(14,2) | NULL | Esperado al cierre |
| counted_cash | DECIMAL(14,2) | NULL | Declarado al cierre |
| difference | DECIMAL(14,2) | NULL | Diferencia |
| is_blind_close | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Instantánea de la configuración |
| approval_override_id | BIGINT UNSIGNED | NULL, FK `authorization_overrides` SET NULL | Aprobación de la diferencia |
| opened_at | DATETIME | NOT NULL | Apertura |
| closed_at | DATETIME | NULL | Cierre |
| closed_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien cierra |
| notes | TEXT | NULL | Notas |
| open_station_key | BIGINT UNSIGNED | VIRTUAL `IF(status='open', pos_station_id, NULL)`, UNIQUE | Una sesión abierta por estación |
| open_user_key | BIGINT UNSIGNED | VIRTUAL `IF(status='open', user_id, NULL)`, UNIQUE | Una sesión abierta por usuario |

### 8.3 `cash_session_totals` y `cash_movements` · P0

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `cash_session_totals` | cash_session_id | BIGINT UNSIGNED | NOT NULL, FK `cash_sessions` CASCADE | Sesión |
| | payment_method_id | BIGINT UNSIGNED | NOT NULL, FK `payment_methods` | Método |
| | system_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Total según el sistema |
| | declared_amount | DECIMAL(14,2) | NULL | Declarado (solo efectivo u otros contables) |
| | difference | DECIMAL(14,2) | NULL | Diferencia |
| | transactions_count | INT UNSIGNED | NOT NULL, DEFAULT 0 | Cantidad de transacciones |
| `cash_movements` · AO | cash_session_id | BIGINT UNSIGNED | NOT NULL, FK `cash_sessions` | Sesión |
| | type | ENUM('cash_in','cash_out','no_sale') | NOT NULL | Ingreso, retiro o cajón sin venta |
| | amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Monto (`0` en `no_sale`) |
| | reason_code_id | BIGINT UNSIGNED | NULL, FK `reason_codes` SET NULL | Motivo |
| | note | VARCHAR(255) | NULL | Nota |
| | user_id | BIGINT UNSIGNED | NOT NULL, FK `users` | Responsable |
| | authorization_override_id | BIGINT UNSIGNED | NULL, FK `authorization_overrides` SET NULL | Autorización |

`cash_session_totals` es único por `(cash_session_id, payment_method_id)`.

### 8.4 `sales` · P0 · ajustado v2.2
Documento comercial **único** para cualquier origen (POS u online). Sin `deleted_at`: se anula por estado.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches`, INDEX | Sucursal que registra o cumple (en pedidos online, la sucursal de cumplimiento del canal) |
| origin | ENUM('pos','online') | NOT NULL, DEFAULT 'pos', INDEX | Origen |
| channel_id | BIGINT UNSIGNED | NULL, FK `sales_channels` SET NULL | Canal (si es online) |
| pos_station_id | BIGINT UNSIGNED | NULL, FK `pos_stations` SET NULL | Estación |
| cash_session_id | BIGINT UNSIGNED | NULL, FK `cash_sessions` SET NULL, INDEX | Sesión de caja (nula si la sucursal no controla caja) |
| customer_id | BIGINT UNSIGNED | NULL, FK `customers` SET NULL | Cliente. Nulo por defecto en pedidos online; asociación por RUT o por correo único (RN-47) |
| seller_id | BIGINT UNSIGNED | NULL, FK `sellers` SET NULL, INDEX | Vendedor atribuido |
| created_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien creó el documento |
| cashier_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien cobró |
| client_uuid | CHAR(36) | NULL, UNIQUE | Idempotencia de envío |
| kind | ENUM('sale','quote') | NOT NULL, DEFAULT 'sale' | Venta o cotización (P2) |
| flow | ENUM('direct','two_step','online') | NOT NULL | Cómo se originó |
| status | ENUM('draft','held','pending_payment','completed','cancelled') | NOT NULL, INDEX | Ciclo de vida |
| return_status | ENUM('none','partial','full') | NOT NULL, DEFAULT 'none' | Devoluciones |
| fulfillment_status | ENUM('not_applicable','pending_assignment','assigned','preparing','ready_for_pickup','in_delivery','delivered','cancelled') | NOT NULL, DEFAULT 'not_applicable' | Despacho. En P1, los pedidos online parten en `assigned`; `pending_assignment` queda para P2 |
| fiscal_status | ENUM('not_required','external_unverified','external_declared','pending','issued','action_required') | NOT NULL, DEFAULT 'not_required', INDEX | Resumen fiscal; en modalidad `external`, usar `external_unverified` hasta declaración voluntaria; `external_declared` NO significa emisión verificada por StockFlash. Estados originales conservados en modo integrado |
| tax_documentation_mode | ENUM('external','integrated') | NOT NULL, DEFAULT 'external' | Fotografía del modo aplicado a esta venta; no asumir que `external` equivale a operación exenta. Habilitar `integrated` solo con Fiscal configurado |
| requested_document | ENUM('boleta','factura') | NULL | Lo que pide el cliente (`NULL` = por defecto) |
| ticket_number | VARCHAR(30) | NULL | Número de ticket o cotización; único `(branch_id, ticket_number)` |
| ticket_code | CHAR(8) | NULL, UNIQUE | Código corto para escanear |
| sale_number | VARCHAR(30) | NULL | Número de venta (al cobrar); único `(branch_id, sale_number)` |
| hold_label | VARCHAR(60) | NULL | Etiqueta de venta en espera |
| held_by_pos_station_id | BIGINT UNSIGNED | NULL, FK `pos_stations` SET NULL, INDEX | Estación propietaria de `held`; búsqueda y recuperación autenticadas exclusivamente por esta estación; no aplica tickets `two_step` |
| external_order_id | VARCHAR(40) | NULL | ID del pedido en el canal |
| external_order_number | VARCHAR(40) | NULL | Número visible en el canal |
| external_status | VARCHAR(30) | NULL | Estado original en el canal |
| external_modified_at | DATETIME | NULL | Fecha de modificación del canal: protege contra eventos fuera de orden (RN-40) |
| currency_code | CHAR(3) | NOT NULL, DEFAULT 'CLP' | Moneda |
| gross_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Suma a precio de referencia |
| discount_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Descuentos |
| net_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Neto |
| exempt_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Exento |
| tax_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Impuesto |
| rounding_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Redondeo de efectivo |
| total_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Total |
| paid_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Pagado |
| change_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Vuelto |
| global_discount_percent | DECIMAL(5,2) | NULL | Descuento global |
| customer_name_snapshot | VARCHAR(150) | NULL, PII | Solo el nombre visible del cliente al vender |
| shipping_snapshot | JSON | NULL, PII | Datos de despacho (nombre, calle, comuna, ciudad, región, teléfono) solo si hay despacho; se limpia a los `retention_shipping_days` de entregado |
| shipping_cleared_at | DATETIME | NULL | Cuándo se limpió `shipping_snapshot` |
| notes | VARCHAR(500) | NULL | Notas |
| expires_at | DATETIME | NULL | Vencimiento del ticket o de la venta en espera |
| completed_at | DATETIME | NULL | Completada |
| cancelled_at | DATETIME | NULL | Anulada |
| cancelled_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien anula |
| cancel_reason_code_id | BIGINT UNSIGNED | NULL, FK `reason_codes` SET NULL | Motivo |

Únicos: `(channel_id, external_order_id)` (un solo registro por pedido, RN-38), `(branch_id, ticket_number)`, `(branch_id, sale_number)`; los `NULL` no colisionan. Índices: `(branch_id, status, created_at)`, `(seller_id, created_at)`, `(customer_id, created_at)`, `(channel_id, external_modified_at)`.
Las incidencias de revisión (stock, vinculación, factura, pago, reembolso) viven en `sale_review_issues` (sección 8.8); una venta puede tener varias a la vez.

### 8.4A `sale_external_tax_records` · P0 comercial v2.2 · documentación externa DECLARATIVA

Solo existe cuando el operador registra voluntariamente una referencia externa; **no se requiere fila ni folio para completar ventas**. No generar XML fiscal ni asumir validación del SII; no utilizar como sustituto de `fiscal_documents` emitidos por motor integrado.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| sale_id | BIGINT UNSIGNED | NOT NULL, FK `sales`, INDEX | Venta documentada externamente |
| declared_document_type | VARCHAR(40) | NULL | Tipo declarado, sin validar tributariamente |
| external_reference | VARCHAR(100) | NULL | Folio o referencia voluntaria; puede no existir |
| declared_issued_at | DATETIME | NULL | Fecha informada por usuario, no verificada por StockFlash |
| recorded_by_user_id | BIGINT UNSIGNED | NOT NULL, FK `users` | Usuario responsable de la declaración |
| recorded_at | DATETIME | NOT NULL | Fecha de registro del dato externo |
| notes | VARCHAR(255) | NULL | Observación administrativa, sin datos tributarios sensibles innecesarios |

**Unicidad inicial:** `(sale_id)` para registro simple; correcciones se auditan, no se sobrescribe el registro histórico sin trazabilidad. La política de múltiples DTE externos asociados a una venta y pagos mixtos queda ABIERTA: no implementar este detalle ambiguo sin ADR. La ausencia de fila significa **sin documentación externa registrada**, no «exento de emitir DTE».


### 8.5 `sale_items` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| sale_id | BIGINT UNSIGNED | NOT NULL, FK `sales` CASCADE, INDEX | Venta |
| line_no | SMALLINT UNSIGNED | NOT NULL | Posición |
| variant_id | BIGINT UNSIGNED | NULL, FK `product_variants`, INDEX | Variante. **Nula solo en líneas externas aún no vinculadas** (RN-46); `CHECK`: `variant_id` o `external_line_id` no nulo |
| product_name_snapshot | VARCHAR(255) | NOT NULL | Nombre al vender |
| sku_snapshot | VARCHAR(100) | NOT NULL | SKU al vender |
| quantity | DECIMAL(12,3) | NOT NULL | Cantidad |
| returned_quantity | DECIMAL(12,3) | NOT NULL, DEFAULT 0 | Devuelto |
| unit_list_price | DECIMAL(14,2) | NOT NULL | Precio de referencia `P0` (RN-01) |
| unit_price | DECIMAL(14,2) | NOT NULL | Precio final por unidad (en pedidos online, el pagado según el canal) |
| discount_percent | DECIMAL(5,2) | NOT NULL, DEFAULT 0 | Descuento efectivo `D` |
| discount_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Descuento de la línea |
| discount_source | ENUM('none','price_list','promotion','customer','manual','override','channel') | NOT NULL, DEFAULT 'none' | Origen del descuento |
| promotion_id | BIGINT UNSIGNED | NULL, FK `promotions` SET NULL | Promoción aplicada |
| is_price_overridden | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Precio manual |
| original_unit_price | DECIMAL(14,2) | NULL | Precio antes del cambio manual |
| override_id | BIGINT UNSIGNED | NULL, FK `authorization_overrides` SET NULL | Autorización asociada |
| tax_rate_id | BIGINT UNSIGNED | NULL, FK `tax_rates` SET NULL | Impuesto |
| tax_rate_applied | DECIMAL(5,2) | NOT NULL, DEFAULT 0 | Tasa al vender |
| net_amount | DECIMAL(14,2) | NOT NULL | Neto de la línea |
| tax_amount | DECIMAL(14,2) | NOT NULL | Impuesto de la línea |
| line_total | DECIMAL(14,2) | NOT NULL | Total de la línea |
| unit_cost_snapshot | DECIMAL(14,4) | NULL | Costo vigente (margen histórico) |
| external_line_id | VARCHAR(40) | NULL | ID de línea en el canal. Único `(sale_id, external_line_id)`; las ventas POS lo dejan nulo y coexisten |
| external_sku | VARCHAR(100) | NULL | SKU remoto, si el canal lo informa (líneas no vinculadas) |
| notes | VARCHAR(255) | NULL | Nota |

Una línea **sin variante** conserva siempre `external_line_id`, `product_name_snapshot`, `external_sku`, cantidad, precio, impuestos y total. No genera reserva ni movimiento de stock hasta vincularla; al vincular se asigna `variant_id` y se ejecuta la reserva y la revisión correspondientes (RN-46). `unit_cost_snapshot` queda nulo.

### 8.6 `sale_payments` · P0

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| sale_id | BIGINT UNSIGNED | NOT NULL, FK `sales` CASCADE | Venta |
| payment_method_id | BIGINT UNSIGNED | NULL, FK `payment_methods` | Método interno. Nulo **solo** en pagos de canal cuya pasarela no tiene mapeo (incidencia `payment_mismatch`) |
| source | ENUM('pos','channel') | NOT NULL, DEFAULT 'pos' | `channel`: pago importado del canal, que es su autoridad (RN-42) |
| external_gateway_id | VARCHAR(60) | NULL | Pasarela informada por el canal (se conserva aunque no tenga mapeo) |
| external_gateway_title | VARCHAR(120) | NULL | Nombre visible de la pasarela en el canal |
| channel_payment_key | BIGINT UNSIGNED | VIRTUAL `IF(source='channel', sale_id, NULL)`, UNIQUE | A lo más **un pago principal originado en el canal por venta** |
| amount | DECIMAL(14,2) | NOT NULL | Monto aplicado a la venta |
| tendered_amount | DECIMAL(14,2) | NULL | Efectivo entregado |
| change_amount | DECIMAL(14,2) | NULL | Vuelto |
| reference | VARCHAR(100) | NULL | Folio de transacción o de la pasarela |
| status | ENUM('pending','confirmed','cancelled') | NOT NULL, DEFAULT 'confirmed' | Los pagos de POS nacen `confirmed`; los del canal nacen `pending` hasta que el canal informe el pago, y pasan a `cancelled` si el pedido se cancela o falla |
| cash_session_id | BIGINT UNSIGNED | NULL, FK `cash_sessions` SET NULL | Sesión de caja |
| received_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien recibió (`NULL` si es del canal) |
| paid_at | DATETIME | NULL | Momento del pago; **nulo mientras el pago está `pending`** (`CHECK`: `confirmed` ⇒ no nulo) |
| notes | VARCHAR(255) | NULL | Notas |

Índice `(cash_session_id, payment_method_id)`: base del cierre de caja. Único `channel_payment_key`: **un solo pago principal originado en el canal por venta** (pagos online divididos: P2), de modo que el mismo pedido recibido por webhook y por sondeo nunca crea dos pagos. Los pagos de canal no tienen `cash_session_id`.

### 8.7 `sale_returns` y `sale_return_items` · P0

| Tabla | Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|---|
| `sale_returns` | number | VARCHAR(30) | NOT NULL | Correlativo; único `(branch_id, number)` |
| | sale_id | BIGINT UNSIGNED | NOT NULL, FK `sales` | Venta original |
| | branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal |
| | customer_id | BIGINT UNSIGNED | NULL, FK `customers` SET NULL | Cliente |
| | status | ENUM('draft','pending_review','completed','cancelled') | NOT NULL, DEFAULT 'draft' | Estado. Canal: solo `pending_review`/`completed`; `cancelled` se reserva a devoluciones POS no finalizadas (RN-75) |
| | source | ENUM('pos','channel') | NOT NULL, DEFAULT 'pos' | Origen del reembolso |
| | channel_id | BIGINT UNSIGNED | NULL, FK `sales_channels` SET NULL | Canal (reembolsos externos) |
| | external_refund_id | VARCHAR(40) | NULL | Identidad estable del reembolso en el canal |
| | external_refunded_at | DATETIME | NULL | Fecha del reembolso según el canal |
| | reason_code_id | BIGINT UNSIGNED | NULL, FK `reason_codes` SET NULL | Motivo |
| | refund_method_id | BIGINT UNSIGNED | NULL, FK `payment_methods` | Método de reembolso |
| | refund_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | **Magnitud positiva** del total reembolsado por el canal/POS; puede incluir conceptos no asignados a líneas de producto |
| | cash_session_id | BIGINT UNSIGNED | NULL, FK `cash_sessions` SET NULL | Sesión de caja |
| | authorization_override_id | BIGINT UNSIGNED | NULL, FK `authorization_overrides` SET NULL | Autorización |
| | created_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien la registra; **`NULL` si se importó del canal** (`source='channel'`) |
| | completed_at | DATETIME | NULL | Cierre |
| | notes | VARCHAR(500) | NULL | Notas |
| `sale_return_items` | return_id | BIGINT UNSIGNED | NOT NULL, FK `sale_returns` CASCADE | Devolución/refund |
| | sale_item_id | BIGINT UNSIGNED | NOT NULL, FK `sale_items` | Línea original; único junto con `return_id` |
| | variant_id | BIGINT UNSIGNED | NULL, FK `product_variants` | Variante. Nula solo si la línea original aún no está vinculada (RN-46): el hecho financiero se conserva y el efecto físico se difiere |
| | quantity | DECIMAL(12,3) | NOT NULL | **Magnitud positiva** de cantidad reembolsada/devuelta |
| | unit_price | DECIMAL(14,2) | NOT NULL | Precio unitario de referencia de la línea original; no sustituye los importes efectivos del refund |
| | net_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | **Magnitud positiva** reembolsada por esta línea, excluyendo impuesto, normalizada desde el canal |
| | tax_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | **Magnitud positiva** del impuesto reembolsado por esta línea |
| | line_amount | DECIMAL(14,2) | NOT NULL | Total reembolsado de la línea, magnitud positiva; contrato: `net_amount + tax_amount` |
| | restock | TINYINT(1) UNSIGNED | NULL, DEFAULT NULL | Decisión física: `NULL` pendiente, `1` reingresa a stock, `0` no reingresa/merma. En devoluciones POS debe definirse antes de completar |

**Unicidades:** `(channel_id, external_refund_id)` en `sale_returns` (los `NULL` de POS coexisten) y `(return_id, sale_item_id)` en `sale_return_items`. Un webhook/sondeo repetido actualiza el mismo refund y no duplica líneas.

**Reglas de integridad (RN-75):**
- `CHECK (source='pos' OR status IN ('pending_review','completed'))`: un refund del canal no puede quedar `draft` ni `cancelled`.
- Refund externo `completed` ⇒ toda línea con `variant_id IS NOT NULL` tiene `restock IS NOT NULL`.
- El adaptador normaliza signos remotos: cantidades e importes internos de refund son magnitudes positivas.
- Si `sale_returns.refund_amount != SUM(sale_return_items.line_amount)`, existe un importe no asignado a producto (por ejemplo shipping/fees/ajuste): `refund_review` permanece activo y **no se genera automáticamente la nota de crédito** hasta resolución fiscal/manual. P1 no modela esos conceptos como pseudo-productos.
- Una línea aún sin variante no elimina ni cancela el refund: solo difiere su efecto de stock hasta vincularla.

### 8.8 `sale_review_issues` · P1
Incidencias de revisión de una venta (RN-76). Una venta puede tener **varias a la vez**; ninguna reemplaza a otra. Se eligió una tabla propia porque una columna única, una lista o una máscara no permiten resolver cada incidencia por separado ni impedir duplicados con integridad.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| sale_id | BIGINT UNSIGNED | NOT NULL, FK `sales` CASCADE, INDEX | Venta |
| code | ENUM('stock_shortage','unlinked_item','invoice_data_missing','payment_mismatch','payment_overdue','refund_review') | NOT NULL | Incidencia |
| sale_item_id | BIGINT UNSIGNED | NULL, FK `sale_items` CASCADE | Línea afectada (ítem sin vincular, faltante de stock) |
| sale_return_id | BIGINT UNSIGNED | NULL, FK `sale_returns` CASCADE | Reembolso en revisión |
| detail | VARCHAR(255) | NULL | Dato mínimo para el revisor (**sin datos personales**) |
| resolved_at | DATETIME | NULL | Resolución (`NULL` = activa) |
| resolved_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien la resolvió (`NULL` = resolución automática) |
| active_key | VARCHAR(100) | VIRTUAL `IF(resolved_at IS NULL, CONCAT(sale_id,':',code,':',IFNULL(sale_item_id,0),':',IFNULL(sale_return_id,0)), NULL)`, UNIQUE | A lo más **una incidencia activa por venta, código y sujeto** (línea o reembolso); un hecho repetido no la duplica y, una vez resuelta, puede abrirse otra |

Índices: `(resolved_at, code)` para la bandeja de pedidos por revisar; `(sale_id)`.
---

## 9. Fiscal (`fiscal`)

El contribuyente (el comercio) es el emisor; el proveedor DTE es su infraestructura tecnológica (RN-50). `fiscal_documents` es la única fuente de verdad de los documentos y, en estado `pending`, la bandeja de salida fiscal (ARQ-02).

### 9.1 `fiscal_documents` · P0 (modelo) · sin `deleted_at`
**Nunca se edita ni se elimina un documento emitido**: se corrige con una nota de crédito o débito que lo referencia. El estado técnico de un documento `issued` **permanece `issued`**; su efecto comercial se deriva de la relación con la nota (consulta o vista `fiscal_documents_effective`), no de un estado propio.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | Sucursal de la venta; el adaptador la traduce a la configuración del proveedor (RN-56) |
| sale_id | BIGINT UNSIGNED | NULL, FK `sales` | Venta |
| sale_return_id | BIGINT UNSIGNED | NULL, FK `sale_returns` | Devolución (nota de crédito) |
| kind | ENUM('internal_receipt','boleta','boleta_exempt','factura','factura_exempt','credit_note','debit_note','dispatch_guide') | NOT NULL | Tipo |
| sii_type_code | SMALLINT UNSIGNED | NULL | 39, 41, 33, 34, 61, 56, 52 (`NULL` en el comprobante interno) |
| purpose | ENUM('sale','return','cancel','correction') | NOT NULL, DEFAULT 'sale' | Propósito |
| revision | SMALLINT UNSIGNED | NOT NULL, DEFAULT 1 | Aumenta si se vuelve a emitir tras una nota de crédito |
| adapter | VARCHAR(40) | NOT NULL | Adaptador que lo emitió |
| environment | ENUM('test','production') | NOT NULL, DEFAULT 'production' | Ambiente: evita mezclar folios de pruebas y producción |
| status | ENUM('pending','sending','issued','rejected','error','unknown') | NOT NULL, DEFAULT 'pending', INDEX | Estado técnico |
| idempotency_key | VARCHAR(100) | NOT NULL, UNIQUE | Clave de idempotencia de la solicitud (se envía como referencia externa si el proveedor lo permite) |
| active_key | VARCHAR(100) | VIRTUAL `IF(status='rejected', NULL, CONCAT(IFNULL(sale_id,0),':',IFNULL(sale_return_id,0),':',kind,':',purpose,':',revision))`, UNIQUE | A lo más **un documento activo** por venta, tipo, propósito y revisión; solo `rejected` libera la clave |
| folio | BIGINT UNSIGNED | NULL | Folio asignado (documentos con efecto tributario) |
| internal_number | VARCHAR(30) | NULL | Correlativo del comprobante interno |
| provider_document_id | VARCHAR(100) | NULL | ID en el proveedor |
| track_id | VARCHAR(50) | NULL | Seguimiento ante el SII |
| references_document_id | BIGINT UNSIGNED | NULL, FK `fiscal_documents` | Documento al que corrige o referencia |
| reference_reason | VARCHAR(40) | NULL | Anula, corrige texto o corrige monto |
| net_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Neto |
| exempt_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Exento |
| tax_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Impuesto |
| total_amount | DECIMAL(14,2) | NOT NULL, DEFAULT 0 | Total |
| receiver_snapshot | JSON | NULL, PII | Receptor (RUT, razón social, giro, dirección); es el dato del propio documento y se conserva por obligación legal |
| issued_at | DATETIME | NULL | Emisión |
| xml_path | VARCHAR(500) | NULL | XML comprimido en `Storage` (conservación ≥ 6 años) |
| xml_replicated_at | DATETIME | NULL | Cuándo se copió al destino `fiscal_backup_destination` con hash verificado (`NULL` = sin réplica) |
| xml_verified_at | DATETIME | NULL | Última verificación de existencia y hash (BKP-11) |
| xml_hash | CHAR(64) | NULL | SHA-256 del XML |
| pdf_cache_path | VARCHAR(500) | NULL | PDF en caché (regenerable; se purga a los `retention_fiscal_pdf_days`) |
| pdf_cached_at | DATETIME | NULL | Cuándo se generó |
| retention_until | DATE | NULL | Conservación mínima del XML (emisión + 6 años) |
| attempts | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | Intentos |
| next_attempt_at | DATETIME | NULL | Próximo reintento permitido (solo para fallas seguras previas al envío) |
| last_error | TEXT | NULL | Último error |
| requested_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien solicitó una nota manual |
| approved_by_user_id | BIGINT UNSIGNED | NULL, FK `users` SET NULL | Quien aprobó la nota de crédito (RN-53) |

Únicos: `idempotency_key`; `active_key`; **`(environment, sii_type_code, folio)`** (impide duplicar un folio por tipo y ambiente; los `NULL` no colisionan); `(branch_id, internal_number)`. Índices: `(status, next_attempt_at)` para el barredor y `(xml_replicated_at, xml_path)` para el replicador de XML.

### 9.2 `fiscal_document_attempts` · P0 · AO

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| document_id | BIGINT UNSIGNED | NOT NULL, FK `fiscal_documents` CASCADE, INDEX | Documento |
| attempt_no | SMALLINT UNSIGNED | NOT NULL | Número de intento |
| request_hash | CHAR(64) | NOT NULL | Hash de la solicitud enviada |
| result | ENUM('ok','rejected','timeout','error') | NOT NULL | Resultado |
| http_status | SMALLINT UNSIGNED | NULL | Código HTTP |
| provider_ref | VARCHAR(100) | NULL | Referencia del proveedor |
| message | VARCHAR(500) | NULL | Mensaje |
| duration_ms | INT UNSIGNED | NULL | Duración |

Permite reconstruir lo intentado antes de resolver un estado `unknown`.

---

## 10. Canales de venta (`channels`)

El dominio **no conoce a WooCommerce**: usa `sales_channels` y mapeos genéricos. El adaptador vive en `app/Integrations/WooCommerce`.

### 10.1 `sales_channels` · P0 · SD

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| adapter | VARCHAR(30) | NOT NULL, INDEX | Adaptador (`woocommerce`; otros en el futuro) |
| name | VARCHAR(150) | NOT NULL | Nombre visible |
| base_url | VARCHAR(255) | NULL | URL del canal |
| credentials_encrypted | TEXT | NOT NULL | JSON cifrado del adaptador. Para Woo P1: `auth_method` (`wc_auth`/`manual`), `key_id` opcional, `consumer_key`, `consumer_secret`; **nunca se registra en logs ni payloads de auditoría** |
| webhook_secret_encrypted | TEXT | NULL | Secreto aleatorio con el que se crean/verifican los webhooks gestionados; cifrado y separado de las API keys |
| fulfillment_branch_id | BIGINT UNSIGNED | NOT NULL, FK `branches` | **Sucursal de cumplimiento única** del canal (RN-31): de ella sale el stock publicado y ahí se reserva |
| sync_catalog_enabled | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Sincroniza catálogo |
| sync_stock_enabled | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Publica stock |
| sync_prices_enabled | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Publica precios |
| import_orders_enabled | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Importa pedidos |
| import_customers | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Crear clientes desde pedidos online (RN-47) |
| auto_publish_new_variants | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 0 | Publicar automáticamente las variantes nuevas |
| holds_pending_stock | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | El canal retiene stock de pedidos `pending` (RN-30) |
| hold_stock_minutes | INT UNSIGNED | NOT NULL, DEFAULT 60 | Minutos de retención del canal |
| platform_version | VARCHAR(20) | NULL | Versión detectada |
| capabilities | JSON | NULL | Capacidades detectadas por preflight: namespace `wc/v3`, permisos de pedidos/productos/variaciones/refunds/webhooks, lectura de Settings si está permitida, marcas, versión/plataforma, `fractional_stock_supported`, granularidad de stock (`variation_stock_supported`/`parent_stock_mode`), escritura de precio y otros flags versionados del adaptador |
| config | JSON | NULL | Configuración específica no secreta: campos de factura, mapeo de estados, propiedad de campos, IDs/tópicos de **webhooks gestionados**, política de stock y otras opciones del adaptador |
| orders_cursor_at | DATETIME | NULL | Cursor del sondeo de pedidos (RN-41) |
| products_cursor_at | DATETIME | NULL | Cursor del sondeo de productos |
| last_synced_at | DATETIME | NULL | Última sincronización correcta |
| last_webhook_at | DATETIME | NULL | Último webhook recibido |
| last_poll_at | DATETIME | NULL | Último sondeo |
| is_active | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1, INDEX | Activo |

Único `(adapter, base_url)`. Multi-sucursal, elección por pedido y enrutamiento por comuna: P2 (tablas reservadas en la sección 12).

### 10.2 `channel_publications` · P1
Decide **qué variantes se publican a cada canal** (no existe un indicador global).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| channel_id | BIGINT UNSIGNED | NOT NULL, FK `sales_channels` CASCADE | Canal |
| variant_id | BIGINT UNSIGNED | NOT NULL, FK `product_variants` CASCADE | Variante |
| is_published | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Publicada |

Único `(channel_id, variant_id)`. Los combos no se publican por defecto.

### 10.3 `channel_mappings` · P0
Vínculo técnico con el canal. Nunca se asume que los IDs coinciden.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| channel_id | BIGINT UNSIGNED | NOT NULL, FK `sales_channels` CASCADE | Canal |
| entity_type | ENUM('variant','category','tag','brand','customer') | NOT NULL | Entidad |
| internal_id | BIGINT UNSIGNED | NOT NULL | ID interno (**sin FK**: tabla polimórfica) |
| external_id | VARCHAR(64) | NOT NULL | ID en el canal |
| external_sub_id | VARCHAR(64) | NULL | ID secundario (variación) |
| external_sku | VARCHAR(100) | NULL | SKU en el canal |
| external_key | VARCHAR(140) | VIRTUAL `CONCAT(external_id,'#',IFNULL(external_sub_id,''))` | Clave externa única |
| sync_status | ENUM('linked','pending','error') | NOT NULL, DEFAULT 'linked' | Estado |
| content_hash | CHAR(64) | NULL | Hash de lo sincronizado (evita envíos innecesarios) |
| published_quantity | DECIMAL(12,3) | NULL | Último stock publicado (solo `variant`) |
| published_price | DECIMAL(14,2) | NULL | Último precio publicado (solo `variant`) |
| published_at | DATETIME | NULL | Cuándo se publicó |
| last_synced_at | DATETIME | NULL | Última sincronización |

Únicos: `(channel_id, entity_type, internal_id)` y `(channel_id, entity_type, external_key)`. Una tarea de mantenimiento elimina mapeos huérfanos.

### 10.4 `channel_remote_items` · P1
Catálogo remoto importado como "no vinculado" (WOO-05).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| channel_id | BIGINT UNSIGNED | NOT NULL, FK `sales_channels` CASCADE | Canal |
| external_id | VARCHAR(64) | NOT NULL | ID en el canal |
| external_sub_id | VARCHAR(64) | NULL | ID de la variación |
| sku | VARCHAR(100) | NULL | SKU remoto |
| name | VARCHAR(255) | NOT NULL | Nombre remoto |
| payload | JSON | NULL | Datos del producto (sin datos personales) |
| status | ENUM('unlinked','linked','ignored') | NOT NULL, DEFAULT 'unlinked', INDEX | Estado |
| linked_variant_id | BIGINT UNSIGNED | NULL, FK `product_variants` SET NULL | Variante vinculada |
| discovered_at | DATETIME | NOT NULL | Cuándo se descubrió |
| external_key | VARCHAR(140) | VIRTUAL `CONCAT(external_id,'#',IFNULL(external_sub_id,''))` | Clave externa que evita el problema del `NULL` de `external_sub_id` |

Único `(channel_id, external_key)`: el importador puede correr las veces que sea necesario sin duplicar un producto o una variación.

### 10.5 `channel_payment_maps` · P1
Traduce cada pasarela del canal a un método interno y define reserva y disparo fiscal (CHN-04).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| channel_id | BIGINT UNSIGNED | NOT NULL, FK `sales_channels` CASCADE | Canal |
| external_gateway_id | VARCHAR(60) | NOT NULL | Pasarela en el canal (ej.: `bacs`, `cod`, `webpay_plus`) |
| payment_method_id | BIGINT UNSIGNED | NOT NULL, FK `payment_methods` | Método interno |
| payment_timing | ENUM('prepaid','manual_confirmation','on_delivery') | NOT NULL, DEFAULT 'prepaid' | Cuándo se considera pagado |
| reserve_while_pending | TINYINT(1) UNSIGNED | NOT NULL, DEFAULT 1 | Reservar stock mientras el pago está pendiente |
| pending_reservation_minutes | INT UNSIGNED | NULL | Plazo para **reconciliar** la reserva de un pedido `on-hold` (⚑ propuesta: 4.320 = 72 h para transferencia; `NULL` = hasta despacho). No libera por sí mismo (RN-30). En `pending` con retención del canal rige `hold_stock_minutes + 15` |
| fiscal_trigger | ENUM('on_paid','on_dispatch','manual') | NOT NULL, DEFAULT 'on_paid' | ⚑ Cuándo solicitar el DTE (**validar con un contador**) |

Único `(channel_id, external_gateway_id)`.

### 10.6 `channel_sync_events` · P0 (bandeja de salida **exclusiva de canales**)

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| channel_id | BIGINT UNSIGNED | NOT NULL, FK `sales_channels` CASCADE, INDEX | Canal |
| entity_type | ENUM('variant','category','tag','brand','customer','order','stock','price') | NOT NULL, INDEX | Entidad |
| entity_id | BIGINT UNSIGNED | NULL | ID interno (sin FK) |
| direction | ENUM('inbound','outbound') | NOT NULL | Sentido |
| action | VARCHAR(40) | NOT NULL | `push_stock`, `push_price`, `import_order`, `reconcile_order`, `reconcile_refunds`, `repair_webhooks`, … |
| status | ENUM('pending','processing','done','failed','dead') | NOT NULL, DEFAULT 'pending', INDEX | Estado |
| attempts | TINYINT UNSIGNED | NOT NULL, DEFAULT 0 | Intentos |
| next_attempt_at | DATETIME | NULL, INDEX | Próximo intento (retroceso exponencial) |
| dedupe_key | VARCHAR(120) | NULL | Fusiona envíos repetidos (ej.: `stock:{variant_id}`) |
| pending_dedupe_key | VARCHAR(120) | VIRTUAL `IF(status='pending', dedupe_key, NULL)` | Existe **solo mientras el evento está `pending`** |
| payload | JSON | NULL | Identificadores y banderas. **Nunca el valor de stock o precio a publicar** ni datos personales: el job lo recalcula al ejecutar (RN-77) |
| last_error | TEXT | NULL | Último error |
| processed_at | DATETIME | NULL | Cierre |

Único `(channel_id, pending_dedupe_key)`: evita encolar dos veces lo mismo mientras espera, pero **no bloquea eventos futuros** una vez procesado, y un evento `processing` no impide encolar un sucesor. **Contrato de concurrencia (RN-77):** los efectos sobre una misma entidad se serializan con `Cache::lock` (caché de base de datos hoy, Redis después); al adquirir el bloqueo el job **vuelve a leer y calcular el estado vigente** y no confía en el payload; `published_quantity` y `published_price` se actualizan solo tras la confirmación del canal; el sucesor `pending` se ejecuta después y relee el estado actual. Así, con varios workers, un valor antiguo nunca pisa a uno más nuevo. Índice `(status, next_attempt_at)`.

### 10.7 `webhook_receipts` · P1 · PII en `payload`

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| channel_id | BIGINT UNSIGNED | NOT NULL, FK `sales_channels` CASCADE | Canal |
| delivery_id | VARCHAR(100) | NOT NULL | ID de entrega del canal (idempotencia) |
| topic | VARCHAR(60) | NOT NULL | Tópico (ej.: `order.updated`) |
| resource_id | VARCHAR(40) | NULL | ID del recurso |
| resource_modified_at | DATETIME | NULL | Fecha de modificación del recurso (orden de eventos, RN-40) |
| signature_valid | TINYINT(1) UNSIGNED | NOT NULL | Resultado de la firma |
| payload_hash | CHAR(64) | NOT NULL | Hash de la carga |
| payload | MEDIUMTEXT | NULL | Carga original; se **depura** a las 72 h si se procesó y a los 14 días si falló |
| payload_scrubbed_at | DATETIME | NULL | Cuándo se depuró |
| status | ENUM('received','processed','ignored','failed') | NOT NULL, DEFAULT 'received' | Estado |
| error | VARCHAR(500) | NULL | Error |
| received_at | DATETIME | NOT NULL | Recepción |
| processed_at | DATETIME | NULL | Procesamiento |

Único `(channel_id, delivery_id)`. Si una entrega con firma válida puede persistirse, el endpoint responde 2xx aunque sea duplicada y el trabajo se procesa asíncronamente. No se devuelve 429 por volumen normal de entregas válidas; firmas inválidas sí están sujetas a rate limit por IP (RN-43/SEC-18).

---

## 11. Reportes (tablas resumen)

Todas con fecha **local** en `date`, calculadas por cron de forma incremental. **Ninguna es densa por variante**: solo guardan lo que ocurrió o agregados pequeños.

| Tabla | Columnas | Único | Volumen |
|---|---|---|---|
| `daily_sales_summaries` | `date DATE`, `branch_id FK`, `origin ENUM('pos','online','all')`, `sales_count INT`, `items_count INT`, `gross_amount`, `discount_amount`, `net_amount`, `tax_amount`, `total_amount`, `cost_amount`, `margin_amount`, `returns_amount` (DECIMAL 14,2), `cancelled_count INT`, `avg_ticket` | `(date, branch_id, origin)` | 3 filas por sucursal y día |
| `daily_payment_summaries` | `date`, `branch_id FK`, `payment_method_id FK`, `amount DECIMAL(14,2)`, `transactions_count INT` | `(date, branch_id, payment_method_id)` | ≤ 9 por sucursal y día |
| `daily_seller_sales_summaries` | `date`, `branch_id FK`, `seller_id FK NOT NULL` (usa el vendedor `NONE`), `sales_count`, `items_count`, `total_amount`, `discount_amount`, `cost_amount`, `margin_amount`, `returns_amount`, `commission_amount` | `(date, branch_id, seller_id)` | 1 por vendedor activo y día |
| `daily_product_sales_summaries` | `date`, `branch_id FK`, `variant_id FK`, `quantity DECIMAL(12,3)`, `revenue`, `discount_amount`, `cost_amount`, `margin_amount` | `(date, branch_id, variant_id)` | **Dispersa**: solo variantes vendidas ese día (≈ cientos por sucursal y día) |
| `daily_stock_valuation_summaries` | `date`, `branch_id FK`, `category_id BIGINT UNSIGNED NOT NULL DEFAULT 0` (`0` = total de la sucursal; sin FK), `on_hand_units DECIMAL(14,3)`, `valued_amount DECIMAL(14,2)`, `active_variants INT`, `stockout_variants INT` | `(date, branch_id, category_id)` | Pocas filas por sucursal y día (una por categoría), no una por variante |
| `report_runs` | `report_key VARCHAR(60)`, `period_date DATE`, `status ENUM('running','ok','failed')`, `rows INT`, `started_at`, `finished_at`, `error VARCHAR(500)` | `(report_key, period_date)` | Controla frescura y evita agregaciones duplicadas |

- El mensual se obtiene sumando los diarios.
- **Stock puntual de una variante a una fecha:** se reconstruye desde el kardex (`balance_after` del último movimiento ≤ fecha, índice `(variant_id, occurred_at)`), sin una tabla diaria por variante.
- **Instantánea mensual dispersa** (`monthly_stock_snapshots`, solo variantes con existencia ≠ 0): P2, para valorización histórica por variante (REP-06).
- En `daily_stock_valuation_summaries`, `category_id=0` representa el total de la sucursal; ninguna columna de una restricción única es nula, de modo que la base protege realmente la unicidad.

---

## 12. Tablas reservadas y artefactos fuera de la base de datos

### 12.1 Tablas reservadas (no se crean hasta su fase)

| Fase | Tabla | Propósito y columnas clave |
|---|---|---|
| P2 | `channel_branches` | Cumplimiento desde varias sucursales por canal: `channel_id`, `branch_id`, `contributes_stock`, `can_dispatch` (PK compuesta). Requiere definir antes cómo se asigna un pedido a una sucursal (WOO-11) |
| P2 | `dispatch_rules` | Enrutamiento por comuna: `channel_id`, `commune`, `region`, `branch_id`, `priority` |
| P2 | `monthly_stock_snapshots` | Instantánea mensual **dispersa** (solo variantes con existencia ≠ 0): `month_end DATE`, `branch_id`, `variant_id`, `on_hand`, `valued_amount` |
| P2 | `customer_credit_accounts` | `customer_id`, `credit_limit`, `balance`, `credit_days`, `status` |
| P2 | `customer_credit_movements` (AO) | `account_id`, `type` (`charge`, `payment`, `adjustment`), `amount`, `sale_id` NULL, `balance_after` |
| P3 | `product_lots` | `variant_id`, `branch_id`, `lot_code`, `expires_on`, `quantity` |
| P3 | `loyalty_accounts`, `loyalty_movements` | Puntos y movimientos con saldo |
| P3 | `gift_cards` | `code`, `balance`, `expires_on`, `status` |
| P3 | `layaways` | `sale_id`, `deposit_amount`, `due_on`, `status` |

### 12.2 Artefactos fuera de la base

| Artefacto | Ubicación | Descripción |
|---|---|---|
| **Libro de supresiones** | `Storage`, disco `privacy`: `erasure-ledger.ndjson` | *Append-only*: una línea por anonimización (`{"entity":"customer","id":123,"action":"anonymize","at":"…"}`), **sin datos personales**. Se incluye en cada respaldo y se copia al destino externo cuando exista. Tras restaurar **solo la base**, `privacy:reapply-ledger` reaplica las supresiones. Límite: en una pérdida total protege hasta el último respaldo (RN-66) |
| Respaldos cifrados | `Storage`, disco `backups` | Generados por `backup:run`. **Contienen** la base de datos (con ruta y hash de cada XML), el libro de supresiones y la configuración; **no** contienen PDF ni el repositorio completo de XML. Metadatos en `backup_runs` |
| XML fiscales | `Storage`, disco `fiscal` | Comprimidos; referenciados por `fiscal_documents.xml_path`; conservación ≥ 6 años. **Réplica incremental** al destino `fiscal_backup_destination` desde su creación (`xml_replicated_at`) y verificación progresiva (`xml_verified_at`). Sin réplica externa, el panel advierte **copia física única** |
| PDF fiscales (caché) | `Storage`, disco `fiscal` | Regenerables; se purgan a los `retention_fiscal_pdf_days` |
| Exportaciones con datos personales | `Storage`, disco `exports` | URL temporal; se purgan a los `retention_export_files_days` |
| Paquete de recuperación de secretos | **Fuera del servidor** (lo guarda el Admin) | Archivo cifrado con `APP_KEY`, la clave del índice ciego y la clave de respaldo (BKP-08). El sistema solo registra cuándo se descargó (`installation_info.recovery_package_exported_at`) |
| Secretos | `.env` | `APP_KEY`, `BLIND_INDEX_KEY`, `BACKUP_ENCRYPTION_KEY` y las claves públicas de firma de paquetes y de respuestas de licencia |

---

## 13. Semillas mínimas y cuenta demo

**Diferencia contractual v2.2:** `Demo Vitrina` (temporal, reseteable, datos ficticios) ≠ `free` (persistente, datos reales, cupos). `DemoDataSeeder` jamás corre en instalaciones `free`. El límite mensual se verifica LOCALMENTE sin reportar totales al heartbeat LIC-02. Determinar el conteo exacto y concurrencia antes de codificar la función de bloqueo. La licencia desde Control central incluye `products_enabled`, sin ampliar el payload saliente de LIC-02. `StockFlash Control` tiene su propio esquema, no utiliza tablas operacionales de este catálogo.

### 13.1 Semillas del sistema

| Tabla | Contenido inicial |
|---|---|
| `roles` | 5 roles con `authority_level` y topes (Vendedor 5 %, Cajero 10 %, Supervisor 30 %, Admin 100 %) |
| `permissions` / `role_permissions` | Matriz de la Parte 1, sección 6.3, con `module_code` |
| `payment_methods` | Efectivo, débito, crédito, transferencia, Webpay, contra entrega, cuenta, vale, otro |
| `tax_rates` | `IVA19` (19 %, por defecto) y `EXENTO` (0 %) |
| `reason_codes` | Oferta puntual, regalo, daño, error de digitación, empleado, vencido, pérdida, devolución, retiro de caja, etc. |
| `plan_definitions` | `demo` (vitrina) y `free` (persistente: 50 variantes/500 ventas al mes) más planes comerciales; `products_enabled` independientes de `modules_enabled`, Parte 1 §4.3 v2.2 |
| `installation_modules` | Una fila por módulo conocido (`core` siempre activo) |
| `sellers` | Fila reservada `NONE` ("Sin asignar") |
| `system_configs` | Claves de la sección 3 con sus valores por defecto |
| `installation_info` | Generada por el instalador (`installation_id` UUID v4) |
| `document_sequences` | Se crea la fila del ámbito (instalación o sucursal) al crear cada sucursal |

### 13.2 Cuenta demo (`is_demo=true`, plan `demo`)
2 sucursales (tienda y bodega) más una bodega online; 3 estaciones; un canal simulado en `channel_dry_run` con una sola sucursal de cumplimiento; ≈120 productos con variantes, un combo, una promoción y una lista mayorista; clientes con RUT sintético válido y un grupo mayorista; vendedores; historial de ventas reciente; sesiones de caja cerradas y una abierta; tickets pendientes; un traspaso en tránsito; pedidos online en distintos estados (incluido uno en `stock_shortage`); documentos fiscales de prueba. **Sin** datos personales reales, sin correo saliente y con el adaptador fiscal interno o el ambiente de pruebas del proveedor.

---

## 14. Mapa de datos personales (base de PRV-01)

Documentación del producto, **no** una funcionalidad ni un registro de tratamientos del responsable.

| Entidad / campo | Finalidad | ¿Estrictamente necesario? | Protección | Retención o salida |
|---|---|---|---|---|
| `customers`: nombre, RUT, correo, teléfono | Identificar al cliente y aplicar su descuento | Nombre y RUT solo si hay descuento o factura; el resto es opcional | RUT cifrado e índice ciego; enmascarado; revelar se audita | Anonimizar con la herramienta |
| `customers`: giro y domicilio tributario | Emitir factura | Solo si pide factura | Capturar solo en ese momento | Anonimizar |
| `customer_addresses` | Despacho o factura | Solo si hay despacho o factura | Permiso de visualización | Anonimizar |
| `sales.customer_name_snapshot` | Contexto comercial histórico | Solo el nombre visible | — | Se anonimiza |
| `sales.shipping_snapshot` | Despachar el pedido | Solo mientras se despacha | — | Se limpia a los 180 días de entregado |
| `fiscal_documents.receiver_snapshot` | Es el dato del propio documento tributario | Sí (obligación legal) | Enmascarado en pantalla | Conservación ≥ 6 años; no se anonimiza |
| Pedido online importado (webhook) | Procesar el pedido | Mientras se procesa | Firma validada | Carga depurada a las 72 h (14 días si falló) |
| `customer_consents` | Opt-in de funciones opcionales | Solo si existe la función | Registro inmutable | Permanente (evidencia) |
| `users`: nombre y correo | Autenticar | Sí | Hash de contraseña, bloqueo, MFA para Admin | Anonimizar al desvincular |
| `users`: hash de PIN, secreto TOTP, códigos de recuperación | Autorizar y segundo factor | Sí | Hash o cifrado; nunca en registros | Se eliminan al desactivar MFA |
| `users.last_login_ip`, `audit_logs.ip_address/user_agent` | Seguridad y trazabilidad | Conveniente | Acceso restringido; sin datos sensibles en valores | 24 meses |
| `sellers`: nombre | Atribución de ventas | Sí | Cada vendedor ve solo lo propio | Anonimizar |
| Actividad de cajeros y supervisores | Control interno | Sí | Permisos por rol | Según la venta |
| `suppliers`: RUT y contacto | Recepciones y compras | RUT y nombre; el contacto es opcional | RUT cifrado e índice ciego | Anonimizar |
| `system_configs.responsible_*` | Responsable de la instalación | Sí | — | **No se envía al control central** |
| Respaldos | Recuperación | Sí | AES-256; clave separada | 7 diarios y 4 semanales |

---

## 15. Índices críticos y volumen

| Tabla | Índices clave | Volumen (500 ventas/día/sucursal, 5 años) |
|---|---|---|
| `stock_movements` | `(branch_id, variant_id, id)`, `(variant_id, occurred_at)`, `(source_type, source_id)` | ≈ 1 M+ filas por sucursal; no se purga; consultas acotadas por variante y sucursal |
| `stock_reservations` | único de línea, `(status, expires_at)`, `(branch_id, variant_id, status)` | Las no activas pueden archivarse a los 90 días (mantenimiento opcional) |
| `sales` | `(branch_id, status, created_at)`, `(seller_id, created_at)`, `(customer_id, created_at)`, únicos de numeración y de canal | ≈ 900 k filas por sucursal |
| `sale_items` | `(sale_id)`, `(variant_id)` | ≈ 3–4 M filas por sucursal; los reportes leen resúmenes |
| `sale_review_issues` | `active_key` único, `(resolved_at, code)`, `(sale_id)` | Pocas filas: solo pedidos con incidencias |
| `sale_payments` | `(cash_session_id, payment_method_id)` | Base del cierre de caja |
| `product_variants` / `products` | `sku` único; `FULLTEXT` de `products.name` | Búsqueda < 300 ms con 50.000 variantes |
| `customers` | `fiscal_id_hash` único, `email` | Búsqueda por RUT por índice ciego |
| `channel_sync_events` | `(status, next_attempt_at)`, único `(channel_id, pending_dedupe_key)` | Los cerrados se purgan a los `retention_sync_events_days` (30) |
| `webhook_receipts` | único `(channel_id, delivery_id)` | Carga depurada a las 72 h o 14 días |
| `audit_logs` | `(user_id, created_at)`, `(is_security_event, created_at)` | 24 meses |
| `fiscal_documents` | `(status, next_attempt_at)`, `(xml_replicated_at, xml_path)`, únicos de clave activa y folio | ≈ 900 k documentos por sucursal. **XML:** el tamaño por archivo depende del proveedor y de la compresión; como **orientación** (no como supuesto de arquitectura), unos pocos KB comprimidos por documento implican del orden de varios GB tras 6 años en un cliente de 3 sucursales con 500 documentos diarios. **Medir con XML de muestra del proveedor** antes de dimensionar; en clientes grandes se recomienda un disco externo (ARQ-04) |
| `daily_product_sales_summaries` | `(date, branch_id, variant_id)` | Dispersa: solo lo vendido |
| `daily_stock_valuation_summaries` | `(date, branch_id, category_id)` | Una fila por categoría, sucursal y día |
| `channel_mappings` | dos únicos (interno y externo) | Sin FK por diseño; limpieza periódica de huérfanos |

---

## 16. Relaciones clave

- `users.global_role_id → roles`; `users ↔ branches` vía `branch_user` (con `role_id`); `roles ↔ permissions` vía `role_permissions`.
- `products → categories, brands`; `product_variants → products`; `product_barcodes → product_variants`; `combo_components → product_variants (×2)`.
- `price_lists → branches, customer_groups, sales_channels`; `price_list_items → price_lists, product_variants`.
- `current_stock`, `branch_variant_settings`, `stock_movements`, `stock_reservations → branches + product_variants`; `stock_movements.reservation_id → stock_reservations`; `stock_movements.reverses_movement_id → stock_movements`.
- `sales_channels.fulfillment_branch_id → branches`; `channel_publications`, `channel_mappings`, `channel_remote_items`, `channel_payment_maps`, `channel_sync_events`, `webhook_receipts → sales_channels`.
- `sales → branches, pos_stations, cash_sessions, customers, sellers, sales_channels, users`; `sale_items → sales, product_variants, promotions, authorization_overrides`; `sale_payments → sales, payment_methods, cash_sessions`; `sale_returns → sales`; `sale_return_items → sale_returns, sale_items`.
- `sale_review_issues → sales, sale_items, sale_returns, users`; `sale_returns → sales, sales_channels`.
- `cash_sessions → branches, pos_stations, users`; `cash_session_totals`, `cash_movements → cash_sessions`.
- `fiscal_documents → branches, sales | sale_returns`; `fiscal_documents.references_document_id → fiscal_documents`; `fiscal_document_attempts → fiscal_documents`.
- `customers → customer_groups`; `customer_addresses`, `customer_consents → customers`; `promotion_conditions → promotions` y catálogo.
- `system_updates → backup_runs`; `integration_health` se relaciona por `integration_key` (sin FK).
- **Referencias polimórficas sin FK (por diseño):** `stock_movements.source_*`, `stock_reservations.source_*`, `channel_mappings.internal_id`, `authorization_overrides.reference_*`, `audit_logs.auditable_*`, `channel_sync_events.entity_id`.

---

## 17. Auditoría cruzada (requerimientos ↔ modelo)

Auditoría ejecutada sobre los textos finales de la Parte 1 y la Parte 2 mediante verificación automática de presencia, existencia de definiciones, IDs y referencias cruzadas. **No incluye la ejecución contra una base MariaDB real** (ver 17.3).

### 17.1 Correcciones de la revisión final: dónde quedaron

| # | Corrección | Parte 1 | Parte 2 | Resultado |
|---|---|---|---|:-:|
| 1 | Ítem no vinculado en pedido online | RN-46, RN-38, SAL-16 | `sale_items.variant_id` nulo solo en líneas externas; `external_sku`; único `(sale_id, external_line_id)`; `CHECK`; `sale_return_items.variant_id` nulo | ✔ |
| 2 | Pagos online pendientes | RN-42, SAL-20, CHN-13 | `sale_payments`: `paid_at` nulo, estado `pending/confirmed/cancelled`, `external_gateway_id/title`, `payment_method_id` nulo si no hay mapeo, único `channel_payment_key` | ✔ |
| 3 | Reembolsos del canal | RN-75, CHN-15, CHN-17 (P2), WOO-12/16, SAL-22, §10.9 | `sale_returns`: identidad externa y total; `sale_return_items`: único `(return_id,sale_item_id)`, importes reales `net/tax/line`, `restock` triestado; diferencias no asignadas bloquean NC automática | ✔ |
| 4 | Incidencias simultáneas | RN-76, SAL-21 | Tabla `sale_review_issues` con `active_key` único por venta, código y sujeto; ya no existe una columna única de revisión en `sales` | ✔ |
| 5 | Reservas online sin liberar a ciegas | RN-28, RN-30, INV-18, CHN-10 | `stock_reservations.expires_at` = momento de reconciliar; acción `reconcile_order` | ✔ |
| 6 | Limitación de `checkout-draft` | RN-30, RN-38, RN-39 | — | ✔ |
| 7 | Concurrencia de `channel_sync_events` | RN-77, ARQ-12, CHN-16, WOO-03 | Contrato con `Cache::lock` y relectura; `payload` sin valores a publicar | ✔ |
| 8 | Respaldo y XML fiscales | BKP-01, BKP-05, BKP-09…11, RN-55, FIS-09, INT-01 | `fiscal_documents.xml_replicated_at` y `xml_verified_at`; `fiscal_backup_destination`; estimación de tamaño solo orientativa | ✔ |
| 9 | Disparo fiscal | RN-18, RN-51, FIS-03, §4.4, §10.4 | — | ✔ |
| 10 | Autoridad de contenido multicanal | RN-44, WOO-07 | `content_source_channel_id`; `products.description` e `image_url` | ✔ |
| 11.1 | `current_stock.updated_at` | — | `DATETIME`, igual que el resto del modelo (ninguna columna usa otro tipo de fecha-hora) | ✔ |
| 11.2 | `integration_health.mail` | INT-01…04 | Se elimina `mail` | ✔ |
| 12 | Idempotencia adicional | RN-38, RN-42 | `channel_remote_items.external_key` único; línea externa única; pago de canal único | ✔ |
| 13 | Asociación de cliente por correo | RN-47, CHN-14 | `customers.email` sigue sin ser único | ✔ |
| 14 | Prueba de columnas generadas | ARQ-13 | Regla 13 de la sección 2 y script de 17.3 | ✔ (definida; ejecución pendiente en CI) |
| 15 | Onboarding P0 vs paquete de recuperación | CORE-02, BKP-08 | `installation_info.recovery_package_exported_at` | ✔ |
| 16 | Límites Woo confirmados (cantidad decimal, autoridad de precio y stock de variaciones) | RN-78…80, WOO-18…20 | Sin nuevas tablas: `sales_channels.capabilities` + `channel_mappings`; incompatibilidades se resuelven en adaptador/preflight sin alterar cantidades internas | ✔ |

### 17.2 Las nueve verificaciones estructurales

| # | Verificación | Resultado | Evidencia y notas |
|---|---|:-:|---|
| 1 | Cada requerimiento que necesita persistencia tiene soporte en el modelo | ✔ | Cada grupo funcional tiene tablas en la sección 1.1. Los requerimientos sin tabla propia son de comportamiento, interfaz o infraestructura (POS-01, POS-08, CAT-09, `SEC-*`, `ARQ-*`, `UPD-01…07`, `FIS-14`) |
| 2 | Cada tabla y campo relevante tiene un requerimiento que lo justifica | ✔ | Las 80 tablas tienen al menos un requerimiento en la sección 1.1. Los campos de fase posterior están justificados por requerimientos P2 o P3: `sales.kind` (SAL-17), `price_list_items.min_qty` (PRC-07), `license_states.signed_payload` y `signature` (LIC-05), `purchase_orders*` (PUR-03), `branch_variant_settings.location_label` (INV-14), `promotions.coupon_code` (PRC-04) |
| 3 | No existen FK hacia tablas creadas después | ✔ | Orden de la sección 1.2 verificado lote por lote, incluido `sale_review_issues` (lote 09, después de `sale_returns`). Sin FK por orden: `system_configs.updated_by_user_id`. Las referencias polimórficas están listadas en la sección 16 |
| 4 | No existen dos fuentes de verdad para roles, stock, precio, pago, fiscal, canal o contenido | ✔ | Roles: `roles`, `branch_user`, `users.global_role_id`. Stock físico: `stock_movements`; reservado: `stock_reservations`. Precio: `price_lists` + `price`. **Pago online: el canal**, importado a `sale_payments`. **Reembolso monetario online: el canal**, importado a `sale_returns`. Fiscal: `fiscal_documents`. Canal: `sales_channels` y `channel_mappings`. **Contenido: el POS, salvo un único canal designado** |
| 5 | No existen enums o campos que contradigan la abstracción multicanal | ✔ | `online` es un origen genérico y el canal concreto se identifica con `channel_id`. `price_lists` admite `all`, `pos`, `online` o un canal específico. La publicación es por canal. Las promociones son solo POS. `payment_methods.webpay` es una pasarela chilena, no un canal. `sales_channels.config` contiene claves propias del adaptador |
| 6 | Las restricciones UNIQUE son compatibles con el ámbito real de la numeración | ✔ | `document_sequences` sin columnas nulas en su único. Cada número visible es único **por sucursal**; `purchase_orders` es único por instalación. Los únicos que admiten `NULL` lo hacen a propósito (`sale_number` antes del cobro, `folio` en comprobantes internos, `external_line_id` y `external_refund_id` en ventas POS). `daily_stock_valuation_summaries` usa `category_id=0` |
| 7 | La retención es compatible con la inmutabilidad declarada | ✔ | **AO** (permanente) y **AO-P** (`audit_logs`, solo `retention:prune`). Cada categoría de RN-65 apunta a tablas que no son AO |
| 8 | El modelo sigue siendo práctico en MariaDB y cPanel | ✔ | Sin particiones, *triggers* ni procedimientos almacenados; bloqueos con `SELECT … FOR UPDATE`; JSON como `LONGTEXT`. Los únicos sobre columnas generadas se validan con la prueba ARQ-13 (17.3), con regla de decisión si fallan |
| 9 | El MVP no incorpora funciones de compliance general | ✔ | No existen tablas ni requerimientos de registro de tratamientos, encargados, transferencias, plazos, incidentes, EIPD ni bases jurídicas |

### 17.3 Prueba ARQ-13: script de referencia (no ejecutado en este entorno)

Debe ejecutarse en **MariaDB 10.6** y en la versión LTS recomendada. Se repite el patrón para cada una de las 8 columnas de la regla 13 de la sección 2. Criterio de salida: cada `INSERT` marcado "debe fallar" falla con el error 1062 (clave duplicada) y los demás se aceptan. Si una expresión falla, esa columna se declara `PERSISTENT`.

```sql
-- Patrón 1: documento activo único (fiscal_documents.active_key)
CREATE TABLE t_fiscal (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NULL, sale_return_id BIGINT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL, purpose VARCHAR(20) NOT NULL,
  revision SMALLINT UNSIGNED NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL,
  active_key VARCHAR(100) AS (IF(status='rejected', NULL,
    CONCAT(IFNULL(sale_id,0),':',IFNULL(sale_return_id,0),':',kind,':',purpose,':',revision))) VIRTUAL,
  UNIQUE KEY uq_active (active_key)
) ENGINE=InnoDB;
INSERT INTO t_fiscal (sale_id,kind,purpose,status) VALUES (1,'boleta','sale','issued');   -- OK
INSERT INTO t_fiscal (sale_id,kind,purpose,status) VALUES (1,'boleta','sale','pending');  -- debe fallar (1062)
INSERT INTO t_fiscal (sale_id,kind,purpose,status) VALUES (1,'boleta','sale','rejected'); -- OK (libera la clave)
INSERT INTO t_fiscal (sale_id,kind,purpose,revision,status) VALUES (1,'boleta','sale',2,'pending'); -- OK

-- Patrón 2: dedupe solo mientras está pendiente (channel_sync_events.pending_dedupe_key)
CREATE TABLE t_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, channel_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(12) NOT NULL, dedupe_key VARCHAR(120) NULL,
  pending_dedupe_key VARCHAR(120) AS (IF(status='pending', dedupe_key, NULL)) VIRTUAL,
  UNIQUE KEY uq_pending (channel_id, pending_dedupe_key)
) ENGINE=InnoDB;
INSERT INTO t_events (channel_id,status,dedupe_key) VALUES (1,'pending','stock:10');     -- OK
INSERT INTO t_events (channel_id,status,dedupe_key) VALUES (1,'pending','stock:10');     -- debe fallar (1062)
INSERT INTO t_events (channel_id,status,dedupe_key) VALUES (1,'processing','stock:10');  -- OK
UPDATE t_events SET status='done' WHERE id=1;
INSERT INTO t_events (channel_id,status,dedupe_key) VALUES (1,'pending','stock:10');     -- OK (el anterior ya no está pendiente)

-- Patrón 3: un pago principal de canal por venta (sale_payments.channel_payment_key)
CREATE TABLE t_pay (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sale_id BIGINT UNSIGNED NOT NULL, source VARCHAR(10) NOT NULL,
  channel_payment_key BIGINT UNSIGNED AS (IF(source='channel', sale_id, NULL)) VIRTUAL,
  UNIQUE KEY uq_channel_pay (channel_payment_key)
) ENGINE=InnoDB;
INSERT INTO t_pay (sale_id,source) VALUES (5,'channel');  -- OK
INSERT INTO t_pay (sale_id,source) VALUES (5,'channel');  -- debe fallar (1062)
INSERT INTO t_pay (sale_id,source) VALUES (5,'pos');      -- OK
```

### 17.4 Contradicciones residuales

**No se detectó ninguna contradicción estructural entre la Parte 1 y la Parte 2 tras el cierre del 04-10-2026.** La auditoría incluye el flujo de refunds: `created_by_user_id` nullable para canal, importes efectivos por línea, `restock` triestado y unicidad por línea.

Puntos que dependen de una validación externa al documento:
1. **Ejecución de la prueba ARQ-13** en MariaDB 10.6 y en la LTS recomendada (exigida antes del primer despliegue).
2. **Hold Stock de WooCommerce:** el comportamiento de RN-30 corresponde a su configuración estándar; verificar en la versión instalada de cada cliente.
3. **Momento de emisión del DTE por método de pago:** validar con un contador.
4. **Tamaño real de los XML fiscales:** medir con XML de muestra del proveedor.
5. **Alcance de la anonimización frente a documentos tributarios** y obligaciones de privacidad: asesoría jurídica del cliente.
6. **Primer proveedor DTE:** debe permitir consultar un documento por referencia externa para resolver el estado `unknown`.
7. **Extensiones Woo de cantidad decimal:** si un cliente las necesita, deben pasar una prueba de contrato específica antes de declarar `fractional_stock_supported=true`; Woo core P1 permanece en modo entero.

---

## 17A. Matriz de impacto v2.2 para migraciones

| Elemento | Cambio | Dependencia/validación |
|---|---|---|
| `license_states` | `products_enabled JSON`, `max_monthly_sales INT` | No reportar ventas en heartbeat; migración con backfill para planes previos |
| `plan_definitions` | `products_enabled JSON`, `max_monthly_sales INT`, seed `free` | Backfill y contratos de Control |
| `sales` | `tax_documentation_mode`, nuevos estados fiscales, `held_by_pos_station_id` | Prueba de transiciones, stock y caja; diferenciar tickets `two_step` |
| `sale_external_tax_records` | Nueva tabla después de `sales`, con FK a `sales` y `users` | No incluye ni sustituye XML fiscal; referencias voluntarias; DTE externos múltiples ABIERTOS |
| Consulta de mensualidad | Índice existente `sales.completed_at` debe evaluarse en EXPLAIN; procedimiento transaccional de guard queda pendiente | Nunca inventar qué cuenta, expiración o bloqueo sin ADR |
| Control central | Esquema separado, sin FK con `sales` operacionales | Ver `StockFlash-Control-SPEC-v0.1.md` |



## 18. Contratos externos que condicionan el modelo (congelados al 04-10-2026)

1. **WooCommerce se consume por REST `wc/v3`.** `sales_channels` guarda credenciales/capacidades/configuración del adaptador; ninguna tabla depende del esquema SQL de WordPress o HPOS.
2. **Autorización inicial Woo:** el flujo preferido es `wc-auth`; el callback de claves es HTTPS y su intento/nonce es efímero, por lo que **no requiere tabla permanente** adicional. Solo las credenciales resultantes se persisten cifradas.
3. **Webhooks:** `webhook_receipts` conserva la recepción idempotente; los IDs/tópicos de webhooks administrados se guardan en `sales_channels.config`, evitando una tabla adicional en P1.
4. **Polling:** `orders_cursor_at`/`products_cursor_at` son high-water marks; el algoritmo exacto (solape, `modified_after`, `modified_before`, paginación, avance al final) vive en el Anexo y RN-41.
5. **Refunds:** Woo expone refunds como recursos propios y el total puede no coincidir con la suma de productos. Por eso `refund_amount` vive en el encabezado y los importes de producto en `sale_return_items`; no se infiere uno del otro.
6. **Productos variables:** `channel_mappings.external_id` identifica el producto padre y `external_sub_id` la variación. El adaptador decide si publica stock/precio al endpoint de producto simple o a la variación concreta.
7. **HPOS:** no cambia el modelo interno porque el adaptador nunca lee/escribe tablas WordPress directamente.
8. **DTE:** el proveedor fiscal queda detrás de `FiscalDocumentIssuer`; no se agregan tablas propias del proveedor salvo que una implementación futura demuestre que un dato persistente es necesario y no cabe en el contrato existente.
9. **Cantidad decimal vs Woo core:** `product_variants`, inventario, ventas y reservas conservan `DECIMAL(12,3)`, pero el adaptador Woo P1 considera `stock_quantity` entero salvo capacidad externa explícitamente probada. No se cambia el modelo interno ni se redondea; la incompatibilidad vive en `sales_channels.capabilities`/estado de integración.
10. **Precio Woo:** `channel_mappings.published_price` representa el precio efectivo confirmado por el canal. Cuando el POS es autoridad de precio, el adaptador publica `regular_price` y evita que `sale_price` remoto mantenga una segunda autoridad. No se agrega un campo `sale_price` interno por esta razón.
11. **Variaciones:** `external_id` + `external_sub_id` permiten distinguir producto padre y variación. La compatibilidad del modo de stock remoto (`manage_stock` en variación vs `parent`) se valida en preflight; no requiere otra tabla.

**Regla:** una limitación externa descubierta durante implementación se resuelve primero en el adaptador/configuración/capabilities. Solo modifica este modelo si aparece una nueva verdad de negocio que deba persistirse de forma independiente.

