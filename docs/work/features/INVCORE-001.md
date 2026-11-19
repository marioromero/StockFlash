# INVCORE-001 — Inventory ledger and branch stock foundation

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Scope / contract
Implement P0 INV-01, INV-02, INV-03 from Part 1 §8.3 and RN-19/RN-21; use Part 2 §inventory existing approved schema. First inspect real migrations/models/permissions and contracts; avoid assuming physical schema exists. Create only the minimum approved-schema migrations if missing, subject to constitution and schema-change guard; if schema not already approved in Part 2, BLOCK that portion. Deliver transactional physical stock ledger (immutable stock_movements with balance_after, cost and polymorphic origin), current_stock per branch+variant, read stock API/UI admin with on_hand/reserved/available, and deterministic read-only reconciliation report/command that never silently rewrites ledger or balances. No stock change when catalog product is saved. Reservations remain document-owned; do not fabricate reservations or allow client-supplied reserved balances. Reuse existing audit, module entitlements and branch RBAC; no permission bypass. Inventory operations internal service only in this wave; no user-exposed arbitrary adjustments or sale posting before later packets. Define movement idempotency/duplicate origin safeguards consistent with approved schema; preserve decimal precision, branch isolation, atomic audit rollback and concurrency locking semantics. No invented new business rules.

## Acceptance / QA
- Authorized read returns correct branch/variant on_hand, reserved, available=on_hand-reserved; unauthorized branch/role/license cannot read or mutate.
- Approved internal physical movement is one transaction with locked current_stock row, append-only ledger, balance_after and audit; any failure rolls back all.
- Concurrent first-write/upsert and duplicate source cannot double-post; prove SQLite behavior and clearly defer actual MariaDB concurrency validation.
- Reversal appends inverse movement, never edits/deletes original. Reservations do not create physical movement.
- Reconciliation detects mismatches without destructive auto-correction; repeatable and bounded, no secrets/PII in output.
- Tests for positive/negative stock policy boundaries only when applicable; do not silently implement checkout/online exceptions. Validate decimal limits, FK, missing branch/variant, idempotence, permissions, disabled license, rollback, regressions.
- Fix or isolate CATPROD QA R2 N-01 inactive tax-rate issue before any future POS/Fiscal calculations; this packet must not integrate fiscal math.
- Existing scripts/sf_verify.py gates registered for INVCORE-001, full PHP suite, TS/build, contracts, independent read-only QA, selective publisher AUTO_GIT, GitHub exact-SHA CI. Use local HTIME stage boundaries best-effort, no auto status/summary polling.
- Update technical documentation and metadata; mark guide/roadmap semantic HTML PENDING_CHATGPT. No fake real-browser or MariaDB claims.

## Hard limits
No remote database, migrations against real DB, .env/secrets, install, deploy, cron, DTE, POS, sales, cash, Woo, stock transfers, CSV, counts, reservations lifecycle, user-facing stock adjustments or inventory costing policy changes. No modification of approved Part 1/2 contracts, ChatGPT-only files, public/docs/index.html or resources/roadmap/index.html. SQLite in-memory tests and CI only. Use safe_git_pull.py once before code changes; preserve local work on SYNC_BLOCKED. Maximum 3 correction rounds. Report evidence exact SHA and CI, never self-declare VERIFIED.
