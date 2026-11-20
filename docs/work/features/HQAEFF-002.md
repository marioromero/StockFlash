# HQAEFF-002 — QA efficiency Phase B: no redundant final test passes

status: DRAFT
owner_authorization: explicit_2026-11-20
execution_mode: ISOLATED_PULL_REQUEST
publication_mode: REVIEW_BEFORE_MERGE
active: NO (INVTRANSFER-001 remains in CURRENT.md)
scope: harness-only, future packet plan validation; no business/functional scope changes

## Requirement
Evidence: INVCSV-001 50.3s and INVTRANSFER-001 45.0s focused Inventory tests then full PHP suite, both passing. Each current `npm run build` reexecutes typecheck, duplicating separate 4.0/3.8s final typecheck gates. Local token/HTIME files are unavailable in GitHub, so no token-savings claim.

## Deliverables
1. Add deterministic `scripts/sf_gate_policy.py`, executed at start of `sf_verify.py`. For any newly authored packet, require one full mandatory PHP suite with standard Unit+Feature coverage, one mandatory Vite build with proven typecheck or an additional mandatory separate typecheck. Reject redundant targeted PHP/test duplicates and missing gate coverage. No silent skip. Historic approved packets untouched.
2. Synthetic harness tests ensure fail-closed policy, unchanged legacy execution, no duplicate typecheck when build already covers it, and true mandatory coverage. CI tests run without model agents/DB/remote services.
3. Concise Phase B evidence document; update only relevant harness instructions; do not touch active CURRENT, product code, migrations, security controls, historical gate lists, CI workflows or secrets.

## Release
Independent read-only adversarial QA, exact-head 5/5 CI, merge only with QA APROBADO, exact-merged-SHA CI. No production/remote DB/deploy. This is not authorization to remove tests from previously approved packets or to claim token savings.

## Independent QA R1 remediation
QA R1 found future-coverage bypasses B1 (no-op build), B2 (no-op standalone typecheck), B3 (PHPUnit exclusions) and minor M1 (nonstandard duplicate commands). Corrected in scripts/sf_gate_policy.py with fail-closed exact-script checks, exclusion/suffix guards and targeted regressions. Independent R2 QA and exact-head CI remain required; this packet is not VERIFIED.
