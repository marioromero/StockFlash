# POSSTATION-001 — Implementation notes (2026-11-24)

status: IMPLEMENTED_TECH_PENDING_GATES_QA_CI
packet: `docs/work/features/POSSTATION-001.md` (APPROVED)
base_sha: `283a470` (synced HEAD)
feature: POSSTATION-001 — POS stations and local device binding (POS prerequisite)
requirements: Part 1 §8.6 POS-02; §8.7 CSH-02; §7.6 RN-33 / RN-36; Part 2 §4.13
verification: NOT_VERIFIED
maria: NOT_RUN
remote_db: NOT_TOUCHED
browser_ux: NOT_RUN
ci: NOT_RUN
deploy: NOT_RUN

## Delivered scope

- **Existing schema reused exactly.** No new/alter migration, table, column, index, permission or
  dependency. `pos_stations` remains `id`, `branch_id` FK `branches` CASCADE, `code VARCHAR(30)`
  UNIQUE, `name VARCHAR(100)`, nullable `printer_profile` enum `thermal58/thermal80/a4`, nullable
  `device_token_hash VARCHAR(64)`, nullable `last_seen_at`, `is_active TINYINT DEFAULT 1` indexed,
  timestamps and `deleted_at`.
- Model `PosStation`: fillable safe fields only, `device_token_hash` hidden, SoftDeletes, casts and
  `branch()` relation. The bound-device flag is exposed only as a boolean.
- POS domain (`app/Support/PosStations/`):
  - `PosStationCode`: trim + uppercase, `^[A-Z0-9._-]{1,30}$`, immutable after create.
  - `PosStationModuleGate`: fail-closed on the explicit `installation_modules.pos` row; POS stations
    have no independent commercial entitlement and do not fall back to another module.
  - `EnsurePosStationManager` + `PosStationAccessService`: global role with `settings.manage` sees all
    branches; branch users need `settings.manage` in a `branch_user` role and are limited to those
    active, non-deleted branches. No new permission.
  - `PosStationWriteGuard`: reuses `LicenseReadOnlyRule`; suspended outside grace blocks writes with
    422 while reads remain available.
  - `PosStationService`: one transaction per create/update/status/bind/revoke including audit. Code
    uniqueness includes case variants and soft-deleted rows (precheck + DB unique). Branch change is
    limited to an existing active authorized branch and atomically invalidates the device token.
    Deactivation invalidates the token; activation never issues one. No hard delete.
  - `PosStationToken`: `random_bytes(32)` (>= 256 bits) hex raw token; only `hash('sha256', raw)` is
    stored. Raw value is returned only to the controller for the response cookie.
  - `PosStationResolver`: read-only cookie -> SHA-256 -> active non-deleted station in an active
    non-deleted branch. It never authenticates a user, never authorizes, never issues a token and
    never updates `last_seen_at`.
- **Cookie rules:** internal constant name; `HttpOnly`, `SameSite=Strict`, path `/`, `Secure` when the
  request is HTTPS (plain HTTP localhost remains usable). Raw token is not written to DB columns other
  than the SHA-256 hash, Inertia props, HTML, JSON, redirects, query strings, logs or audit rows.
  Revoke clears the hash and expires the cookie.
- Audit events `pos_stations.created|updated|activated|deactivated|token_bound|token_revoked` record
  only station ID, branch ID, active flag, changed field names and non-secret boolean flags
  (`token_rotated`, `token_revoked`, `token_invalidated`). `SensitiveKeyFilter` preserves those exact
  boolean audit flags while still filtering real token/secret keys; `contains()` is unchanged.
- Routes under the central authenticated `web` group:
  - `GET /settings/pos-stations`, `GET /settings/pos-stations/{station}`, `POST /settings/pos-stations`,
    `PATCH /settings/pos-stations/{station}`,
    `POST /settings/pos-stations/{station}/activate|deactivate|bind|revoke`.
  - Numeric-only IDs; module disabled 403; out-of-scope detail/mutation 404; license read-only writes
    422. Binding is an explicit POST; no GET or login issues a token.
- Dashboard “Estaciones POS” link only when the `pos` module is enabled and the actor can manage at
  least one allowed branch.
- es-CL accessible `PosStations/Index.vue` and `PosStations/Show.vue`: bounded search/filter/pagination,
  create (code/name/printer/branch), edit (name/printer/branch, code read-only), status actions and
  explicit “Vincular este navegador” / “Revocar dispositivo” with confirmation. Tokens and hashes are
  never displayed or copied; only the bound boolean is shown. No sales, cash, cart, ticket, stock,
  reservation or fiscal wording.
- Public `/docs/`, `/roadmap`, `/setup` and CUSCORE/SELLERS/inventory behavior preserved.

## Targeted verification executed

All PHP commands used isolated SQLite only:
`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`. Remote/MariaDB status for this
wave: `maria=NOT_RUN`, `remote_db=NOT_TOUCHED`.

- `php artisan test tests/Feature/PosStations`: 46 passed / 565 assertions.
- `php artisan test tests/Feature/PosStations tests/Feature/Sellers tests/Feature/Customers tests/Feature/Inventory`:
  382 passed / 5234 assertions.
- `php artisan test tests/Feature/Core tests/Feature/Docs tests/Feature/Roadmap tests/Feature/InertiaPagesPathTest.php`:
  128 passed / 765 assertions.
- `npm run typecheck`: PASS.
- `php -l` on every new PHP file: PASS.
- `vendor/bin/pint --test` on new PHP files (excluding pre-existing `routes/web.php`/`bootstrap/app.php`
  style drift): PASS.

The full PHP suite, deterministic `scripts/sf_verify.py POSSTATION-001` (HQAEFF-002 policy), independent
QA, exact-SHA CI and publication were NOT run by this implementation role. No QA/VERIFIED claim is made.

## Deferred / out of scope

- CSH-01/CSH-03+ cash sessions, POS cart, sales/tickets/payments, inventory reservations, commissions,
  reports, fiscal/DTE/Woo, heartbeat and browser/device integration beyond tests are not implemented.
- `last_seen_at` remains null until a future approved POS event defines it.
- MariaDB real collation/concurrency, browser UX, real devices, remote DB, installer execution,
  deploy and cron remain pending or explicitly out of scope.
- `GUIA_PENDIENTE_CHATGPT` and `ROADMAP_PENDIENTE_CHATGPT` remain for the POS stations screen and
  coverage. GPT-authored HTML files were not edited.
