# QA Efficiency Guard — HQAEFF-001 Phase A

Status: isolated PR implementation; no change to the active product packet. Owner authorization: 2026-11-19.

## Verified problem
INVREORDER-001 produced two complete 16-gate runs (194.9s and 195.1s) even though independent QA accepted the first tree. INVWRITE-001 had intervening security fixes, therefore its re-runs are not presumed waste. No claim of measured token savings.

## Executable safe reuse
- Normal sf_verify.py <ID> always executes all declared gates; opt-in sf_verify.py <ID> --reuse-evidence is the orchestrator entry point.
- No/corrupt/expired proof, modified source/tests/config, changed HEAD, altered/missing report, failed/interrupted prior attempt or any uncertainty: execute ALL gates.
- Only a real GATES_PASS report from sf_verify with unchanged Git-visible files (tracked AND untracked), same HEAD, matching report checksum, and a proof no older than two hours may be reused. Print explicit REUSED_PREVIOUS_EXECUTION and original report; do not fabricate another test run or write another report.
- Each real invocation invalidates any old proof at start, then writes one new proof atomically ONLY on genuine GATES_PASS and unchanged source across that invocation.
- Generated same-feature VERIFY/REVIEW evidence is excluded from source fingerprint. All other Git-visible files are included conservatively. Never inspect .env or private keys, even for hashing.
- Local proof lives in gitignored docs/work/.run/<ID>.gate-evidence.json. Contains only run ID, hashes, timestamp, HEAD and report basename. No secrets, prompts, session history, cost estimates or extra tool/LLM polling.

## Limits and mandatory safeguards
- Short-lived local proof is not an unchanged runtime environment guarantee: changed PHP/Node toolchain, installed vendor/node_modules, test environment, external service, or suspicious failure requires unconditional full verification without reuse flag.
- This is not a shared cross-machine build cache. Independent QA can reuse verified same-tree gate evidence but must still independently review and probe risks. Publisher still validates exact source and selects files; exact-SHA GitHub CI never skips.
- Do not remove current packet gates, security/authorization checks, regression suite, independent reviewer, CI or privacy guards.

## Clean-up decisions
- Retain HTIME telemetry and 15 existing Python regression tests; they have genuine evidentiary and safety value.
- HOBS-001..004 and optional future Phase 1/2 collector are archived, not operational. No new audit agent, session parser, daemon, JSONL audit report or extra audit-only commit.
- Do not delete historical notes, QA evidence or RUN-REPORT proposal: they are not in the default reading path and their deletion would not save per-run model context.
- Phase B (later, not implemented): measure 2–3 normal comparable features, then eliminate duplicated focused PHP suite only in new approved packets when full-suite coverage is verified.
