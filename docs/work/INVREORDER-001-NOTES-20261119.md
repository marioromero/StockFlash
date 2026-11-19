# INVREORDER-001 — Work notes (2026-11-19)

packet: `INVREORDER-001`
requirement: `INV-07` (Part 1 §8.3; Part 2 §7.1/§7.2)
base_sha: `7665317c53b1af4a6f4cff1a30804b5d7e2d61c1`
status: `IMPLEMENTED_IN_CODE_TESTS`; `NOT_VERIFIED`
maria=NOT_RUN
remote_db=UNTOUCHED
GUIA_PENDIENTE_CHATGPT
ROADMAP_PENDIENTE_CHATGPT

## Delivered

- One new migration only: `database/migrations/2026_11_19_000023_create_branch_variant_settings_table.php`.
- Table `branch_variant_settings` with composite PK `(branch_id, variant_id)`, FKs to `branches` and `product_variants` with CASCADE delete, `reorder_point DECIMAL(12,3) NOT NULL DEFAULT 0`, `reorder_qty DECIMAL(12,3) NULL`, `max_stock DECIMAL(12,3) NULL`, `safety_stock DECIMAL(12,3) NOT NULL DEFAULT 0`, `location_label VARCHAR(50) NULL`. No `id`, no timestamps, no extra indexes, no triggers.
- `ReorderSettingsService`: transactional license guard, eligibility revalidation under lock, settings row lock, insert/update of only `reorder_point`, `reorder_qty`, `max_stock`, atomic audit through `AuditLogger` (`inventory.reorder_settings_updated`). Create sets `safety_stock=0` and `location_label=null`; updates never touch those columns.
- `ReorderQueryService`: branch-scoped eligible variants plus historic settings rows (`can_edit=false`), current stock from `current_stock`, exact decimal formatting, bounded search/filter/pagination, critical count.
- `ReorderController`, `ReorderFilterRequest`, `ReorderSettingsRequest`.
- UI `resources/js/Pages/Inventory/Reorder.vue`; "Reposición" link added to `Stock.vue`.

## Routes

- `GET /inventory/stock/{branch}/reorder` → `inventory.reorder.index` (`inventory.view` + `branch.access`).
- `PUT /inventory/stock/{branch}/reorder/{variant}` → `inventory.reorder.update` (`inventory.adjust` + `branch.access`).

Branch is resolved server-side from `{branch}` with the same semantics as `EnsureBranchAccess`: `is_numeric` resolves the ID first, otherwise the exact code. No request-supplied branch/user override. Module gate is fail-closed 403.

## Critical rule

`has_settings = true` AND `reorder_point > 0` AND `available <= reorder_point`.

Boundary equality is critical; negative available is critical; zero-point or unconfigured rows are never critical. `safety_stock` and `location_label` are neither read for this rule nor exposed.

## Side effects

No `current_stock` write, no `stock_movements` write, no reservation lifecycle, no cost/price/PII exposure. Configuration writes only `branch_variant_settings` and `audit_logs`.

## Verification performed

- `php artisan test tests/Feature/Inventory` → 150 passed, 1804 assertions (SQLite `:memory:`).
- `npm run typecheck` → pass.
- Orchestrator verification: `scripts/sf_verify.py INVREORDER-001` GATES_PASS 16/16 (report `docs/work/INVREORDER-001-VERIFY-20261119-171648.md`); inventory 150/1804; full suite 752/7415; contracts/typecheck/build PASS. Independent QA APROBADO at `docs/work/INVREORDER-001-REVIEW-20261119-1725.md`. MariaDB concurrency/index checks, real browser UX, CSV and remote DB operations were NOT run (NOT_RUN).

## Deferred / risks

- MariaDB lock ordering/index behavior: NOT_RUN.
- Browser visual/UX review: NOT_RUN.
- Gate registration: the implementer did not edit `scripts/sf_verify.py`; the orchestrator registered mandatory IR1-IR16 under the approved packet. Exact-SHA CI remains external.
- Historic settings rows for inactive/deleted variants remain visible read-only; no cleanup job was added.
