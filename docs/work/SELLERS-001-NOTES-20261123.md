# SELLERS-001 — Implementation notes (2026-11-23)

status: IMPLEMENTED_TECH_PENDING_GATES_QA_CI
packet: `docs/work/features/SELLERS-001.md` (APPROVED)
base_sha: `5d983bb` (synced HEAD)
feature: SELLERS-001 — Seller register and branch assignments (POS prerequisite)
requirements: Part 1 §8.9 SEL-01; §7.2 RN-11 / RN-13 / RN-14; Part 2 §6.5 and §0.1
verification: NOT_VERIFIED
maria: NOT_RUN
remote_db: NOT_TOUCHED
browser_ux: NOT_RUN
ci: NOT_RUN
deploy: NOT_RUN

## Delivered scope

- One additive migration `2026_11_23_000026_create_seller_core_tables.php` with only the two approved tables:
  - `sellers`: `id`, `code VARCHAR(20) NOT NULL UNIQUE`, `name VARCHAR(150) NOT NULL`, nullable `user_id`
    FK `users` `SET NULL`, `commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0`,
    `is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1`, nullable `anonymized_at`, Laravel timestamps
    and `deleted_at` (SD). No extra business columns.
  - `branch_seller`: composite PK `(seller_id, branch_id)`, both `BIGINT UNSIGNED` FKs `CASCADE`;
    no `id` and no timestamps.
- Models `Seller` and `BranchSeller`; idempotent `SellerSeeder` invoked from `DatabaseSeeder` creates
  the reserved `NONE` / “Sin asignar” row without branches. The seeder is not called from GET routes.
- Seller domain/access:
  - `SellerModuleGate`: explicit `installation_modules.sellers` row wins; otherwise `pos` enabled.
  - `EnsureSellerManager`: routes are global, so authorization succeeds when the global role or any
    `branch_user` role has `sellers.manage`. `sellers.view_reports` alone never grants access.
  - `SellerAccessService`: global managers have full scope (`null`); branch managers receive only active
    branches where their role has `sellers.manage`. Reads, filters and writes are scoped in server code.
  - `SellerService`: single transaction for seller + pivot + audit, with `SellerWriteGuard` license
    assertion. Create requires at least one allowed active branch. Update preserves existing branch
    assignments outside the actor's scope, only lets the actor add/remove allowed branches, and rejects a
    final empty set. Submitted unauthorized branch IDs are rejected.
  - Optional linked user must exist, be active, not soft-deleted, and (if branch-scoped) have branch
    access to every final assigned branch. Global users are accepted.
  - Code is trimmed and uppercased, matches `^[A-Z0-9._-]{1,20}$`, cannot be `NONE`, is immutable after
    creation, and is unique including soft-deleted and case variants (precheck plus DB unique).
  - Commission is an exact canonical decimal string 0..100 with two decimals; no float arithmetic and no
    commission calculation/report.
  - No hard delete. Deactivate/activate only. Anonymized sellers reject edits and status changes.
  - Audit records only seller ID, active status, branch IDs and changed field names. Name, code, linked-user
    PII, emails and commission values are not copied. Audit failure rolls back seller and pivot.
- Routes under the central authenticated group:
  - `GET /sellers`, `GET /sellers/{seller}`, `POST /sellers`, `PATCH /sellers/{seller}`,
    `POST /sellers/{seller}/activate`, `POST /sellers/{seller}/deactivate`.
  - Numeric-only IDs; module disabled 403; out-of-scope detail 404; license read-only writes 422.
- Dashboard “Vendedores” link only when the sellers module is enabled and the actor can manage at least one
  branch.
- es-CL accessible `Sellers/Index.vue` and `Sellers/Show.vue`: bounded search/pagination, permitted branch
  filter, create/edit, optional user select (id/name only), commission, branch checkboxes within scope,
  status and assignments visible, read-only license notice, no sales/report/commission-earned UI.
- Existing `InventorySchemaTest` migration-count regression updated additively. No other existing test
  behavior changed.

## Targeted verification executed

All PHP commands used isolated SQLite only:
`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`.

- `php artisan test tests/Feature/Sellers`: 57 passed / 636 assertions.
- `php artisan test tests/Feature/Customers tests/Feature/Inventory`: 279 passed / 4033 assertions.
- `php artisan test tests/Feature/Core tests/Feature/Docs tests/Feature/Roadmap`: 122 passed / 738 assertions.
- `npm run typecheck`: PASS.
- `php -l` on every new PHP file: PASS.
- `vendor/bin/pint --test` on new PHP files/tests: PASS.

The full PHP suite, deterministic `scripts/sf_verify.py SELLERS-001`, independent QA, exact-SHA CI and
publication were NOT run by this implementation role. No QA/VERIFIED claim is made.

## Deferred / out of scope

- SEL-02 sales attribution RN-11/RN-12, SEL-03 monthly seller report, SEL-04 commission report and SEL-05
  targets are not implemented.
- No sales, tickets, reservations, reports, commission-earned calculation, customer schema changes or
  stock/inventory mutations.
- MariaDB real collation/concurrency, browser UX, installer execution, remote DB access, deploy and cron
  remain pending or explicitly out of scope.
- `GUIA_PENDIENTE_CHATGPT` and `ROADMAP_PENDIENTE_CHATGPT` remain for the seller screen and coverage.
