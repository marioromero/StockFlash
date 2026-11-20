# StockFlash — QA efficiency Phase B (future packet gate plans)

Status: isolated PR; owner request 2026-11-20. Does not alter active INVTRANSFER-001 or any legacy gate list.

## Two post-Phase-A samples, observational only
| Report | Focused inventory | Complete PHP | Standalone TS | Build |
| --- | ---: | ---: | ---: | ---: |
| INVCSV-001-VERIFY-20261120-112945 | 50.3 s | 166.8 s | 4.0 s | 8.4 s |
| INVTRANSFER-001-VERIFY-20261120-123338 | 45.0 s | 140.8 s | 3.8 s | 7.9 s |

Measured redundant targeted PHP gate time **95.3s across two reports**. The full PHP command covers inventory through the explicit default `phpunit.xml` Unit/Feature suites. Build already invokes `vue-tsc --noEmit && vite build` according to `package.json`; separately declared typecheck gates cost another **7.8s** across both samples. Total observable repeated command time **103.1s** across these two candidates, NOT a measured savings outcome or a DeepSeek token estimate.

INVTRANSFER's earlier 12:28 failed run and its corrective final run are not classified as waste: distinct candidates/failures require full mandatory checks. There is no local HTIME JSONL in GitHub and no real model usage attribution; avoid claims about elapsed model inference or token savings. Exact-SHA GitHub CI is a separate mandatory cross-environment control, never a duplicate to skip.

## Executable future-only policy
- Existing `GATES_BY_PACKET` entries remain EXACTLY as approved. `sf_gate_policy.py` has a fixed one-time legacy allowlist, not a dynamic list that auto-exempts new packets.
- New feature packet final gates MUST include exactly one mandatory `php artisan test`; it covers `tests/Unit` and `tests/Feature` via the existing `phpunit.xml`. Targeted PHPUnit commands run during implementation and optional adversarial QA only, not as redundant final replay.
- New packet final gates MUST include exactly one mandatory `npm run build`. With existing `package.json` build script `vue-tsc --noEmit && vite build`, that is both typecheck and compilation; do not add a second final `npm run typecheck`. If `build` script changes, a separate mandatory typecheck gate is required until the checker can verify safe combined coverage.
- The checker runs BEFORE candidate proof reuse and BEFORE costly gates; rejects invalid/new duplicate final gate lists with explicit errors, never silently drops a test command and never treats a skipped test as PASS. Its checks are limited to static package scripts and default PHPUnit suite roots; if new tests lie outside these roots, their inclusion remains an explicit packet acceptance requirement.
- Preserve all separate contract, authorization, security, schema, diff, QA independent, publisher, HTTP smoke and exact-SHA CI controls. Do not disable old gates, change historical reports or alter application tests. Fallback on uncertain coverage is to restore separate checks and rerun, not omit verification.
- No new collector, daemon, raw transcript scan, cost probe, subagent, application migration or external call. No changes to active product packet, local worktree, remote DB or deployment.

## QA acceptance
Execute existing+new Python harness regressions and unchanged product CI at exact PR SHA. Independently challenge: future packet missing PHP/build, targeted PHP duplication, duplicate typecheck, changed npm scripts, corrupt/missing PHPUnit coverage, and unchanged legacy packet behavior. Merge after independent read-only QA APROBADO only, then verify exact merged-SHA CI.

## Following normal development
After adoption, read actual gate reports for 2-3 new packets and compare same-scope command time against baseline. Missing targeted final gates alone do not demonstrate model token savings. Local HTIME stage summaries can be examined on demand without launching audit models.
