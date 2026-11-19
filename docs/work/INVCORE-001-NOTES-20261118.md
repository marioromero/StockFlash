# INVCORE-001 — Inventory ledger and branch stock foundation — implementation notes

INVCORE-001
maria=NOT_RUN
remote_db=UNTOUCHED
GUIA_PENDIENTE_CHATGPT

- Date: 2026-11-18
- Packet: `docs/work/features/INVCORE-001.md` (APPROVED, ONE_SMALL_WAVE).
- Base SHA: `d6940dcdd0b6a88ff02171d6f465c67854bc1214`.
- Correction base SHA (SFH1 i=003): `78a5f0fc1f40aeb12547b5fb1529aa06a051d2a7` (INVCORE-001 first publication).
- Scope: INV-01/INV-02/INV-03 (Part 1 §8.3, RN-19/RN-21; Part 2 §7.2/§7.4). Internal ledger only; no user-exposed stock adjustments, sales, transfers, counts or reservation lifecycle.

## Tables created (one new migration)

`database/migrations/2026_11_18_000022_create_inventory_core_tables.php`

- `current_stock`: composite PK `(branch_id, variant_id)`, `on_hand`/`reserved` DECIMAL(12,3) default 0, generated `available` = `on_hand - reserved`, nullable `last_movement_at`, NOT NULL `updated_at` without automatic timestamps.
- `stock_movements`: append-only kardex with signed `quantity`/`balance_after` DECIMAL(12,3), nullable `unit_cost` DECIMAL(14,4), polymorphic `source_type`/`source_id`, nullable `reservation_id`, self-FK `reverses_movement_id`, `user_id` FK SET NULL, `reason_code_id` FK SET NULL, `note`, `occurred_at`; no `created_at`/`updated_at`.
- Indexes as approved: `(branch_id, variant_id, id)`, `(variant_id, occurred_at)`, `(source_type, source_id)`, `(type, occurred_at)`.
- NOT created in this wave: `stock_reservations`, `branch_variant_settings` (reservation lifecycle explicitly out of scope).

### Deferred FK note

`stock_movements.reservation_id` exists as the approved nullable BIGINT column but has **no FK** because `stock_reservations` does not exist in this wave. The FK is deferred to the reservations/sales packet. No fake table or relation was created.

### SQLite generated column note

Laravel's SQLite grammar created `available` as a real virtual generated column via `virtualAs('on_hand - reserved')`. Tests prove it is computed by the engine, updates with `on_hand`, and rejects direct insertion. No PHP-only simulation exists.

## Services and models

- `app/Support/Inventory/InventoryDecimal.php`: pure integer thousandths for quantity DECIMAL(12,3) and ten-thousandths for cost DECIMAL(14,4); strict regex/range validation; no floats/bcmath arithmetic. SQLite may return REAL on read, so `*FromStorage()` only formats that transport value to the contractual scale before strict parsing.
- `app/Support/Inventory/StockLedger.php`: single transactional internal API (`recordMovement`, `reverseMovement`). It locks the license row, inserts/ignores `current_stock`, locks the stock row, computes the new balance only from that row, updates the cache, appends the movement, writes non-PII audit via `AuditLogger->record(..., 'inventory')`, and commits; any failure rolls back all.
- Duplicate origin guard: `(source_type, source_id, branch_id, variant_id, type)` checked inside the same transaction under the stock row lock, via `DuplicateStockMovementException`. No unapproved DB unique index was added.
- `app/Models/StockMovement.php`: application-level immutability; `update`, `delete`, `forceDelete` and `save()` on an existing row throw `LogicException`. This is documented as application-level because Part 2 §17.2 forbids DB triggers.
- `app/Support/Inventory/InventoryWriteGuard.php`: reuses `LicenseReadOnlyRule` semantics; read-only license blocks ledger mutations, never reads.
- `app/Support/Inventory/InventoryModuleGate.php`: technical module gate.
- `app/Support/Inventory/StockQueryService.php`: branch-scoped, bounded pagination (1..100), `q` filter on SKU/product name; returns schema-computed `available` with COALESCE 0 only for variants without a stock row.

## Routes and permissions

- `GET /inventory` → `inventory.entry`, middleware `can.permission:inventory.view`; resolves default/first active accessible branch server-side and redirects, or 403.
- `GET /inventory/stock/{branch}` → `inventory.stock.index`, middleware `can.permission:inventory.view` + `branch.access`; branch resolved by id/code server-side; 403 if missing/inactive/module disabled.
- No write, adjustment, sale, transfer, count or reservation routes.
- Permission used: `inventory.view` (already seeded). No new permission was invented.
- Module enforcement: the approved `installation_modules` seed writes commercial product codes (`core`, `pos`, `bodega`, `syncwp`, `fiscal`, `compras`, `analitica`, `control`) and does not create a commercial `inventory` row, while `App\Support\Modules\ModuleCatalog` does define the technical module code `inventory` (which owns the `inventory.*` permissions). The gate stays fail-closed: an explicit `inventory` row wins when present; otherwise inventory is available when `pos` or `bodega` is enabled; no known rows -> disabled.
- Dashboard: `can_view_inventory` prop plus an “Inventario” link only for users who can exercise `inventory.view` in an accessible branch.

## Reconciliation command

`app/Console/Commands/ReconcileInventoryCommand.php` — `inventory:reconcile` (auto-discovered, verified in `php artisan list`).

- Strictly read-only, deterministic text or `--json`, supports `--branch`, `--chunk` (1..5000) and `--limit`.
- Detects `current_stock.on_hand` vs latest movement `balance_after` selected by append order only (`id DESC`), because `balance_after` is chained in append order while `occurred_at` is business time and may be backdated. Also detects `on_hand != 0` without movements, duplicate reversals, and `reserved` vs active `stock_reservations` only when that table exists.
- When `stock_reservations` is absent it reports `reserved_check=deferred`; it never emits a false PASS.
- Exit 0 clean, non-zero on mismatches. No INSERT/UPDATE/DELETE, no silent rewrite, no secrets/PII. Tests snapshot both tables before/after and prove repeatability.

## UI

- `resources/js/Pages/Inventory/Stock.vue`: es-CL read-only screen with branch label, search, accessible table (existencia/reservado/disponible/último movimiento), bounded pagination and responsive catalog-style layout.
- `resources/js/Pages/Dashboard.vue`: optional “Inventario” link.

## Tests and checks (targeted only)

- `php artisan test tests/Feature/Inventory` → **49 passed / 374 assertions** (SFH1 i=003 correction run).
- `php artisan test tests/Feature/Catalog` (regression) → **116 passed / 1810 assertions**.
- `npm run typecheck` → pass (pre-correction run; no frontend changed by this correction).
- `php artisan list` → `inventory:reconcile` registered.
- Full `php artisan test` and `scripts/sf_verify.py INVCORE-001` intentionally NOT run here; they belong to the orchestrator.
- Regression coverage for the ordering fix: `test_usa_orden_de_anexado_cuando_occurred_at_esta_retrodatado` posts two movements where the higher `id` has the earlier `occurred_at`, asserts `current_stock.on_hand` equals that movement's `balance_after`, and asserts `inventory:reconcile` returns `CLEAN` without writing.

## Review NIT disposition (SFH1 i=003)

- Reconcile ordering: **FIXED** — latest movement is selected by append order only (`orderByDesc('id')`), so backdated `occurred_at` cannot produce a false `balance_mismatch`. Output schema, exit codes and read-only behavior are unchanged.
- Module gate wording: **CLARIFIED** — no behavior change, still fail-closed. Documentation now states that the approved `installation_modules` seed writes commercial codes (`core`, `pos`, `bodega`, `syncwp`, `fiscal`, `compras`, `analitica`, `control`), that `ModuleCatalog` defines the technical `inventory` code, and that an explicit `inventory` row wins when present, otherwise `pos` or `bodega`.
- Other previously reported LOW items remain accepted and out of this correction: dashboard link scope, branch-code RBAC for non-branch users, inactive variant listing.

## Concurrency statement (SQLite only)

Tests run exclusively on SQLite `:memory:`. `lockForUpdate` is a no-op there; the test transaction serializes sequential writes. The sequential first-write/upsert and balance assertions prove application behaviour only. Actual MariaDB row-lock concurrency validation is explicitly deferred (`maria=NOT_RUN`). No remote database, migration, seeder or `.env` was touched.

## Test-only regression adjustment

`tests/Feature/Catalog/CatalogSchemaTest.php` asserted an exact set of schema tables. The two new approved inventory tables were added to that expected set (new `INVENTORY_TABLES` constant). No catalog behaviour, migration or UI was changed.

## Deferred / out of scope

- `stock_reservations` lifecycle, `branch_variant_settings`, stock transfers, counts, sales posting, POS/fiscal math, negative-stock policy surface, MariaDB concurrency, browser QA, deployment and remote DB operations.
- Reservations create no physical movement in this wave; no reservation APIs exist.
