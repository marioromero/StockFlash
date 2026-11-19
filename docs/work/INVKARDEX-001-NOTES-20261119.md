# INVKARDEX-001 — Implementation notes (2026-11-19)

packet: INVKARDEX-001
status: IMPLEMENTED_LOCAL_NOT_VERIFIED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
base_sha: `671e215f57999d00964d18cd6d2d77f5db37bbcc`
requirement: INV-08 (Part 1 §8.3), RN-19/RN-21
data_contract: Part 2 §7.4 `stock_movements`
route: `inventory.kardex.index` — GET `/inventory/stock/{branch}/kardex`
permission: `inventory.view` + `branch.access` + inventory module gate (fail-closed 403)
pagination: `cursor` (keyset by `stock_movements.id` DESC, filters preserved via `withQueryString()`)
schema: no schema changes, no migrations
maria=NOT_RUN
remote_db=UNTOUCHED
GUIA_PENDIENTE_CHATGPT
qa: PENDING_INDEPENDENT_REVIEW
verified: NOT VERIFIED

## Delivered

- `app/Support/Inventory/KardexQueryService.php`: read-only branch-scoped query over
  `stock_movements` joined to `product_variants` and `products`; no soft-delete or
  active filters; selects only id, occurred_at, type, quantity, balance_after,
  reverses_movement_id, variant_id, SKU and product name. No costs, prices, margins,
  users, authorizers, PIN, notes or other PII.
- `app/Http/Requests/Inventory/KardexFilterRequest.php`: GET allowlist `q`,
  `variant_id`, `from`, `to`, `per_page`, `cursor`; 422 on invalid dates, date order,
  page bounds, variant id or oversized search; unknown query keys are not used.
- `app/Http/Controllers/Inventory/KardexController.php@index`: fail-closed module
  gate, server-side branch resolution by id or code, active-branch check, read-only
  license notice and Inertia `Inventory/Kardex` props.
- `routes/web.php`: one GET route inside the existing authenticated group with
  `can.permission:inventory.view` and `branch.access`. No mutation routes.
- `resources/js/Pages/Inventory/Kardex.vue`: es-CL filters, accessible responsive
  table, explicit append-order/balance hint, reversal badge with corrected movement
  id, previous/next cursor links, empty state and read-only notice.
- `resources/js/Pages/Inventory/Stock.vue`: "Ver Kardex" link in the branch title
  area; existing stock behavior unchanged.
- `tests/Feature/Inventory/KardexReportTest.php` plus route-list update in
  `StockReadAuthorizationTest.php`.

## Decisions

- Balance is displayed exactly as stored (`balance_after`); rows are ordered by
  append id DESC, never by `occurred_at`, so backdated movements do not alter the
  recorded balance chain.
- Date-only filters use the application timezone (`config('app.timezone')`, UTC):
  from start-of-day, to end-of-day 23:59:59, inclusive.
- Invalid cursors are decoded and validated before querying and raise a 422
  validation error instead of a 500.
- Reversals are visible and the original movement is retained; no records are
  hidden, edited or deleted.

## Correction K-01 (independent QA blocker, 2026-11-19)

- Scope: `KardexController::resolveRouteBranch` used `ctype_digit` while
  `EnsureBranchAccess`/`CheckPermission` use `is_numeric`. A legal code such as
  `1E0` is numeric-but-not-ctype-digit: the middleware authorized branch ID 1
  (numeric coercion) while the controller rendered the branch whose code was
  `1E0`, leaking another branch's Kardex.
- Fix: `resolveRouteBranch` now uses the SAME resolver semantics as
  `EnsureBranchAccess`: any `is_numeric($identifier)` is treated as an ID via
  `Branch::query()->whereKey((int) $identifier)->first()`, otherwise it is
  resolved by exact code. Existing active-branch check and fail-closed 403 are
  unchanged. Numeric-like legal codes can no longer switch the authorized scope;
  authorization and data scope use one deterministic resolution.
- Regression test: `test_codigo_numerico_legal_resuelve_el_mismo_alcance_que_el_middleware`
  creates branch A first (ID 1), branch B with legal code `1E0`, a branch-scoped
  user with only `inventory.view` on A, and movements in both branches. GET
  `/inventory/stock/1E0/kardex` must render branch ID 1 (A) only; B's movement id
  and SKU must be absent. Existing branch-isolation tests remain green.
- ID-based forged filters (`branch_id`, `variant_id`, `cost`) remain ignored:
  the route `{branch}`, `branch.access` and `can.permission` are the only scope
  inputs; query/body identifiers cannot elevate or change scope.
- N-01..N-06 were accepted as non-blocking exactly as reported by QA; no
  behavior change was required.

## Targeted verification (local SQLite :memory:)

- `php artisan test tests/Feature/Inventory`: 120 passed, 1276 assertions.
- `php artisan test tests/Feature/Inventory/KardexReportTest.php`: 12 passed,
  385 assertions.
- `npm run typecheck`: passed.
- Full PHP suite, `scripts/sf_verify.py`, independent QA, CI and publication are
  orchestrator-owned and were NOT run here.

## Deferred

- CSV/export (INV-05), cost valuation and margins (INV-09 / `catalog.view_cost`).
- MariaDB real performance, indexes, concurrency and browser/visual UX.
- No schema, migrations, installers, remote DB, `.env`, deploy or secrets touched.
