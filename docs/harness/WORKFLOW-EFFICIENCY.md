# OpenCode workflow efficiency — behavior-preserving controls

**Goal:** reduce context and repeat work, not reduce verification. This file is subordinate to AGENTS.md, the packet and deterministic gate definitions. It guides the orchestrator, implementer, independent reviewer and publisher.

## Reading and investigation
1. Start from active packet, exact diff (tracked **and untracked**), changed-file manifest, current SHA and previous unresolved findings. Search limited target paths and dependency calls before using repository-wide glob. Expand if justified, not on a fixed checklist.
2. Prefer native read/grep/glob and narrow ranges; no redundant Get-Content/Select-String/Python scanning when the same content is already in context. Do not repeatedly read the same file unless its fingerprint changed or a specific question remains.
3. Always distinguish Unicode code points from encoding corruption. Ordinary accents such as á, ñ, ó are expected; check for invalid UTF-8 or U+FFFD/mojibake instead of flagging every U+00C3. One precise scan of changed source files suffices unless evidence shows another problem.
4. Write a short evidence pointer + filename/line and bounded terminal output, not a full 10,000-line log in model context. Keep complete command output in safe local evidence when required, with sanitized report excerpts in Git.
5. Never require the model to reread the long historical harness docs or the entire v2.2 contract on every task. Use MAPA-DE-LECTURA and only task-relevant skill sections.

## Tests, gates and QA
6. During implementation run **targeted tests**/static checks after each material change. The orchestrator uses the opt-in local proof guard: `py -3 scripts/sf_verify.py <ID> --reuse-evidence`; it still executes all gates if any evidence or source check is uncertain (see `QA-EFFICIENCY-GUARD.md`). Run the approved **full deterministic packet gate** once per final candidate state. The final PHP suite, contract checks, typecheck/build and security tests remain mandatory exactly as declared by the active packet.
7. **Phase B (future packets only):** use focused PHP suites during implementation; the final candidate declares a single mandatory `php artisan test` covering Unit + Feature via phpunit.xml and no repeated focused PHP gates. Also declare one mandatory `npm run build`; the current package script runs `vue-tsc --noEmit && vite build`, so a standalone final `npm run typecheck` duplicates work. If the build script changes, keep mandatory standalone typecheck until coverage is proven. `scripts/sf_gate_policy.py` enforces this at start of `sf_verify.py` for newly authored packets and fails closed on missing coverage; historic approved packet gate lists are grandfathered unchanged. See `QA-EFFICIENCY-PHASE-B.md`. Never silently skip tests or replace QA/CI.
8. The independent reviewer must inspect changed/untracked files, attack surfaces and acceptance criteria; it **can reuse successful deterministic gate evidence with identical verified fingerprints** instead of routinely rerunning the same full test suite. It may run targeted adversarial repro tests to challenge evidence. Reviewer never approves its own changes or relies on an unverified handoff.
9. Each correction cycle begins from **the previous QA findings** and the changed-file fingerprints. Review precisely the fixes, their propagation and any newly affected security/behavior paths. Orchestrator still reruns *all mandatory final gates* on the changed candidate and obtains a new independent QA verdict before publication.
10. Publisher compares staged inventory + untracked list + fingerprints with the exact files covered by gates and QA. Rerun only if an actual source/verified evidence mutation occurred; do not rerun the full suite just to restate an unchanged checked SHA. This does not change mandatory exact-SHA CI after push.
11. No infinite retries, no competing subagent review trees, no repeated diagnosis without a new signal. Maximum correction rounds per active packet remains three; stop with concrete BLOCK when a contract/owner decision or tool permission is genuinely missing.
12. Treat time-consuming local commands and model token consumption separately. Do not claim savings until native metered counts or a controlled A/B with explicit limitations exists.

## Historical audit, no automatic observer
HOBS-001/002/003/004 are closed diagnostics; its Phase 1/2 future collector design is archived, not routine work. Retain native HTIME timing and the minimal local proof guard only. Do not start a session export, audit agent, continuous watcher, second event logger or automatic audit-only publication.

## Stage timing
15. At each genuine stage boundary (implementation, verification, QA, correction, publication and CI wait) spend one tiny CLI call: `py -3 scripts/sf_timeline.py begin <ID> <stage> --actor orchestrator ...` and the matching `end ... --status ...`. Data stays in the ignored `docs/work/.run/<ID>.timeline.jsonl`; `status`/`summary` are read-only recovery aids that report **suma** vs **union** without double counting overlap, while wall-clock idle and model inference remain UNKNOWN. Telemetry never authorizes skipping a gate, and a telemetry failure neither blocks nor unblocks approved work.

On-demand read-only telemetry shortcuts: `/sf-time-status <ID>` and `/sf-time-summary <ID>` wrap the existing local Python CLI. Keep both dormant unless a person or a specific authorized diagnosis invokes them; **do not add automatic model or tool invocations** to ordinary feature runs. Existing stage boundary writes are separate and unchanged.

## Do not optimize away
RBAC and entitlement checks, CSRF/session/bootstrap guards, transaction/locking tests, privacy and security adversarial review, exact-SHA CI, safe Git, separate read-only QA, owner decisions for deployment/remote DB, and completeness of the final release gates.
