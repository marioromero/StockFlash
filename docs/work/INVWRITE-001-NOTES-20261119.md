# INVWRITE-001 — Implementation notes (2026-11-19)

Packet: `docs/work/features/INVWRITE-001.md` (APPROVED, `existing_approved_contract_owner_delegation_2026-11-16`, `new_business_decisions: NONE`).

## Status keys

- packet: INVWRITE-001
- base_sha: `f890b7d15844dce32cb0b8bc47a2fe14aba37d82`
- maria=NOT_RUN
- remote_db=UNTOUCHED
- validation_scope: SQLite `:memory:` only; PHP feature tests + `npm run typecheck`
- GUIA_PENDIENTE_CHATGPT
- ROADMAP_PENDIENTE_CHATGPT
- CSV: deferred, NOT implemented and NOT VERIFIED (INV-05 CSV remains pending in a separate feature)
- schema: no migration added or edited; `reason_codes`, `authorization_overrides`, users PIN fields, `stock_movements` and `current_stock` already existed. `stock_movements` still has NO `authorization_override_id`.

## Tables and invariants used

- `current_stock`: only mutated through `StockLedger`; `reserved` untouched by initial load and adjustment.
- `stock_movements`: append-only physical kardex. Initial uses `type=initial`; adjustment uses `type=adjustment`; both use `unit_cost=null`. Balance and `last_movement_at` are computed inside the ledger transaction.
- `reason_codes`: adjustment requires an active row with `scope=stock_adjustment`; `requires_note=1` requires a non-empty note (max 255).
- `authorization_overrides`: only for above-threshold adjustments; `type=stock_adjustment`, `reason_code_id=null` (the movement carries the adjustment reason), `reference_type=inventory_adjustment`, `reference_id=variant_id`.
- `system_configs`: `stock_adjust_pin_value_threshold`, `cost_method`, `override_min_authority`, `pin_length`, `pin_max_attempts`.
- `system_configs.stock_adjust_pin_value_threshold`: missing, empty or zero (including `0.0000000`) disables the PIN threshold.

## Routes and permissions

Both write routes are in the authenticated group and use `['can.permission:inventory.adjust', 'branch.access']`:

- `POST /inventory/stock/{branch}/initial` → `inventory.stock.initial` → `StockAdjustmentController@storeInitial`
- `POST /inventory/stock/{branch}/adjustment` → `inventory.stock.adjust` → `StockAdjustmentController@storeAdjustment`

The existing read route `GET /inventory/stock/{branch}` (`inventory.stock.index`) and `GET /inventory` (`inventory.entry`) are unchanged in authorization. The branch is resolved server-side from `{branch}` (id or code); `branch_id`, `user_id`, `type`, `source_type`, `source_id`, `on_hand`, `cost`, `reserved` and `available` from the client are never accepted as authority. The controller also fail-closes with 403 when `InventoryModuleGate` is disabled.

## Initial load decisions

- Variant must belong to an active, non-deleted product with `tracks_stock=1` and product `type` in `simple|variable`; the variant must be active and non-deleted.
- Quantity must be strictly `> 0` with DECIMAL(12,3) format. Zero is rejected as invalid/no-op and is never recorded as a fake movement; negatives are rejected.
- Rejected if ANY `stock_movements` row already exists for that `(branch_id, variant_id)`; reinitialization by overwrite is not supported. The authoritative check runs **inside** `StockLedger::recordMovement()` via the `underLock` hook: after the license guard, `current_stock` `insertOrIgnore` + `lockForUpdate`, and the duplicate-origin check, but before computing/writing the balance. The pre-transaction gate was removed.
- Uses `source_type=inventory_initial`, `source_id=variant_id`. The ledger duplicate-origin guard rejects a repeated initial for the same branch/variant/type.
- An initial after any adjustment, reversal or other movement is therefore rejected under the row lock, not only by a precheck. SQLite sequential tests cover initial-after-adjustment, initial-after-reversal and repeated initial; real MariaDB concurrency remains NOT_RUN.
- Saving catalog products/variants does not create stock rows or movements (covered by test).

## Adjustment decisions

- Quantity uses signed DECIMAL(12,3); zero rejected; negative corrections allowed. POS RN-22 negative-stock blocking is NOT applied to manual corrections, even if `allow_negative_stock=0`.
- Reason must be active and `scope=stock_adjustment`; note is required only when the reason requires it and is otherwise optional.
- Uses `source_type=inventory_adjustment`; `user_id` comes from the authenticated session.

## Idempotency token decision (no schema change)

- The GET page supplies a per-render `adjustment_token` (`Str::random(48)`, matching `^[A-Za-z0-9_-]{16,64}$`).
- The POST requires `request_token` with the same format.
- `source_id = (int) hexdec(substr(hash('sha256', token), 0, 15))`, i.e. at most 60 bits and within signed 64-bit range.
- The existing ledger duplicate guard key `(source_type, source_id, branch_id, variant_id, type)` rejects a retried submission without a new schema index. A fresh token allows a new legitimate adjustment. No raw token or hash is exposed in props, errors, audit or logs.

## PIN threshold, cost basis and AO semantics

- Value at current cost = `abs(quantity) * unit cost basis`, computed with exact integer/string arithmetic (`InventoryDecimal::multiplyQuantityByCost` / `compareProductToThreshold`); no floats, no bcmath.
- PIN is required only when the threshold is enabled AND the value is strictly greater than the threshold. Equality does NOT require PIN.
- Cost basis: `product_variants.cost_price` (current average net cost), unless `system_configs.cost_method='last'` AND `product_variants.last_cost` is not null, in which case `last_cost` is used. This matches contract RN-24 (`cost_method` admits `average` or `last`); when `last_cost` is null the average cost remains the only available basis.
- Above threshold the request must include a valid authorizer id and PIN. **Self-authorization is allowed** per COREPIN-001: a requester with `override.authorize`, authority `>= override_min_authority` and a valid PIN may authorize their own adjustment. There is no `authorizerId === userId` prohibition; `PinAuthorizationService` policy is the sole authority. `authorizerOptions()` includes the current user when eligible.
- `PinAuthorizationService::authorize(type='stock_adjustment', branchId, authorizerId, pin, referenceType='inventory_adjustment', referenceId=variant_id, oldValue=current on_hand text, newValue=estimated resulting on_hand text, reasonCodeId=null, reason=note, onApproved=<callback>)` revalidates override permission, authority level and branch role.
- **Atomic approval + physical write (external-review correction):** `authorize()` accepts an optional final `?callable $onApproved = null`. The callback runs inside the PIN service transaction after PIN verification/reset and **before** the `authorization_overrides` insert; it may return an array overriding `old_value`, `new_value`, `reason_code_id` and/or `reason`. If it throws, the whole authorization transaction rolls back (no AO, no approval audit, no nested movement/audit). Failed PIN attempts/lockouts/denial audits still persist because denials return before the callback.
- `adjust()` passes an `onApproved` closure that, under the authorization transaction: (1) re-reads variant/product with `lockForUpdate` where supported (SQLite no-op) using the same eligibility criteria; (2) re-reads `cost_method`/`stock_adjust_pin_value_threshold` with `lockForUpdate` in deterministic `config_key` order, recomputes the value at cost and fails closed with `StockWriteException('adjustment', 'El valor ya no requiere PIN; vuelva a intentar sin autorización.')` if it no longer exceeds the threshold; (3) calls `StockLedger::recordMovement()` (nested savepoint; locks license→stock) with the same token-derived `source_id`, rethrowing `DuplicateStockMovementException` as `StockWriteException('request_token', ...)`; (4) derives the authoritative old `on_hand` as `balance_after - signed quantity` and returns AO `old_value`/`new_value`. The movement is captured and returned after authorization succeeds.
- `InventoryWriteGuard::isReadOnly()` is still pre-checked before PIN authorization (early, no orphan approval). The ledger revalidates the license authoritatively inside its own transaction.
- The PIN service is **not** wrapped in an outer transaction. A wrong PIN persists the attempt/audit and returns 422 with no physical movement and no AO.
- Successful approval and movement are now all-or-nothing: a later ledger/approval-audit failure leaves AO=0, `stock_movements`=0 and `current_stock` unchanged. The previous "orphan AO on later ledger failure" residual no longer applies.
- Duplicate-token retries are pre-rejected: after resolving `source_id` from `request_token`, `adjust()` checks for an existing `stock_movements` row with `source_type=inventory_adjustment`, `source_id`, `branch_id`, `variant_id` and `type=adjustment` and throws a `StockWriteException('request_token', ...)` BEFORE `balancePreview`/PIN authorization. A retried submission therefore never creates a second AO; the ledger duplicate guard/catch remains as defense-in-depth.
- PIN, PIN hash, cost basis and threshold value are never returned in props, validation errors, audit `new_values` or logs.

## Authorizer and reason props

- Only sent when the actor has `inventory.adjust` for the resolved branch. Reasons expose only `id`, `label`, `requires_note` for active `stock_adjustment` rows, ordered.
- Authorizers are computed server-side: active, non-deleted, applicable role (global role or `branch_user` role for that branch) exposing `override.authorize` and `authority_level >= override_min_authority` (default 50). The current user is **included when eligible** (COREPIN-001). Only `id` and `name` are returned, bounded and ordered. No email, PIN or lock state.
- Stock rows now include `initialized` (`EXISTS` any movement for branch+variant); `on_hand`, `reserved`, `available`, `last_movement_at` and pagination remain unchanged.

## UI

- Same `Inventory/Stock.vue` read screen with per-row `Cargar inicial` (only when `!initialized`) and `Ajustar`, opening an inline accessible form. Shows on_hand/reserved/available before submit. Adjustment shows reason select, conditional note hint, optional authorizer select (may include the current user when eligible), and a PIN `type=password` input with `autocomplete="off"`; hidden token. Help text states `override.authorize` + authority + PIN and no longer requires a "different" user.
- Read-only license, disabled module (403) and no-adjust permission states are shown; server enforces every write. Success uses shared flash; validation messages are es-CL 403/422. No fiscal/DTE/sales wording.

## External-review corrections (2026-11-19)

Addressed the three functional corrections in `docs/work/INVWRITE-001-EXTERNAL-REVIEW-20261119.md`:

1. HIGH — approval + physical write atomicity: `PinAuthorizationService::authorize()` gained the `onApproved` hook; `adjust()` performs the ledger write inside the authorization transaction and derives authoritative AO `old_value`/`new_value`. Failure after approval leaves AO=0, movement=0, stock unchanged.
2. HIGH — INITIAL no-history invariant: authoritative `EXISTS stock_movements` check moved into `StockLedger::recordMovement()` `underLock`, after license + stock row lock and duplicate-origin check, before balance. The pre-transaction gate was removed.
3. CONTRACT — self-authorization prohibition removed: no `authorizerId === userId` rejection, current user not excluded from `authorizerOptions()`, UI text now states `override.authorize` + authority + PIN. COREPIN-001 policy remains the authority.

Regression tests added: approval-audit failure after AO insert (AO=0/movement=0/stock unchanged), threshold re-evaluated inside the authorization transaction (fails closed), self-authorization success, initial after reversal rejected. Wrong-PIN test now also asserts the persisted denial audit and no movement.

## Targeted verification (this corrected candidate, not full suite)

- `php artisan test tests/Feature/Inventory`: 94 passed, 805 assertions.
- `php artisan test tests/Feature/Core/PinAuthorizationServiceTest.php`: 31 passed, 120 assertions.
- `npm run typecheck`: PASS (only UI help text changed in `Stock.vue`).
- Full PHP suite and `scripts/sf_verify.py` were intentionally NOT run by this implementer; independent QA/gates/CI are external.

## QA residual disposition

- R1 (duplicate-token retry created a second AO before the ledger duplicate guard): FIXED — pre-rejected before PIN/AO; regression test retained.
- R2 (UI eligibility flags): accepted, non-blocking/deferred; no behavior change in this correction.
- R3 (pre-PIN denial audit): accepted, non-blocking/deferred; no behavior change.
- R5 (initial history check outside the ledger lock): FIXED — now enforced via `StockLedger::recordMovement($data, $underLock)` under the authoritative stock lock; sequential SQLite tests cover initial after adjustment, initial after reversal and repeated initial.
- R6 (audit reason/note): accepted, non-blocking/deferred; no behavior change.
- R7 (authorizer with PIN not configured in listing): accepted, non-blocking/deferred; listing does not expose PIN configuration. The server still rejects `pin_not_configured`.
- R9 (branch-writer success test): accepted, non-blocking/deferred; no behavior change.
- R10 (report hashes): accepted, non-blocking/deferred; no behavior change.
- R11 (aria/focus): accepted, non-blocking/deferred; no behavior change.

## Remaining residuals (truthful, non-blocking)

- MariaDB real concurrency/locking for the `onApproved` and `underLock` hooks: NOT_RUN; SQLite sequential tests only (`lockForUpdate` is a no-op in SQLite).
- Real browser UX: NOT_RUN.
- Combo/INV-13 behavior: out of scope for this packet.
- Audit `reason`/`note` and aria/focus refinements: deferred as above.
- Authorizer with PIN not configured still appears in the listing (no PIN state exposed).
- No commit/push from this role; no QA/VERIFIED claim.

## Deferred / not claimed

- Remote DB, installers, migrations/seeds against real databases, deploy: untouched.
- CSV import for INV-05: explicitly deferred; this packet is not a CSV feature.
- No commit/push from this role; no independent QA/VERIFIED claim.
