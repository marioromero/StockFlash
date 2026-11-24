# POSSTATION-001 — Independent QA review (2026-11-24T22:09 local)

Packet: `docs/work/features/POSSTATION-001.md` — APPROVED, `AUTO_GIT`, `ONE_SMALL_WAVE`,
`new_business_decisions: NONE`; active in `docs/work/CURRENT.md`.
Base/HEAD: `283a4707598af9aac214fd7a8c73a638fb97fe11` (git status checked read-only; base == HEAD).
Gate evidence: `docs/work/POSSTATION-001-VERIFY-20261123-220309.md` — **GATES_PASS 15/15 (PS1..PS15)**;
full PHP suite 988 passed / 10922 assertions; `npm run build` PASS (`vue-tsc --noEmit && vite build`);
`check_contracts.py` PASS; no migration/schema change (PS4/PS15).
Verdict: **APROBADO** (independent QA only; never VERIFIED).

## Fingerprint confirmation

`git status --porcelain=v1 --untracked-files=all` contains exactly the reviewed paths (no other changes,
no `database/migrations`, no `public/docs/index.html`, no `resources/roadmap/index.html`, no protected
harness/product files). All supplied SHA-1s matched exactly, including:

| Path | SHA-1 |
|---|---|
| app/Http/Controllers/DashboardController.php | 4ad6bc329d46e162f8c306df417838475addad5a |
| app/Support/SensitiveKeyFilter.php | 7c0686ea7308b06d772a9789a6df93c84a6d6c61 |
| bootstrap/app.php | 079390015dbc196bc7149bc3b1a2df7fcd86c6e3 |
| routes/web.php | a80d0ce465998ac21f2bdb10dfe38f8329d22b4a |
| scripts/sf_verify.py | fcd20e893cf9656773fd49b6810fb9554dbfa511 |
| resources/js/Pages/Dashboard.vue | c3047f34618de0508c9d178ca90da7d46c6c9d98 |
| app/Http/Controllers/PosStations/PosStationController.php | 064363675d86a6723bfb366a1f57a25a2c612455 |
| app/Support/PosStations/PosStationService.php | e1c98e295fac8c6deae699fe15b4d7d4fc3f6111 |
| app/Support/PosStations/PosStationToken.php | fa338d6b420170b1dc48204c700de90fe5017e1c |
| app/Support/PosStations/PosStationResolver.php | 0b0a84a7a743aa1f38fef3289ca2bf11da842a55 |
| tests/Feature/PosStations/PosStationTokenTest.php | c9ffc943b5977fa1ec335e62a61fcffe2ace0718 |
| docs/work/POSSTATION-001-VERIFY-20261123-220309.md | a86eec47045f832bfd0e5493e257a02dc3038a46 |

The remaining 19 supplied hashes (middleware, request, model, access/code/gate/write-guard services,
Vue pages, other POS tests, notes, CURRENT/GUIA/ROADMAP metadata) also matched
one-for-one. All reviewed source mtimes precede the gate report (latest source 21:59:25;
report 22:03:09), so the evidence applies to this exact working tree. No source changed during QA.

## Gate evidence handling

- Accepted PS1..PS15 from the existing report because the fingerprint is identical; full PHP suite,
  build and contracts were intentionally **not re-run** (no gate replay).
- Independent targeted probe only: `php artisan test tests/Feature/PosStations` →
  **46 passed / 565 assertions**, SQLite `:memory:` asserted in `PosStationTestCase::beforeRefreshingDatabase`
  (`tests/Feature/PosStations/PosStationTestCase.php:31-35`). This reproduces the packet's stated count.
- PS9 command is the mandatory complete `php artisan test`; PS10 build includes `vue-tsc --noEmit`;
  HQAEFF-002 plan validator runs before gates (`scripts/sf_verify.py:1900-1901`). No redundant focused
  PHP or standalone typecheck gate in the final list. Historical packet gates untouched (pure insertion
  before `SETUP-001`).

## Targeted adversarial checks and dispositions

1. **Schema reuse — PASS.** `database/migrations/2026_10_16_000013_create_pos_stations_table.php:12-24`
   unchanged (git; PS4/PS15); columns/types/unique/FK/soft-delete exactly match Part 2 §4.13
   (`docs/product/...parte-2...:464-475`). `PosStation` only maps existing columns
   (`app/Models/PosStation.php:19-53`); no new tables/columns/indexes/permissions/dependencies, no hard
   delete path (no DELETE route; `tests/.../PosStationCrudTest.php:304-314`; `PosStationSchemaTest.php:86-119`).
   Existing rows are untouched (no migration/seeder/DELETE code). No schema-wide regression signal.
2. **Access — PASS, server-side.** Global role `settings.manage` acts across branches; branch users require
   a `branch_user` role with `settings.manage` on an active, non-deleted branch
   (`PosStationAccessService.php:23-61`), and `canAccess`/`scopeVisibleStations` enforce list/detail/read
   scope (`:95-125`). Numeric-only routing plus re-resolution prevents forged IDs/IDOR
   (`PosStationController.php:189-202`, `routes/web.php:401-422`). Create/update branch targets are revalidated
   server-side (`PosStationService.php:319-340`); unauthorized branch filter is rejected (`Controller:204-242`).
   Module `pos` is fail-closed on the explicit `installation_modules.pos` row with no fallback
   (`PosStationModuleGate.php:17-24`; middleware `EnsurePosStationModuleEnabled.php:19-26`); manager guard is
   global/branch aware (`EnsurePosStationManager.php:27-40`). License read-only permits GETs and blocks all
   mutations with 422 (`PosStationWriteGuard.php:19-43`, `PosStationAccessTest.php:161-213`). Dashboard link
   is server-gated by module + `canManageAnyBranch` (`DashboardController.php:54-56`, `Dashboard.vue:50,148`).
   Routes inherit `web` session/CSRF (`PosStationAccessTest.php:353-360`); guests redirect to login
   (`:14-24`). Inactive-branch-only manager is denied (`:80-94`).
3. **CRUD/code — PASS.** Code is trim + uppercase, `^[A-Z0-9._-]{1,30}$`
   (`PosStationCode.php:16-24`), immutable after creation (request excludes `code` on update: `PosStationRequest.php:79-86`;
   service ignores it), unique case-insensitively including soft-deleted rows (`PosStationService.php:342-349`)
   with DB unique as backstop (`PosStationCrudTest.php:92-110`). Name/printer enum/branch validation and
   active-branch rule are enforced (`Service:351-394`, `Request:52-89`). Forged `id/is_active/device_token_hash/
   last_seen_at/deleted_at` are dropped (`CrudTest:166-206`). No hard delete.
4. **Token security — PASS.** Only explicit POST binds/rebinds/revokes; GET never issues or writes
   (`routes/web.php:406-421`, `AccessTest:274-293`). `random_bytes(32)` → 64 lowercase hex; only
   `hash('sha256', raw)` persisted (`PosStationToken.php:18-31`; DB hash length/hash equality asserted
   `PosStationTokenTest.php:20-71`). Cookie is `HttpOnly`, `SameSite=strict`, path `/`, `Secure` when HTTPS
   (`PosStationController.php:155-175`; tests `:73-88`). Laravel `EncryptCookies` additionally encrypts the
   Set-Cookie value; `TestResponse` decrypts it for assertions, and the resolver reads the decrypted value,
   so the raw token is never the stored cookie plaintext. No token/hash in Inertia/HTML/JSON/DB row/audit/
   redirect/URL; list/detail expose only `has_device` (`Controller:260-298`; `PosStation.php:23,50-53`;
   `TokenTest:47-68`, `AccessTest:295-317`). Rebind replaces the hash atomically; revoke clears hash and expires
   the cookie; deactivation and branch change clear the hash and audit `token_invalidated`; a name/printer-only
   update keeps the token (`Service:134-166,171-211,221-303`; tests `:90-252`). Resolver is read-only: valid
   64-hex cookie → SHA-256 → active station in active non-deleted branch, optional expected branch; garbage,
   absent, wrong, inactive, soft-deleted or branch-mismatch tokens return null; it never authenticates,
   authorizes, rotates or writes `last_seen_at` (`PosStationResolver.php:25-59`; tests `:254-309`).
5. **Audit — PASS.** Create/update/status/bind/revoke audit inside the same transaction as the row/token
   change; payloads contain only station ID, branch ID, active flag, changed field names and the non-secret
   booleans `token_rotated|token_revoked|token_invalidated` (`Service:65-78,151-165,194-207,255-267,287-299`;
   `PosStationAuditTest.php:17-102`). Audit failure rolls back create/update/status/token changes
   (`AuditTest:104-152`, `TokenTest:311-343`). `SensitiveKeyFilter` change is minimal and non-weakening:
   only exact boolean flags are preserved; `contains()` is unchanged and string values are still dropped
   (`SensitiveKeyFilter.php:41-52,82-101`).
6. **No sales/cash — PASS.** New PHP/Vue contains no sales, cart, tickets, cash, reservations, stock,
   fiscal or commission behavior; `last_seen_at` is only read, never written; no heartbeat or GET issuance
   (greps; `Service:62`, `Resolver:9-15`). `/docs/`, `/roadmap` and `/setup` remain available
   (`PosStationAccessTest.php:343-351`). No real browser/HTTPS/MariaDB execution: `browser_ux=NOT_RUN`,
   `maria=NOT_RUN`, `remote_db=NOT_TOUCHED`.
7. **Tests — PASS.** 46 substantive tests across schema/CRUD/access/token/audit with HTTP + Inertia + DB
   assertions; checks are not tautological (module fail-closed, inactive-branch denial, IDOR on every
   mutation, license read-only, forged fields, case/soft-deleted code uniqueness, 256-bit token/hash/cookie
   attributes, no leakage, invalidation matrix, resolver negatives, audit rollback, public-route regression).
   Targeted rerun 46/565 confirms genuine green at this fingerprint.
8. **Metadata — PASS with documentation lag (non-blocking).** Notes/ledgers state
   `IMPLEMENTED_TECH`, pending QA/CI, no VERIFIED, no deploy/remote DB, MariaDB/browser NOT_RUN
   (`POSSTATION-001-NOTES-20261124.md:3,8-13,65-92`). SELLERS-001/CUSCORE-001 remain VERIFIED_TECH;
   `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT` correctly registered; GPT-authored HTML untouched.
   CURRENT/GUIA/ROADMAP/notes still say gates are pending even though GATES_PASS now exists — update at
   closure; no false VERIFIED claim.

## Non-blocking observations (OBS)

- **OBS-1 (low, robustness):** `PosStationService::create` wraps the transaction in `catch (QueryException)`
  and maps any such failure to "duplicate code" 422 (`PosStationService.php:82-86`). Rollback is preserved,
  but a non-uniqueness DB/audit `QueryException` would be misreported. No security/data impact.
- **OBS-2 (low, audit precision):** `revoke` always audits `token_revoked: true` even when no token existed
  (idempotent revoke) (`PosStationService.php:287-299`). Nonsecret flag only; harmless.
- **OBS-3 (low, performance residual):** resolver lookup on `device_token_hash`
  (`PosStationResolver.php:48-59`) has no index. Part 2 §4.13 does not require one; table is small and
  future POS-flow performance is unmeasured (MariaDB NOT_RUN).
- **OBS-4 (deferred, MariaDB):** case-insensitive uniqueness is guaranteed by the application precheck;
  the DB unique index is collation-dependent, so the concurrent case-variant race is not proven. MariaDB
  concurrency is explicitly deferred and NOT_RUN.
- **OBS-5 (deferred, commercial):** plan `max_pos_stations` is not enforced. The packet does not require it
  and says stations are not independently billable; classify as a future licensing wave, not this packet.
- **OBS-6 (low, docs):** CURRENT/GUIA/ROADMAP/notes gate status is stale after GATES_PASS; owner/orchestrator
  should update at closure.
- **OBS-7 (brand/accessibility, PASS):** new screens reuse the existing backoffice text brand and hardcoded
  palette used by Dashboard/Sellers (`#0f172a/#2563eb`) rather than the global `--brand-*` tokens in
  `resources/css/app.css`; this is a pre-existing app-wide pattern, not new drift. Official PNGs are absent
  (only provisional SVGs, which the contract forbids using), so the accessible text fallback is correct.
  Labels, table caption/scope, status roles, disabled states and global `:focus-visible` are present;
  no browser render/screenshot was produced.

## Residual / deferred (explicit)

- MariaDB real collation/concurrency, real browser UX/HTTPS proxy behaviour, real devices: NOT_RUN.
- Exact-SHA GitHub Actions CI, publication, deploy and any VERIFIED decision remain for publisher/ChatGPT.
- No remote DB, `.env`, secrets, migrations or production actions were accessed.

## QA independence statement

Read-only review; no files edited, staged, committed or pushed. One targeted PHPUnit probe was run
(`tests/Feature/PosStations`, 46/565). Full suite, build, contract script, browser and MariaDB were not
replayed by QA because the deterministic gate report exists at the identical fingerprint. This review
does not claim VERIFIED.
