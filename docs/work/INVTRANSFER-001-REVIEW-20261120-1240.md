# INVTRANSFER-001 — Independent QA review (2026-11-20 12:40 CLT)

## Verdict
**APROBADO** (independent read-only QA; **not VERIFIED**).

- Packet: `docs/work/features/INVTRANSFER-001.md` — `status: APPROVED`, `AUTO_GIT`, `ONE_SMALL_WAVE`, `new_business_decisions: NONE`.
- Base/HEAD/origin-main: `03c9bb8fb94f9c3f2e6c8114aede428c8ee03def` (HEAD = base = origin/main; no commit/push by QA).
- Candidate fingerprint: `8fa0147270bed524bd1565f518dcf023c4dbf93250dfc5b8976b56ab2aab36b6`, file_count 720 — **matches** `docs/work/.run/INVTRANSFER-001.gate-evidence.json` exactly.
- Gate report: `docs/work/INVTRANSFER-001-VERIFY-20261120-123338.md` — GATES_PASS 18/18; report SHA-256 matches the proof.
- No blocker found. Findings N1–N6 are non-blocking.

## Fingerprint (exactly the 24 supplied paths)
```text
88dc6d02c5360ebeb5ca346fac410f39be384619  docs/work/GUIA-HTML-ESTADO.md
b5f94b0b05c42b239a7f732ea6c482c03ade7b9b  docs/work/ROADMAP-HTML-ESTADO.md
2a6553fcdc56f562267ca328111a5cc941478670  resources/js/Pages/Inventory/Stock.vue
e90ef468944db80e9027ae03e5c3b940768ee8c0  routes/web.php
3c6253e9eea961ece29e530c19a67e93ab866b97  scripts/sf_verify.py
732e5f48ecdc9e8e6b2431151822c08972e1d8e4  tests/Feature/Catalog/CatalogSchemaTest.php
6dcec94b9187c3c67765b443c960dda30a5270b9  tests/Feature/Inventory/InventorySchemaTest.php
64bbf8a57d6fc7bf7251d249326ce0e270faf899  tests/Feature/Inventory/StockReadAuthorizationTest.php
26185f01cf0adafa4c267a2d4e871b93c0309c82  app/Http/Controllers/Inventory/StockTransferController.php
9b505de1e24f02ce53cf0c5a010696150256dce3  app/Http/Requests/Inventory/StockTransferReceiveRequest.php
949b5005d05777e8b82f480439b089c04bdaf05a  app/Http/Requests/Inventory/StockTransferStoreRequest.php
31c8f7ed0323595e21f3dd4ff02ed7bcaaf9093f  app/Support/Inventory/StockTransferException.php
b693a468ce52bf87f7d3d4dbf23a9b68bb36f767  app/Support/Inventory/StockTransferQueryService.php
78d023b302c5d39547b277869d23477290329695  app/Support/Inventory/StockTransferService.php
e2d836d5dcd94f5ea429b835d4fe1272db44975f  database/migrations/2026_11_20_000024_create_stock_transfer_tables.php
61778574a1ee33b4ca0cd8df851273e135ccc2c5  docs/work/INVTRANSFER-001-NOTES-20261120.md
c5e9cbd4ec20d4d61c19a493a2770d1fbfca3487  docs/work/INVTRANSFER-001-VERIFY-20261120-123338.md
2852bd958ec441e131fb7aceab6320204185c8c6  resources/js/Pages/Inventory/TransferShow.vue
ab9aee3a2298e7e491058ab402d5d71074a58335  resources/js/Pages/Inventory/Transfers.vue
b3c7ff11d8e99ff5e715a144ccb3d00986d0fc66  tests/Feature/Inventory/StockTransferAccessTest.php
48b15b33c1dead9f6e37662281fea9572acaadcf  tests/Feature/Inventory/StockTransferLifecycleTest.php
1e46cd4e0eae2216c518a228db437d358b050bda  tests/Feature/Inventory/StockTransferQueryTest.php
01fc24a52b91034e6464bbfc14bb0ad942b022f6  tests/Feature/Inventory/StockTransferSchemaTest.php
4c246870a6dbfbfbadb8de9057576aeb821a2cc8  tests/Feature/Inventory/StockTransferTestCase.php
```
`git status --porcelain=v1 --untracked-files=all` shows exactly these 24 entries. No stray edits. `docs/work/.run/*` is gitignored evidence, not candidate source.

## Gate evidence
- **18/18 mandatory gates PASS** (IT1–IT17 + IT7b): packet/active, implementation files, routes/middleware/link, protected/no-schema, schema, lifecycle/ledger/numbering/audit, contracts, atomicity tests, inventory suite **213/2812**, full suite **815/8423**, typecheck, build, GUIA/ROADMAP metadata, notes/MariaDB, read scope/no sensitive fields, HQAEFF+SQLite isolation, no secrets, diff-check.
- **HQAEFF full-run proof:** proof `created_at` 2026-11-20 12:33:39 aligns with report 12:33:38; report name and SHA-256 match; no `--reuse-evidence` artifact.
- The earlier `122824` run is a `GATES_FAIL` superseded artifact (Catalog exact-table/notes/gates), corrected before the final run; it is not a current failure.
- QA did **not** rerun the full suite, inventory suite, contracts, typecheck or build; it relied on the exact-tree proof plus targeted probes.

## Independent targeted checks performed
- **Targeted PHP probe:** `php artisan test tests/Feature/Inventory/StockTransferSchemaTest.php tests/Feature/Inventory/StockTransferLifecycleTest.php tests/Feature/Inventory/StockTransferAccessTest.php tests/Feature/Inventory/StockTransferQueryTest.php` → **36 passed, 616 assertions** (7.33s), SQLite `:memory:` per `phpunit.xml`. Tree fingerprint unchanged after the run.
- Static data-flow over migration, service, query service, controller, requests, `DocumentSequenceAllocator`, `StockLedger`, `InventoryWriteGuard`, `AuditLogger`, routes, UI and tests.
- Schema exactness checked against Part 2 §7.5 and the packet, including columns/types/defaults/nullability/FKs/indexes and absence of timestamps.
- Access/scope checked against route middleware, controller resolver, query scope, permission/module/license guards, and forged-field handling.
- UI/brand/security review of the two new pages and the Stock link; no edits made.
- No `.env`, keys, dumps, remote DB, migrations, seeders, deploy, commit or push accessed/performed. No file edited by QA.

## Acceptance dispositions
1. **Schema exactness — PASS.** Only `stock_transfers` and `stock_transfer_items` are created (`database/migrations/2026_11_20_000024_create_stock_transfer_tables.php:23-63`). `number VARCHAR(30) NOT NULL`, UNIQUE `(from_branch_id, number)`, enum/status/default/index, three nullable user FKs SET NULL, branch FKs, `notes VARCHAR(500)`, no `created_at`/`updated_at`; items FK CASCADE, variant FK, three DECIMAL(12,3) defaults, nullable DECIMAL(14,4) cost. Schema tests verify exact columns, defaults, FKs and unique key (`StockTransferSchemaTest.php:14-45,47-91,93-123,125-157`). Catalog/Inventory schema tests were updated additively (`CatalogSchemaTest.php:68-69`, `InventorySchemaTest.php:20-21,133`). Migration is isolated.
2. **Numbering contradiction — PASS with N1/N2.** Drafts use a hidden internal `DRAFT-`+24-hex marker (`StockTransferService.php:43,770-773`), never returned to the client (`StockTransferQueryService.php:293-300`, `StockTransferService.php:775-784`); canonical allocation occurs only on `draft → requested` inside the same locked transaction (`StockTransferService.php:190-223,208`), using `DocumentSequenceAllocator::allocate('transfer', origin)` (`DocumentSequenceAllocator.php:127-130`). No client number/status/user/date/cost is accepted. Origin-scoped numbering and no stock in draft/request are tested (`StockTransferLifecycleTest.php:18-81,163-199`).
3. **Atomic lifecycle — PASS.** Create/request/cancel move no stock (`StockTransferService.php:59-247,502-545`); dispatch requires `requested`, locks transfer/items/stock in deterministic order, checks `available >= requested`, writes exactly one negative `transfer_out` per line at origin through `StockLedger`, sets `shipped_qty = requested_qty`, then `in_transit` and audit (`StockTransferService.php:254-365`; ledger source idempotency `StockLedger.php:281-300`). Receive requires `in_transit`, declares one non-negative qty per item, rejects over-receipt, writes positive `transfer_in` only for positive lines at destination, preserves `shipped − received` pending, sets `received` only when all lines match, otherwise `partially_received`; no auto-compensation/reversal (`StockTransferService.php:374-496`). Double dispatch/receive fails on status; audit failure rolls back state, stock and movements (`StockTransferLifecycleTest.php:363-384,485-523,594-627`). `current_stock.reserved` is untouched (`StockTransferLifecycleTest.php:264-314`); no direct ledger-table writes (`StockTransferLifecycleTest.php:629-641`).
4. **Access/scope — PASS.** Routes require `auth`, `password.change`, `verify.session`, `inventory.view` for reads and `inventory.adjust` for writes, plus `branch.access` (`routes/web.php:281-310`). Controller enforces module gate (`StockTransferController.php:274-279`); service enforces license (`InventoryWriteGuard.php:19-31`), branch side and active-branch checks (`StockTransferService.php:194-205,264-275,388-399,512-520,570-590`). List/detail are origin-or-destination scoped (`StockTransferQueryService.php:52-55,160-165`). Destination can receive but not dispatch; origin cannot receive (`StockTransferAccessTest.php:48-96`). Other branch 403 (`98-120`); `1E0` parity (`139-156`); read-only pages 200 / writes 422 (`184-220`); forged fields ignored (`222-292,294-339`); inactive branches/variants rejected (`341-348`, lifecycle `386-419`).
5. **UI — PASS (browser NOT_RUN).** es-CL list/detail/create/receive flows, bounded filters and per-page options, status/date/quantities/pending, actions gated by server props, explicit shortage/no-auto-resolution warning, no cost/price/margin/PII fields, notes escaped, link from `Stock.vue:33-38` (`Transfers.vue:19-43,45-86,88-195,197-263`; `TransferShow.vue:52-97,99-150,152-209,211-237`). Pagination `v-html` is limited to server-generated Laravel paginator labels, the established pattern in the existing verified `Stock.vue`; user data is not injected as HTML. Accessibility: labels, `aria-live`, roles, focus-visible, responsive table treatment.
6. **Tests — PASS with N4.** Genuine feature tests cover schema, origin-scoped numbering, draft/request/cancel, multi-line send, origin-only deduction, destination-only credit, partial/zero/full receipt, pending difference, double submit, invalid transitions, forged fields, invalid decimals, insufficient stock, inactive variants/branches, module/license, audit rollback, Kardex mapping, query props and no direct writes (`StockTransferLifecycleTest.php`, `StockTransferAccessTest.php`, `StockTransferQueryTest.php`, `StockTransferSchemaTest.php`). Prior test changes are additive allowlists (`CatalogSchemaTest.php:68-69`, `InventorySchemaTest.php:20-21,133`, `StockReadAuthorizationTest.php:207-213`); no weakening observed.
7. **Gates/evidence — PASS.** 18 mandatory gates, genuine full run, exact-tree proof/report match, SQLite isolation, protected/ChatGPT HTML/CI untouched. Metadata is truthful: INVCSV VERIFIED_TECH, INVTRANSFER pending, MariaDB/browser NOT_RUN, remote DB historical exception **not** claimed UNTOUCHED, no deploy, **not VERIFIED** (`INVTRANSFER-001-NOTES-20261120.md:1-12`; GUIA/ROADMAP metadata). N5 is a conservative stale phrase, not a false claim.
8. **Fingerprint — PASS.** Exact 24 paths/hashes; no stray edits; HEAD = origin/main.

## Findings (all non-blocking)
- **N1 — MINOR (reserved prefix vs valid branch code).** Branch codes allow `[A-Z0-9-]` up to 30 chars (`BranchRequest.php:61-62`), so a branch code beginning `DRAFT-` is valid. Its canonical requested number would also start `DRAFT-`, and both `publicNumber` implementations hide any number with that prefix even when the status is not `draft` (`StockTransferQueryService.php:293-300`, `StockTransferService.php:775-784`). Result: a requested transfer from such a branch displays `number = null`/“Borrador” while its status is `Solicitado`. No data/security impact; display/audit inconsistency. Recommend hiding only by `status = draft` or reserving the prefix.
- **N2 — MINOR/LOW-MEDIUM (long branch codes cannot allocate).** `DocumentSequenceAllocator` rejects branch codes longer than 10 chars unless a `prefixOverride` is supplied (`DocumentSequenceAllocator.php:47,168-174`), while `BranchRequest` allows up to 30 (`BranchRequest.php:61-62`). `StockTransferService::request()` calls `allocate('transfer', origin)` without an override (`StockTransferService.php:208`), so a valid long-code branch gets 422 “No fue posible asignar el número… revise el código” and cannot ever request a transfer; the branch code is immutable after creation. Failure is fail-closed (draft intact, no stock, no partial sequence) and current fixtures/seed use short codes, so this is not blocking for the approved acceptance, but it is a real configuration dead-end. Recommend a deterministic `prefixOverride` or an early explicit validation/message before R7/real branch onboarding.
- **N3 — DEFERRAL (cost snapshot).** `unit_cost_snapshot` is created nullable (`migration:62`) but never populated on dispatch/receive (`StockTransferService.php:324-336,443-453`); transfer ledger movements have `unit_cost = null`. This is consistent with the packet hard guard excluding stock valuation/cost recalculation and is documented in the notes; INV-09/valuation remains deferred.
- **N4 — TEST GAP (numbering rollback).** `test_solicitud_fallida_no_consume_el_correlativo` fails before allocation (inactive destination, `StockTransferLifecycleTest.php:209-216`), so it does not directly prove rollback after a successful allocation. The outer transaction + allocator savepoint are correct in code, and an audit-failure rollback test exists for dispatch (`594-627`); recommend an analogous request audit-failure test.
- **N5 — DOC STALENESS (non-blocking).** GUIA/ROADMAP metadata written before the final gate run still says “gates/QA/CI pendientes” (`GUIA-HTML-ESTADO.md:134`; report line 377). It is conservative and does not claim VERIFIED; update when QA/CI close.
- **N6 — PRE-EXISTING (not introduced).** `CheckPermission` passes the raw `{branch}` token for non-global users (`CheckPermission.php:34`), so code-based non-numeric URLs fail closed; numeric/`1E0` resolution matches `EnsureBranchAccess` and the controller. Not introduced by this packet; prior INVCSV QA noted the same.

## Residuals / deferrals
- MariaDB concurrency, lock ordering/deadlocks, index behavior and performance: **NOT_RUN** (SQLite only).
- Real browser/Inertia UX, accessibility rendering and responsive behavior: **NOT_RUN**.
- Exact-SHA GitHub Actions 2/2: **PENDING** (no commit/push in QA).
- Remote DB historical exception: known, disclosed, **not** claimed UNTOUCHED; no cleanup authorized.
- `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT`: user guide and roadmap editorial remain ChatGPT-exclusive; do not block unrelated correct code.
- `unit_cost_snapshot`/valuation and MariaDB/browser evidence remain deferred.
- No deploy, installation, migration/seeding, remote DB operation or production claim.

## Tests/checks not run by QA
- Full PHP suite, inventory suite, contracts, `npm run typecheck` and `npm run build` were **not rerun**; QA used the exact-tree GATES_PASS proof plus the targeted 36/616 probe.
- MariaDB, real browser UX, remote DB, migrations/seeds, deploy, commit/push: **not run**.
- No `.env`, credentials, dumps, keys or customer data read.

## Status
Independent read-only QA **APROBADO** for the exact fingerprint above. This is **not** `VERIFIED`; only external ChatGPT review of approved acceptance + independent QA + gates + exact-SHA CI may record technical `VERIFIED`, with MariaDB/browser/production checks explicitly deferred. The publisher may proceed under `AUTO_GIT` only for this exact tree, and must not infer deploy, remote DB, migration/seeding or production authorization from this QA.
