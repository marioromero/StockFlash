# INVKARDEX-001 — Branch and variant physical Kardex report

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R4_BODEGA
active: docs/work/CURRENT.md

## Approved contract / purpose
Implement Part 1 §8.3 INV-08 (P0) read-only Kardex report by branch, variant and date period, on top of INVCORE-001 / INVWRITE-001 verified physical movements. Use Part 1 RN-19/RN-21 and Part 2 §7.4 stock_movements, no invented status, origin or costing policy. Do not alter the ledger or transactions.

## Functional scope
1. Add an authorized, navigable es-CL report screen from existing Inventory UI; GET-only route(s). Branch comes from trusted resolved URL parameter / existing branch.access middleware, never from unchecked query or UI prop. `inventory.view` and existing module gate required; respect active branch, global vs branch roles, license read semantics, and hidden/inactive variants without leaking other-branch data.
2. Filter by selected branch (server-authorized), variant or product/SKU search, optional inclusive date range against `stock_movements.occurred_at` with validated bounds; avoid unbounded queries (cap results per page <=100; stable cursor or keyset pagination where practical), preserve filters across pagination. If date-only input, inclusive end-of-day and precise timezone interpretation consistent with existing app conventions. Invalid date/order/branch/variant parameters must fail safely (422/403) without querying broader scope.
3. Show append-only physical events with movement timestamp, type/event (human-readable es-CL mapping for approved RN-21 types), signed quantity in 3-decimal precision, balance_after, branch and variant/SKU, optional safe source reference (non-sensitive), reversal identification. The ledger balance chain is by insertion/ID order, **not** occurred_at chronological order; never compute a running balance by reordering timestamps. If the report sorts by date, clearly distinguish recorded balance_after from re-sorted date order, or sort by append ID for ledger consistency. Preserve original records when reversed; do not hide corrections, no edit/delete endpoints.
4. Do not disclose unit_cost, cost valuation, price, margin, personal data or authorizer/PIN to unauthorized users. A basic quantity-only report without costs is sufficient for INV-08; cost extensions are a separate approved scope with `catalog.view_cost` and contract checking. Avoid untrusted HTML/raw source data in UI, SQL injection and N+1 per row.
5. Helpful empty state, accessible filters/table, pagination and handling for no movements or inactive product. Technical documentation updated, guide/roadmap semantic HTML marked PENDING_CHATGPT, not edited by OpenCode.
6. Test branch isolation even with forged filters/code, missing/inactive branch, role permission/read-only license, module disabled, variant inactive and mixed branches, opening/adjustment/reversal, same occurred_at ties, backdated movement ordering and correct stored balance_after, precision, bad dates and pagination bounds, no write effects, sensitive field leakage, proper search, and catalog regression. Include independent read-only QA on exact fingerprint, deterministic `scripts/sf_verify.py INVKARDEX-001` gates, full PHP suite, contracts, npm typecheck/build, AUTO_GIT and exact-SHA GitHub Actions.
7. Reuse approved design patterns, Vue/Inertia routes and compact SFH1 workflow. Avoid new migrations/dependencies unless canonically approved and essential; there should be no schema change for this read-only report.

## EARS
- WHEN a user with inventory.view opens a branch Kardex report, the system MUST show only authorized physical movements for that branch with correct signed quantity and authoritative stored balance_after.
- WHEN searching by variant/SKU or date range, the system MUST filter server-side, paginate deterministically, and never broaden beyond authorized branch scope.
- WHEN records are backdated, the report MUST not misrepresent historical balance_after as a recomputed chronological balance; reversals remain visible.
- WHEN read permission/module/branch validation fails, the server MUST reject or return safe empty data without exposing stock history.
- WHEN the page is read, the system MUST not change stock, reservations, audits or business status.

## Hard guards / deferrals
No bulk CSV (pending INV-05), initial adjustments changes (INVWRITE-001 already VERIFIED_TECH), transfers, reservations, inventory counts, purchase order/receipt, sales/POS, fiscal/DTE, Woo, cost valuation, exports or mutation endpoints. No new business decisions, contract edits, arbitrary migrations, secrets/.env, remote DB access/migrations/seeding, deployment, install, cron or new automation. Safe sync once, fail on SYNC_BLOCKED, max 3 correction rounds. SQLite tests only; real MariaDB concurrency/performance and browser UX deferred with explicit NOT_RUN. OpenCode cannot mark VERIFIED; only external ChatGPT after QA and exact-SHA CI.
