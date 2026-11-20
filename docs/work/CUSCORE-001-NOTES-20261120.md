# CUSCORE-001 — Notas de implementación (2026-11-20)

packet: CUSCORE-001 (APPROVED, active in `docs/work/CURRENT.md`)
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
base_sha: `900106c2e93079221af0837e116d20aa1df4fe0b`
contract: Parte 1 §8.8 CUS-01, CUS-02, CUS-03; §7.9 RN-58, RN-61, RN-64; S3; Parte 2 §0.1 y §6.1/§6.2
status: IMPLEMENTED_PENDING_ORCHESTRATOR_GATES_QA_CI
verification: NOT VERIFIED (OpenCode does not self-certify)
maria=NOT_RUN
remote_db=UNKNOWN
remote_db: UNKNOWN / NOT_UNTOUCHED (truthful historical exception; no remote DB or network call made in this packet)
browser_ui: NOT_RUN
installation: NOT_RUN
deploy: NOT_RUN
guide: GUIA_PENDIENTE_CHATGPT
roadmap: ROADMAP_PENDIENTE_CHATGPT
harness_efficiency: HQAEFF — diff-first targeted reads, isolated SQLite tests, no repeated whole-repo scans.

## Scope delivered

- One new migration `2026_11_20_000025_create_customer_core_tables.php` with exactly
  `customer_groups` and `customers` per Part 2 §6.1/§6.2 and SD conventions.
- No `customer_addresses`, `customer_consents`, `sales`, `sale_items`, reservations,
  purchase history, marketing/consent UI, POS pricing, tax or fiscal issuance.
- No new permission, module code, plan or commercial decision. Existing seeded
  `customers.view`, `customers.manage`, `customers.view_sensitive` are used.
- `customers.privacy_tools` remains out of scope for this P0 wave.

## Schema (exact)

`customer_groups`: id; code VARCHAR(30) NOT NULL UNIQUE; name VARCHAR(100) NOT NULL;
default_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0; max_discount_percent
DECIMAL(5,2) NULL; is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1; created_at,
updated_at, deleted_at.

`customers`: id; type ENUM('person','company') DEFAULT 'person'; name VARCHAR(150);
trade_name VARCHAR(150) NULL; fiscal_id_encrypted VARCHAR(255) NULL;
fiscal_id_hash CHAR(64) NULL UNIQUE; email VARCHAR(150) NULL INDEX; phone VARCHAR(30)
NULL; business_activity/fiscal_address/fiscal_commune/fiscal_city NULL;
customer_group_id BIGINT UNSIGNED NULL FK `customer_groups` SET NULL;
default_discount_percent/max_discount_percent DECIMAL(5,2) NULL;
marketing_opt_in TINYINT(1) UNSIGNED DEFAULT 0; origin ENUM('pos','online','import')
DEFAULT 'pos'; last_activity_at NULL; is_active TINYINT(1) UNSIGNED DEFAULT 1;
blocked_at, anonymized_at NULL; created_at, updated_at, deleted_at.

Endpoints never hard-delete: activation/deactivation toggles `is_active`; `deleted_at`
exists only for the SD convention.

## RUT crypto and blind index (RN-58)

- `config/privacy.php` reads `BLIND_INDEX_KEY` with no `APP_KEY` fallback.
- `App\Support\Customers\CustomerRut` trims, removes dots/spaces/hyphens, uppercases
  K, strips leading zeros, validates modulo 11 (including K) and returns canonical
  `body-DV`.
- Canonical RUT is encrypted with Laravel Crypt into `fiscal_id_encrypted`.
- `fiscal_id_hash = HMAC-SHA256(canonical_rut, BLIND_INDEX_KEY)`. Simple hashes are
  not used.
- Missing/empty/<32-character key fails closed only when a RUT is supplied, searched or
  otherwise normalized for identity; ordinary customer creation without RUT still works.
- Duplicate normalized RUT is rejected by precheck plus DB unique catch with a generic
  message that never includes the RUT, hash or ciphertext.
- RUT search is exact by blind index; partial/invalid RUT returns a safe 422.
- Tests use only deterministic test keys through `config()`; no real secret is read or
  printed.

## Masking, reveal and auditing (RN-61 / PRV-02/03/06)

- Default list/detail send only masks and presence flags. They do not send plaintext RUT,
  ciphertext, blind index, email, phone, business activity, fiscal address/commune/city.
- `GET /customers/{customer}/reveal` requires `customers.view_sensitive`, returns JSON
  with `Cache-Control` containing `no-store`, and records `customers.revealed` via
  `AuditLogger::recordRead` (`is_personal_data_access=1`, no values).
- `customers.manage` does not imply reveal; unauthorized reveal is 403 without payload.
- The edit form never pre-fills existing sensitive values. Blank means preserve; a new
  value replaces. Non-sensitive fields and explicit NULL discounts are editable.

## Discounts (CUS-02/03 · S3)

- Exact decimal strings only: `DECIMAL(5,2)` bounds 0..100. No floating-point
  arithmetic. Canonical output uses two decimals (`5` → `5.00`, `7.5` → `7.50`).
- Group default is NOT NULL with default 0; group max is nullable.
- Customer own default/max are nullable. NULL means inherit from the group; explicit
  NULL is preserved on update.
- Display computes effective default/max as `own ?? group`, with no new relative-cap
  rule beyond bounds and inheritance.
- No POS pricing, promotions, tax or sale application logic in this packet.

## Module, license and authorization

- `App\Support\Customers\CustomerModuleGate`: explicit `installation_modules.customers`
  row wins; otherwise `pos` enabled; otherwise disabled. The
  `customers.module` middleware covers every customer/group route with 403.
- `customers.view` for list/detail/groups reads; `customers.manage` for
  create/edit/activate/deactivate/group CRUD; `customers.view_sensitive` for reveal.
- `CustomerWriteGuard` mirrors the verified inventory/catalog license rule: suspended
  outside grace blocks writes with 422 and no partial writes. Reads remain available.
- Routes are central/global (no `branch.access`). Numeric IDs only; unknown/non-numeric
  IDs return 404/403 safely. `/docs/`, `/roadmap` and `/setup` are preserved.

## Audit

- Atomic in the same transaction as the mutation. Failure rolls back the whole write.
- Events: `customers.created`, `customers.updated`, `customers.activated`,
  `customers.deactivated`, `customers.groups.created`, `customers.groups.updated`,
  `customers.groups.activated`, `customers.groups.deactivated`, plus
  `customers.revealed` read audit.
- Values are limited to IDs, group ID, discounts, status/blocked/anonymized booleans and
  changed field names. Name, email, phone, RUT, ciphertext and blind index are never
  written to audit.

## UI

- `resources/js/Pages/Customers/Index.vue` (list/filters/create),
  `Customers/Show.vue` (masked detail/edit/status/reveal via native `fetch`) and
  `Customers/Groups.vue` (list/create/edit/status).
- es-CL text, accessible labels/roles, responsive tables, no raw HTML, no axios.
- Dashboard shows “Clientes” only when the server says the user is eligible and the
  module is enabled.

## Tests executed (isolated SQLite only)

Command (explicit isolation):
`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test tests/Feature/Customers tests/Feature/Inventory`

Result: 273 passed / 3872 assertions.

Also executed:
- `php -l` on every new/changed PHP file: no syntax errors.
- `npm run typecheck`: PASS.

Not executed in this role (deferred to orchestrator/full gate):
- full `php artisan test` suite;
- `py -3 scripts/sf_verify.py CUSCORE-001` (orchestrator-owned full gate);
- real MariaDB, concurrency, browser/visual UX, installer, deploy, remote DB;
- independent QA, exact-SHA CI, AUTO_GIT publication.

## QA R1 correction (P1/P3) — 2026-11-20

Independent QA requested changes; only these two findings were corrected.

### P1 — RUT no longer travels in GET/URL/HTML/JSON

- `CustomerController::index` no longer reads `rut` from the query. A forged or
  manual `GET /customers?...&rut=...` redirects before rendering to the same
  filters without `rut`; the value is never reflected in the body.
- New `POST /customers/rut-search` (`customers.rut-search`) behind the same
  `customers.module` + `customers.view` guards. It validates a required string
  `rut` of max 30, normalizes modulo 11 and computes the blind index.
  Missing/short `BLIND_INDEX_KEY` fails closed with a session `rut` error and
  no filter is stored. Successful searches store only the 64-hex
  `fiscal_id_hash` in `customers.rut_hash` and redirect to `/customers`.
- New `POST /customers/rut-search/clear` (`customers.clear-rut`) forgets the
  session filter and redirects to `/customers`.
- `index` reads only the session hash, accepts exactly 64 hex characters,
  applies the exact `fiscal_id_hash` filter and sends only `rut_filter=true`.
  Neither the RUT nor the hash is sent in Inertia props.
- Invalid submissions use a manual redirect with session errors, avoiding
  Laravel's raw old-input flash of the cleartext RUT.
- `Customers/Index.vue` now has a separate “Buscar por RUT” POST form, never
  adds `rut` to the GET filter params, clears the cleartext field after a
  successful submit, shows “Filtro RUT activo” and offers “Quitar filtro RUT”.
  The previous misleading copy was replaced.

### P3 — Dashboard customer link matches route scope

- `DashboardController` now uses `CheckPermission::allows($user, 'customers.view')`
  plus the module gate, matching the global (no `{branch}`) customer routes and
  the default-branch semantics of `CheckPermission`.
- Unused `canAnywhere` helper was removed.
- Regression test covers a branch user with permission only on the default
  branch (link visible, route 200) and permission only on another branch
  (link false, route 403).

### New/updated regression tests

- New `tests/Feature/Customers/CustomerRutSearchPrivacyTest.php`: full
  `getContent()` of the POST search and redirected page never contains the
  cleartext RUT or its punctuation/case variants; URL/Location has no `rut`;
  props contain neither RUT nor hash; session stores only the hash; exact
  filtering survives pagination; missing/short key fails closed; forged GET
  redirects cleanly; clear removes the filter.
- Updated `CustomerListTest`, `CustomerRutTest` and `CustomerAuthorizationTest`
  to use the POST endpoint/guards instead of `GET /customers?rut=...`.
- Added dashboard default-branch/other-branch scope regression.

### Correction verification (this role, isolated SQLite)

`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test tests/Feature/Customers`
→ 62 passed / 1033 assertions.

`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test tests/Feature/Core/DashboardLicenseNoticeTest.php`
→ 9 passed / 117 assertions.

Combined rerun on the final tree
(`... php artisan test tests/Feature/Customers tests/Feature/Core/DashboardLicenseNoticeTest.php`)
→ 71 passed / 1151 assertions.

`npm run typecheck` → PASS.

Full suite, orchestrator gates (`sf_verify.py`), real MariaDB, browser/visual UX,
CI, independent QA and publication remain NOT_RUN in this role.

## Risks / deferred checks

- MariaDB-specific enum/decimal/FK behavior and concurrent lock ordering are NOT_RUN;
  SQLite validates structure and semantics only.
- Blind index key provisioning must exist in real installations before RUT operations;
  absent/short keys fail closed by design.
- Reveal is JSON; browser UX of the reveal panel is NOT_RUN.
- No export, anonymization or consent management flows are included (P1/PRV scope).
- No sales/reservation/stock writes are created by this packet.

No QA approval, `VERIFIED` claim, deployment or remote database change is made here.
