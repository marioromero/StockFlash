# INVWRITE-001 — Manual initial stock and controlled adjustments

status: VERIFIED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R4_BODEGA
active: docs/work/CURRENT.md

## Contract / why
Next functional inventory wave after INVCORE-001 VERIFIED_TECH. Owner-approved Part 1 §7.4 RN-21, §8.3 INV-04 (P0), INV-05 (P0 manual portion); Part 2 §3.2 stock_adjust_pin_value_threshold, §4.12 reason_codes(scope=stock_adjustment), §4.18 authorization_overrides(type=stock_adjustment), §7.2 current_stock, §7.4 stock_movements. Reuse COREPIN-001 and StockLedger; no fresh schema/business policy. This feature implements usable manual opening quantities + adjustment transactions for a single branch/variant, not CSV. CSV component of INV-05 remains explicitly pending, not VERIFIED.

## Deliverable
1. Authenticated es-CL UI integrated in existing /inventory/stock/{branch} or a small adjacent inventory screen: authorize branch selection in server middleware, show on_hand/reserved/available before submission, per-variant manual initial quantity and signed adjustment with reason + optional note. Accessible/responsive; forbid all mutation for read-only roles, license or disabled modules. Use existing `inventory.adjust` permission, not client-side visibility alone, and existing branch.access; do not trust client branch_id/user_id/stock balance/cost/role/authorization ID.
2. Initial physical load: use immutable `initial` movement through existing StockLedger transactional API. Only for physical stock-tracked, active eligible variants, and only where there is no prior physical history for that branch/variant; reject attempts to 'reinitialize' rather than silently overwrite history. Explicit nonnegative initial quantities with DECIMAL(12,3) validation and no float rounding; clarify whether zero is no-op or record-only from approved contract/ledger behavior, not a fake positive movement. A repeat request must not accidentally double-stock. No implicit stock creation on catalog save.
3. Adjustment: append signed `adjustment` movement; mandatory reason_code_id from existing active `reason_codes.scope=stock_adjustment`, notes if `requires_note`. Validate strictly signed nonzero DECIMAL(12,3), valid branch/variant, allowed note lengths, no arbitrary movement type or source/user fields, no direct `current_stock` writes except StockLedger. Negative stock policies for POS checkout RN-22 must not be imposed on ordinary stock corrections without an approved rule.
4. Existing configuration `stock_adjust_pin_value_threshold`: 0 disables threshold. For above-threshold value at relevant cost, use existing COREPIN internal service, type=stock_adjustment with identified authorizer (scope/authority/branch), secure attempt lockout and atomic audit. All costs and approval calculations are performed server-side; never leak PIN, authorization secrets or confidential cost to unauthorized actors. If a material ambiguity in cost basis or exact threshold boundary exists after reading canonical contracts, isolate that subcase and report a concrete BLOCK; continue independent implementation. No blanket PIN bypass.
5. Maintain module/license/RBAC and transaction isolation, audit source/motive/actor, consistent rollback (approval+ledger if applicable); resolve retries/idempotency safely within existing schema, avoiding duplicate physical moves. Keep reservation cache untouched and no fabricated reservations. Present clear 403/422 and es-CL validation messages.
6. Quality: tests SQLite :memory: for initial + valid/invalid adjustments, duplicate initial submission, reason scope/inactive/requires_note, PIN below/above/disabled threshold, permission per branch, admin vs user, module/license fail-closed, audit rollback, cost secrecy, no direct balance tampering, numeric precision/overflow, concurrent simulation limits, empty stock, negative correction and catalog/regression tests. Reuse scripts/sf_verify.py with INVWRITE-001 gates, full PHP suite/typecheck/build/contracts, independent read-only QA on exact fingerprint, selective AUTO_GIT publishing, GitHub exact-SHA CI. Record no real MariaDB/browser validation.
7. Keep `docs/work/GUIA-HTML-ESTADO.md` and `docs/work/ROADMAP-HTML-ESTADO.md` technical metadata accurate, including completed INVCORE-001; mark semantic user guide and roadmap PENDING_CHATGPT for this wave. Do not edit ChatGPT-owned HTML, continuity files or authorized contracts. Use optional HTIME stage markers, never automated timeline status/summary probes.

## Acceptance EARS
- WHEN a permitted operator records initial stock on an eligible uninitialized branch/variant, the system MUST append exactly one `initial` movement, atomically update existing stock and audit; repeat/wrong branch/invalid item MUST not silently duplicate/change stock.
- WHEN a permitted operator adjusts stock, the system MUST require an active appropriate reason, calculate and append the signed physical movement via StockLedger, and leave reserved untouched.
- WHEN adjustment requires PIN under approved threshold, the system MUST check a valid distinct authorization event for that operation and roll back failures without leaking PIN, even when operator has inventory.adjust.
- WHEN license/module/permission/branch/validation/audit/PIN checks fail, the system MUST not persist a partial physical stock mutation.
- WHEN only code/SQLite CI validation exists, the report MUST retain MariaDB concurrency, real browser and production/deployment as NOT_RUN.

## Hard guards
Do NOT alter approved schemas except if a required structure is explicitly in Part 2 and not already implemented; no arbitrary migrations/triggers/dependencies. No CSV batch import in this feature (INV-05 CSV deferred), no transfers, counting, reservations, sales, POS, DTE, Woo, purchase receipts, cost valuation or priced margin recalculation. No remote DB operations, migration/seeding against real database, real installation, credentials/.env, cron, deployment, changing plans or broadening business decisions. Code work starts with one safe_git_pull.py SYNC_OK, no reset/stash/clean. Max 3 correction rounds, deterministic gates + independent QA + exact-SHA CI. ChatGPT alone records VERIFIED.

## External technical closure — ChatGPT, 2026-11-19
status: VERIFIED_TECH
code_sha: `816fdc6cb2ab7f1757f648ce7d552c07256e3379`
gates: 18/18 GATES_PASS
qa: independent APROBADO; `docs/work/INVWRITE-001-REVIEW-20261119-1535.md`
php: 710 tests / 6502 assertions
ci_exact_code_sha: 2/2 SUCCESS (PHP/Node run 44589236116; contracts run 44589236165)
external_review: authorization PIN + ledger atomicity, locked initial-history invariant, self-authorization aligned to COREPIN, no-PIN TOCTOU authorization fix.
limits: MariaDB concurrency/lock order/deadlocks NOT_RUN; real browser UX NOT_RUN; remote DB UNTOUCHED; no deploy; CSV portion of INV-05 still NOT_IMPLEMENTED.
editorial_docs: `public/docs/index.html#inventario-stock` and `resources/roadmap/index.html` updated by ChatGPT. Docs-only CI must also pass at eventual handoff HEAD.
