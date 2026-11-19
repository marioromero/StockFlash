# INVREORDER-001 — Independent QA review (20261119-1725)

Verdict: **APROBADO** (independent read-only QA; not VERIFIED, no commit/push/deploy performed)
Packet: `docs/work/features/INVREORDER-001.md` — status APPROVED, `approval_basis: existing_approved_contract_owner_delegation_2026-11-16`, `new_business_decisions: NONE`, `publication_mode: AUTO_GIT`, `execution_mode: ONE_SMALL_WAVE`, stage R4_BODEGA; active in `docs/work/CURRENT.md:4`.
Contract: Part 1 §8.3 `INV-07` (P0, line 590); Part 2 §7.1 `branch_variant_settings` (lines 824–834), §7.2 `current_stock` (lines 836–847); migration batch 06 (line 158); `stock_reservations` remains a separate INV-17/18 item (line 600).

## 1. Repository state and fingerprint

- `git rev-parse HEAD` = `git rev-parse origin/main` = `7665317c53b1af4a6f4cff1a30804b5d7e2d61c1` (matches claimed base). No fetch/pull/sync performed; read-only QA.
- `git status --porcelain=v1 --untracked-files=all`: 9 modified, 12 untracked, 0 deleted/renamed/added elsewhere.
- `git diff --name-only HEAD` and `git ls-files --others --exclude-standard` yield exactly the 21 paths below; `git diff --check HEAD` exit 0.
- Count note: the QA request header says "22 paths" but its own hash list has 21 entries, and the tree has exactly those 21. No missing/extra path found.

| Path | blob hash (verified) |
|---|---|
| docs/work/GUIA-HTML-ESTADO.md | a49dc140115e7a0fc47ff5900f9303f433658c42 |
| docs/work/ROADMAP-HTML-ESTADO.md | 4c550670d1100099fa9d5e3e2de22336c0f5d187 |
| resources/js/Pages/Inventory/Stock.vue | 9a943d4ea6fa0e7992cd439be6c647d5989e306f |
| routes/web.php | 21d9fa74b4d663c5488331e1606695145ce855bc |
| scripts/sf_verify.py | f009527de63dc1b7f6afd8bd9838f823390cf8b6 |
| tests/Feature/Catalog/CatalogSchemaTest.php | 16625e146c7b46cc657f23adfe3fbebc17deafb0 |
| tests/Feature/Inventory/InventorySchemaTest.php | 57ecede9ac74a9cc4d103dfddeda2c6b9e40194a |
| tests/Feature/Inventory/InventoryTestCase.php | 1940ce65bd6f1464e8d92b774879a0adaa8194d1 |
| tests/Feature/Inventory/StockReadAuthorizationTest.php | a786cba7f037766ea8327bbaa69146e01f3bf996 |
| app/Http/Controllers/Inventory/ReorderController.php | 7027c7896a15dacb12cff192872c1792a1bbb149 |
| app/Http/Requests/Inventory/ReorderFilterRequest.php | 91ae6557a0635fa5aff428098121cef487587fdc |
| app/Http/Requests/Inventory/ReorderSettingsRequest.php | a9db15aa0b4c6b68e96648154b9b968e71725862 |
| app/Support/Inventory/ReorderQueryService.php | 1ab9e953cb0373f72b5a3a15a84a4fddeae9ec4c |
| app/Support/Inventory/ReorderSettingsException.php | c516f84e2b9a12775bcb8dce1b24dfdad1d83815 |
| app/Support/Inventory/ReorderSettingsService.php | d1e9f2cdd9f29c315a0a3415cbac5dc4c99c81f0 |
| database/migrations/2026_11_19_000023_create_branch_variant_settings_table.php | f65664e269b3653f8a2d8ba7d1183ec20a9190ec |
| docs/work/INVREORDER-001-NOTES-20261119.md | 00e89c188b09f501880dbf0f2fea096d73785ac7 |
| docs/work/INVREORDER-001-VERIFY-20261119-171648.md | 9ab75dc27b43ecf1c9d8726c6a93d56f38e9fba7 |
| resources/js/Pages/Inventory/Reorder.vue | 03894631bcc436798eb379b2994d9802ab0eb131 |
| tests/Feature/Inventory/ReorderDashboardTest.php | e185751c93a13726c2d3763404ba585f3b028677 |
| tests/Feature/Inventory/ReorderSettingsWriteTest.php | 6023fa3b2eb3ea0536428a2d27c6680e3e4011d6 |

## 2. Gate evidence (trusted as executed, same candidate)

`docs/work/INVREORDER-001-VERIFY-20261119-171648.md` records **GATES_PASS 16/16** with commands, exit codes and durations (gates 8–336):

- IR1–IR7 static/scope checks PASS (lines 8–136); IR8 contracts PASS, `OK DOC-001: 362 referencias, 6 hashes` (141–144).
- IR9 `tests/Feature/Inventory` PASS, **150 passed / 1804 assertions**, 35.4s (150–180).
- IR10 full suite PASS, **752 passed / 7415 assertions**, 146.0s (187–220).
- IR11 typecheck PASS (227–236); IR12 build PASS, 817 modules (242–259).
- IR13 guide/roadmap metadata PASS; IR14 MariaDB/remote notes PASS; IR15 secret scan PASS; IR16 `git diff --check` exit 0 (267–327).
- Tree tie: every changed source/test file has mtime ≤ 17:12:44, the gate runner itself 17:12:31, report written 17:16:48 → no code/test edit after the gate run. Consistency check: 722 (INVKARDEX) + 30 new tests (27 in the two new files + 3 added in `InventorySchemaTest.php`) = 752 suite tests; 150 inventory test methods counted independently.
- Residual evidence gap: the verify report embeds no hash/tree manifest of its own. Tie rests on hashes + mtimes + the deterministic gate content. Recommend `sf_verify` emit a small tree/fingerprint section (non-blocking).

Independent targeted replay (not a whole-gate replay): `php artisan test tests/Feature/Inventory/ReorderDashboardTest.php tests/Feature/Inventory/ReorderSettingsWriteTest.php tests/Feature/Inventory/InventorySchemaTest.php` → **38 passed / 600 assertions / 8.2s**, all green on this tree.

## 3. Targeted dispositions

### 3.1 Schema (PASS)
- Exactly one new migration; no existing migration touched (`git diff --name-only 7665317… -- database/migrations` empty). Migration list still 23 files (`InventorySchemaTest.php:110–139`).
- `branch_variant_settings` matches Part 2 §7.1 exactly: `branch_id`/`variant_id` BIGINT UNSIGNED composite PK; decimal(12,3) `reorder_point` NOT NULL DEFAULT 0, nullable `reorder_qty`/`max_stock`, `safety_stock` NOT NULL DEFAULT 0, `location_label` VARCHAR(50) NULL; both FKs CASCADE; no `id`, timestamps, explicit extra indexes/unique/triggers (`migration:19–30`).
- `stock_reservations` still absent (`InventorySchemaTest.php:21`); `CatalogSchemaTest.php:64–67` exact-table set updated legitimately (additive only).
- Minor/MariaDB-only: engine-required FK index on `variant_id` will exist on MariaDB; the SQLite-only "no plain indexes" assertion (`InventorySchemaTest.php:96–110`) would not hold there. MariaDB explicitly NOT_RUN; not contract-breaking.

### 3.2 Write path (PASS)
- One transaction: license guard, eligibility under lock, settings row `lockForUpdate`, write, audit (`ReorderSettingsService.php:71–156`, guard `:78`, eligibility `:164–184`, audit `:145–153`).
- Only `reorder_point`/`reorder_qty`/`max_stock` are updated (`:92–100`, `:134–137`); insert initializes `safety_stock='0.000'` and `location_label=null` only on create (`:102–113`); update preserves them (test `ReorderSettingsWriteTest.php:152–193`).
- Request allowlist of exactly three fields; extra body fields ignored because only `validated()` keys reach the service (`ReorderSettingsRequest.php:22–49`, `ReorderController.php:110–120`; test `:533–574`). Strict non-negative DECIMAL(12,3), no floats (`settings request regex`, `ReorderSettingsService.php:186–220`, `InventoryDecimal:quantity()` rejects float/scientific); nullable qty/max preserved; no invented `max>=point` rule (test `:243–259`).
- No `current_stock`/`stock_movements`/reservation writes; missing stock row irrelevant (test `:44–77`, `:300–325`). Inactive/non-stock-tracked/soft-deleted variant or product rejected 422 (`:261–298`). Two branches independent by PK (`:79–106`).
- Audit `old`/`new` limited to the three settings; atomic rollback proven by test doubles (`:327–388`, `:390–445`).
- Concurrency claim: the write is select-then-insert/update (not SQL `upsert`), serialized in InnoDB by the `license_states … FOR UPDATE` row plus the settings-row/gap lock; no silent lost update. Theoretical residual: if `license_states` were empty, two first-time inserts could deadlock or surface a duplicate-key 500 (no corruption, no retry). MariaDB NOT_RUN; `insertOrIgnore`/`upsert` is optional hardening, not a blocker. Parameter `actorUserId` is validated but the audit uses the authenticated session (`AuditLogger`), so it is effectively unused.

### 3.3 Read path / critical rule (PASS)
- Branch scoped via join predicates and route-derived ID (`ReorderQueryService.php:74–95`, `ReorderController.php:44,54`).
- Critical = settings row AND `reorder_point > 0` AND `available <= reorder_point`; point 0 and unconfigured excluded; equality, negative and missing-stock=0 covered (`ReorderQueryService.php:117–122,144–146`; tests `ReorderDashboardTest.php:127–170`).
- Reserved reduces available via the engine virtual column (`current_stock` migration 000022); tested `:172–187`.
- Critical filter and `critical_count` are independent of `q`/pagination (`ReorderQueryService.php:66–72`; test `:189–230`). Search bounded (request `max:100`, service `mb_substr(...,0,100)`), page size clamped 1–100 (`:30–31`), deterministic ordering `p.name, pv.sku, pv.id` plus `withQueryString()` (`:48–53`; test `:232–283` has next-page URL preserving `q`/`per_page`).
- Historic inactive/deleted settings rows remain visible with `can_edit=false` (`ReorderQueryService.php:86–95,127–135`; test `:285–316`). Props/rows/UI expose no `safety_stock`, `location_label`, cost/price/margin/PII (`:96–114`; tests `:109–124`; no `v-html` in `Reorder.vue`).
- Minor observation: `critical_count` and the panel deliberately include historic inactive/deleted configured variants (per the implemented rule); the label "Stock crítico en la sucursal" can therefore include non-operational rows. Consistent with the approved/tested rule; worth a product note, not a defect.

### 3.4 Auth / scope (PASS)
- GET `inventory.view`, PUT `inventory.adjust`, both + `branch.access` (`routes/web.php:239–241,257–259`); module gate fail-closed 403 on both (`ReorderController.php:42,95,133–138`); read-only license keeps GET 200 + notice and blocks PUT 422 before any write (`controller:86`, `InventoryWriteGuard`, tests dashboard `:318–334`, write `:447–459`).
- Branch resolution in the controller mirrors `EnsureBranchAccess` (`is_numeric → ID`, else code, then active check) (`controller:147–162` vs middleware); `'1E0'` regression proves middleware/controller agree and no cross-branch write/leak (`ReorderSettingsWriteTest.php:505–531`). Variant route token is strictly positive-int (`:164–175`), so numeric-like strings cannot retarget a variant.
- Forged `branch_id`/`user_id`/balance/cost/safety/location fields ignored; no request branch override; inactive branch 403; global vs branch-scoped roles covered (`dashboard test :336–368`, `write test :476–574`).
- Pre-existing, not introduced: for branch-scoped users the generic `CheckPermission` compares `branch_user.branch_id` to the raw route identifier (no ID/code resolution). Non-numeric branch **codes** in URLs would 403 for such users on all inventory routes; the UI always links numeric IDs and the `'1E0'` case is consistent. No regression attributable to this packet.

### 3.5 UI (PASS, brand skill PASS)
- es-CL, accessible inline edit only when `row.can_edit && can_adjust && !readOnly` (`Reorder.vue:175–189`, `445–462`); optional qty/max, per-field errors, `role=alert`/`status`, labels bound, focus-visible, responsive stacked table, pagination with preserved URL (`:37–48,69–107,146–316,470–490`).
- Summary, critical badge, filter, historic read-only labels, license notice present; no sensitive fields, no raw HTML.
- Brand: new screen reuses the exact shell/tokens of the existing `Inventory/Stock.vue` and `Inventory/Kardex.vue` (text fallback lockup; SVG prototypes only, no PNG set installed). No new brand drift introduced; literal brand-contract hexes differ across all existing inventory screens (pre-existing, outside this packet). Browser visual review NOT_RUN.

### 3.6 Tests (PASS, genuine)
Schema exactness/PKs/FKs/single-migration (`InventorySchemaTest.php:18–139`); critical boundaries/zero/negative/missing stock (`ReorderDashboardTest.php:127–170`); reserved (`:172–187`); filter/count independence (`:189–230`); bounded search/pagination + next-page filters (`:232–283`); historic read-only (`:285–316`); read-only license (`:318–334`); permissions/module/branch/inactive (`:336–368`); branch isolation (`:370–395`); GET side-effect-free + only two approved reorder routes (`:397–439`); empty state (`:441–455`); write create/update, null round-trip, safety/location preservation, precision/scale, `max<point`, ineligible variants, no stock/movement side effects, audit exactness, rollback, license, module, permissions, `1E0`, extra-field rejection (`ReorderSettingsWriteTest.php:44–574`). Modified prior tests are additive/legitimate (exact table set, route list). No tautological or weakened assertion observed.
Coverage gaps (minor): no explicit pagination with >100 rows, no explicit `available = point + 0.001` probe, no explicit "safety_stock does not influence criticality" row test, no explicit branch-scoped non-numeric branch-code case.

### 3.7 Gates / scope / metadata (PASS with minor notes)
- `scripts/sf_verify.py:1215–1263` adds IR1–IR16, all `mandatory: True`, additive only; no other packet block edited (`SETUP-001` block begins unchanged at line 1265).
- No protected/ChatGPT-only edits: `public/docs/index.html`, `resources/roadmap/index.html`, `AGENTS.md`, `.github/**`, contracts untouched; only OpenCode-owned technical metadata updated (`GUIA-HTML-ESTADO.md`, `ROADMAP-HTML-ESTADO.md`) and flagged `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT`.
- Metadata truthful for state at write time: INVKARDEX-001 VERIFIED_TECH, INVREORDER pending/documented, MariaDB/browser NOT_RUN, remote DB untouched, no deploy, NOT VERIFIED (`NOTES:6–10,42–49`).
- Minor: `NOTES:42–49` says full suite/gates were not run and that `sf_verify.py` was not edited; that was true only for the implementer role. The orchestrator later registered gates (17:12) and ran them (17:16). `GUIA-HTML-ESTADO.md:126` still reads "gates… pendientes". Refresh both before publication (documentation-only; no code impact).

## 4. Residuals / deferrals (none blocking)

1. MariaDB real migration/index, concurrent first-insert, deadlock/retry behavior: **NOT_RUN** (expected FK-supporting index on `variant_id`; SQLite-only "no plain indexes" assertion is not MariaDB-representative).
2. Browser visual/UX and 10"/touch review: **NOT_RUN** (code-level a11y checked only).
3. Gate report carries no internal hash manifest; fingerprint tie is by hash + mtime + deterministic commands.
4. Documentation refresh: implementer-role notes and guide metadata mention gates as pending; update after QA/CI.
5. Optional hardening: replace select-then-insert with `upsert`/`insertOrIgnore` + retry; remove unused `actorUserId`.
6. Product nuance: historic inactive/deleted configured variants are counted as critical; confirm this is the intended operational meaning of the branch critical counter.

## 5. QA scope statement

Read-only, independent review of the exact 21-path candidate on base `7665317c53b1af4a6f4cff1a30804b5d7e2d61c1`. No files edited, no commit/push/deploy, no `.env`/secret/remote-DB access, no full gate/suite replay (existing same-tree GATES_PASS evidence accepted; one targeted 3-file test replay executed). This is independent QA acceptance, **not technical VERIFIED**; exact-SHA CI, external ChatGPT review and any publish decision remain outside this report.
