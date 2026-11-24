# SELLERS-001 — Seller register and branch assignments (POS prerequisite)

status: VERIFIED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R6_POS_PREREQUISITE
active: docs/work/CURRENT.md

## Why / canonical authority
First commercial release R7 requires POS+Bodega without integrated DTE. CUSCORE-001 (CUS-01/02/03) is VERIFIED_TECH; next independent P0 is Part 1 §8.9 **SEL-01**: seller CRUD with unique code, optional linked user, branch associations. Apply Part 1 §7.2 **RN-11/RN-13/RN-14** (seller code policy future sale use; codes unique/non-reusable, deactivation not deletion, code is identification NOT authentication). Follow approved Part 2 §6.5 `sellers` (SD, PII) and `branch_seller`, existing role/branch/licensing conventions. Seller sales attribution RN-12, reports SEL-02/SEL-03 and commissions/report SEL-04 are NOT implemented before sale domain exists.

## Functional delivery
1. Inspect actual migrations/models/branch-user permission framework before editing. Create ONLY missing canonical tables with approved fields, constraints and SD timestamps: `sellers`: id, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, user_id nullable FK users SET NULL, commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0, is_active TINYINT UNSIGNED NOT NULL DEFAULT 1, anonymized_at DATETIME NULL, created_at/updated_at/deleted_at. `branch_seller`: composite PK seller_id+branch_id, both FK CASCADE. No new business columns, audit tables, new entitlements or unrelated migrations.
2. Ensure the reserved seller with code `NONE` and visible label `Sin asignar` exists reproducibly without introducing a normal editable seller or unsafe side effects. Never permit the reserved record to be edited, deactivated, linked to a user, branch-scoped or re-used. Do not auto-create it by unrelated GET requests; use the project's approved seeding/initialization convention. The code `NONE` identifies a fallback aggregate only and never grants access.
3. Build a simple accessible Spanish (es-CL) POS administration view, linked from Dashboard for eligible managers, with bounded pagination/search, create, view, edit, activation/deactivation, unique seller code, optional user relation, multi-branch association, optional commission percentage. Preserve entire branch assignment on edit in one transaction. Show status and branch assignments. Do not fabricate sales, reports, commissions earned or seller-target metrics.
4. Reuse existing `sellers.manage` for management/read/write; respect existing global vs branch-scoped permission resolver. A branch-scoped supervisor may only list/manage sellers in branches where they are authorized, and cannot add, remove or change associations in unauthorized branches. A global authorized manager may manage all. Validate assigned branch IDs exist and are active; if optional linked user is given, it must exist, be active, and have authorized access to assigned branches (global users handled under existing policy). Do not expose unrelated users' emails, RUT, personal data or secrets as dropdown data. Assignment reads must not create cross-branch information leaks. `sellers.view_reports` does NOT alone grant seller-management/PII visibility.
5. Code uniqueness must hold including soft-deleted records and concurrent duplicates (DB unique + generic validation). Use an explicit stable case-normalized code representation consistent with database collation; enforce 1..20 format without confusing seller code with auth/password/PIN. No hard-delete of sellers or reused codes (RN-13). Anonymized seller cannot be mutated/reactivated. Activate/deactivate is server-authorized and audited.
6. Validate `commission_percent` as decimal string between 0 and 100 with exactly two decimal places saved, no floating rounding or inferred gross/net payout semantics. Just store the contracted field; no commission calculation/report until sales exists. Reject invalid/malformed/forged fields and unexpected changes to protected attributes. Use canonical branch/module/license read-only guards; server authorization on every action, not only UI links.
7. All writes (seller, pivot mappings, audit event) in a single DB transaction with rollback on validation, permission, inactive related user/branch, duplicate code or audit failure. Audit seller ID/code? Code is not authentication but could identify an employee: log only seller ID, status, branch IDs and changed field names, not the name or linked user email/PII. Read-only paths must not disclose private user details.
8. Preserve and never edit ChatGPT-only `public/docs/index.html`, `resources/roadmap/index.html`, or their presentation rules; after technical publication mark `GUIA_PENDIENTE_CHATGPT` and `ROADMAP_PENDIENTE_CHATGPT` in technical ledgers. Preserve public /docs/ and /roadmap, one-time /setup gate, CUSCORE privacy, all inventory.
9. Tests only on explicit isolated SQLite `:memory:` environment: exact canonical schemas/PK/FK/indexes, sentinel NONE idempotent/immutable, unique incl. soft deleted and case-equivalent, valid/invalid decimals, nullable user link, seller branches, active/inactive/anonymized, branch-specific view and write rights, forged branch/user IDs, cross-role information leaks, module disabled, read-only license, server validation/CSRF/forged fields, audit rollback, concurrent duplicate simulation, Dashboard link, main CUSCORE/inventory and public-route regressions. Report MariaDB locks/concurrency and browser UX NOT_RUN, never claim production.
10. Follow approved `scripts/sf_verify.py SELLERS-001` full deterministic gates (HQAEFF-002 future-packet policy must pass; do NOT add SELLERS-001 to HISTORICAL_PACKETS), full PHP suite, Vue typecheck+Vite build, contracts, independent read-only QA on exact fingerprint, safe AUTO_GIT publication, and GitHub Actions exact-SHA 2/2 SUCCESS. Existing fixes in PR #59 must remain untouched. No new owner business decisions unless a truly material ambiguity blocks a portion.

## EARS acceptance
- WHEN an authorized manager registers a seller, the system MUST create one unique immutable code identity and the explicitly selected active-branch associations, with optional valid linked user and safe default commission.
- WHEN a branch-scoped manager attempts any out-of-scope read/write/assignment or a seller code is reused (including soft-deleted), the request MUST fail without partial writes or PII leakage.
- WHEN a valid seller is deactivated, the identity/history and unique code MUST remain; code `NONE` MUST never become a normal editable seller or login credential.
- WHEN a seller record or branch assignment changes, it MUST be audited in the same transaction and rollback if audit fails; no seller sales attribution/report is created.

## Hard guards
No POS checkout, sale tables, tickets, reservation documents, customer schema edits, return/report/commission calculations, loyalty, billing, DTE, Woo, fiscal, remote DB, credential reading, environment-based debug scripts, real DB migration/seeding, deploy/cron/install, unapproved schema or roles, modifications to approved product contracts, harness historical policy or ChatGPT-only semantic HTML. No speculative user business questions for routine code/ordering. Use safe sync once, preserve local edits, no git reset/force; max three corrective QA rounds. Report `RESULT` or concrete `BLOCK`, never self-declare VERIFIED.

## External technical closure — GPT, 2026-11-23
status: VERIFIED_TECH
published_code_sha: `e46f25f14b9c0114abbd2964c893d4d6960d655d`
qa: APROBADO independent read-only, `docs/work/SELLERS-001-REVIEW-20261123-1630.md`, 57 seller tests / 636 assertions independently reproduced on isolated SQLite.
gates: 15/15 GATES_PASS on original candidate fingerprint, `docs/work/SELLERS-001-VERIFY-20261123-162335.md`.
php: 941 tests / 10347 assertions, full gate execution.
ci_exact_sha: 2/2 SUCCESS, contracts `45249487071`, PHP/Node/harness/smoke `45249487082`.
scope: P0 SEL-01 seller registry, unique immutable codes, branch assignments, optional user, reserved NONE, commission percentage stored without calculation, auditable writes and access checks.
not_delivered: SEL-02 sales attribution, SEL-03 sales report, SEL-04 earned commissions report, SEL-05 sales targets. MariaDB concurrency, real browser UX and deployment NOT_RUN. No remote database changes.
editorial: `GUIA_PENDIENTE_CHATGPT`, `ROADMAP_PENDIENTE_CHATGPT`; avoid unrelated HTML changes while isolated harness PR #62 awaits independent QA.
