# INVCSV-001 — Initial branch stock import from CSV

status: VERIFIED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R4_BODEGA
active: docs/work/CURRENT.md

## Contract and priority
Complete the CSV half of approved Part 1 §8.3 `INV-05` (P0: initial stock manually and via CSV), after INVCORE-001, INVWRITE-001, INVKARDEX-001 and INVREORDER-001 VERIFIED_TECH. Part 1 RN-21 `initial` physical movements, RN-19 stock lock/idempotence, Part 2 §7.2/7.4 `current_stock` / `stock_movements`. Reuse existing transactional `StockWriteService::loadInitial`, `StockLedger`, `InventoryWriteGuard` and authorized branch resolution. SEC-08 requires validated uploaded CSV outside public. CAT-10 asynchronous catalog import/dry-run is a DIFFERENT P1 requirement and does not authorize/require a catalog importer here.

## Deliverable — one coherent P0 inventory feature
1. Provide an authenticated es-CL flow from existing branch inventory screen for **initial stock only** (not adjustments): upload CSV, review a bounded server-validated preview with row-level errors and totals, then explicitly confirm. Provide a small download-safe CSV template and clear help in UI. Choose a documented, deterministic and Excel-compatible format (UTF-8 BOM accepted, header `sku,quantity` or equivalent explicit labels, comma/semicolon delimiter handling as appropriate); never infer SKU from product name, barcode, or index and never load to arbitrary branch from file. Branch derives only from authorized URL, user from current session. No network storage or external import service. No persistent import staging table needed; re-parse and revalidate the original uploaded bytes on final submission rather than trusting any client-supplied preview/checksum/rows.
2. Existing `inventory.adjust` + `branch.access` + enabled module + active branch + license writable gates enforced on BOTH preview/commit server paths as appropriate; read-only users may see documentation/template if safe, but cannot commit. No cross-branch imports even for variant IDs/SKUs embedded in malicious CSV. Consistent route/authorization resolver (including numeric-looking legal branch codes `1E0`).
3. Parse with bounded streaming CSV reader, robust UTF-8/CRLF/BOM handling, blank rows and malformed encoding/quoting/delimiter/oversized fields rejected fail-closed. Enforce modest documented file/row/byte limits for denial-of-service protection (technical limits, not plan quotas). File upload MIME/extension/size validation, temporary Laravel non-public storage only, lifecycle cleanup on success/failure; never publish raw CSV, user-supplied formulas, secrets or path to public URLs/logs. Reject duplicate SKU rows in same file, unknown/ambiguous SKU, ineligible/deleted/inactive/non-stock-tracked variants and any branch/variant with existing physical movement history; all numeric quantities DECIMAL(12,3) positive, exact string validation without floats, no implicit zero movement, no decimal truncation. Include line-numbered safe, bounded diagnostic feedback (no PII or database details).
4. Final confirm must be **all-or-nothing atomic**: validate entirety, acquire branch+variant physical stock locks in deterministic order and call the authoritative append-only `initial` ledger path for each item under a single outer transaction; any duplicate, failed audit/validation/concurrent prior movement, license flip or DB exception rolls the entire batch back (including audit and every `current_stock` change). Resubmit/retry of same file must never double-load; fail with clear already-initialized status rather than silently overwriting. Do NOT bypass `StockLedger`, write direct stock rows, or use `adjustment` for import.
5. No new tables/migrations, no catalog product creation/update, no price, cost valuation, taxes, reservations, branch transfers or POS. Do not implicitly create stock on catalog CRUD. No client-supplied user, stock balance, movement type/source, reservation or authorizer field accepted.
6. Tests: UTF-8 BOM, Chilean Excel semicolon case, malformed CSV/quote/encoding, empty/extra rows, missing header, duplicate and unknown SKU, invalid or precise decimal (incl >9 integer digits, >3 decimals, exponent, negatives and zero), >limit rejection, stale preview, first and second upload, file type/size, auth/role/branch (numeric-code regression), disabled module/license, inactive products, mixed variants, atomic rollback when second row/audit fails, concurrent retry simulation, no direct changes to `reserved`, no schema changes, cross-branch isolation, no PII/formula injection, and all existing inventory + catalog regression.
7. Register `scripts/sf_verify.py INVCSV-001` deterministic mandatory gates. Respect newly merged HQAEFF-001 fail-closed evidence guard `scripts/sf_evidence.py` and reviewer independence; verify full PHP suite, contracts, npm typecheck/build, independent read-only QA exact fingerprint, AUTO_GIT fast-forward, and GitHub Actions exact-SHA 2/2 SUCCESS. Update technical notes and GUIA/ROADMAP metadata only; HTML semantic guide and roadmap remain ChatGPT-exclusive.

## Acceptance EARS
- WHEN an inventory.adjust user submits a valid CSV to a permitted branch and confirms it, exactly one immutable `initial` movement MUST be appended for every eligible SKU, on_hand and audit MUST commit together, and every row MUST use that branch.
- WHEN any row is invalid, already initialized, duplicated, or becomes conflicting before commit, the whole batch MUST fail without any stock/movement/audit partial effects; retries MUST not double-post.
- WHEN the upload exceeds safety limits, contains hostile CSV content, or comes from an unauthorized/read-only account, the backend MUST fail closed and not leak data.
- WHEN preview and confirm differ, the backend MUST treat the confirm's bytes and current DB state as authoritative; no trust in client preview fields.
- WHEN SQLite/CI is the only execution, MariaDB concurrency/performance and browser UX MUST be recorded NOT_RUN.

## Out of scope / hard guards
Do not implement CAT-10 catalog CSV import/export, INVENTORY adjustments via CSV, transfers (INV-06), reservation lifecycle (INV-17/18), imports of price/cost/tax/products, purchases, sales/POS, channels, fiscal/DTE, online publication, business licensing limits, migration/schema changes, new background queue/cron, dependencies, remote DB/install/migrate/seed, deployment, .env/secrets, protected docs or contracts. New material business/legal/security choices require a concrete BLOCK only for that portion. Sync via safe_git_pull.py once and fail closed on SYNC_BLOCKED. Max 3 correction rounds, exact tree QA/gates/CI, ChatGPT alone declares VERIFIED.

## External technical closure — ChatGPT, 2026-11-20
status: VERIFIED_TECH
code_sha: `f8565927d3724759e614b65f3cdf181b803c5d5b`
gates: 17/17 GATES_PASS (HQAEFF full run, isolated SQLite)
qa: independent APROBADO; `docs/work/INVCSV-001-REVIEW-20261120-1137.md`
php: 779 tests / 7805 assertions
ci_exact_code_sha: 2/2 SUCCESS (PHP/Node 44755780083; contracts 44755780157)
scope: CSV initial stock preview+commit, all-or-nothing via existing StockLedger; no new schema or remote DB activity in implementation/resume phase.
limits: MariaDB concurrency/locks NOT_RUN, real browser Inertia preview UX NOT_RUN, no deploy; known historical remote DB exception not cleaned or claimed untouched, owner declined additional incident work.
pre_release_backlog: parser strict malformed quote edge F1, UTF-8 BOM plus fully-quoted header F2, POST Inertia preview refresh/navigation F6; independently QA-accepted non-blocking for technical code closure, must validate/correct before commercial release. Source QA report lists findings.
editorial_docs: `public/docs/index.html#inventario-stock` and `resources/roadmap/index.html` integrated by ChatGPT, final editorial HEAD CI remains separate.
