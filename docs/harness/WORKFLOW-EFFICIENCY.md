# OpenCode workflow efficiency — behavior-preserving controls

**Goal:** reduce context and repeat work, not reduce verification. This file is subordinate to AGENTS.md, the packet and deterministic gate definitions. It guides the orchestrator, implementer, independent reviewer and publisher.

## Reading and investigation
1. Start from active packet, exact diff (tracked **and untracked**), changed-file manifest, current SHA and previous unresolved findings. Search limited target paths and dependency calls before using repository-wide glob. Expand if justified, not on a fixed checklist.
2. Prefer native read/grep/glob and narrow ranges; no redundant Get-Content/Select-String/Python scanning when the same content is already in context. Do not repeatedly read the same file unless its fingerprint changed or a specific question remains.
3. Always distinguish Unicode code points from encoding corruption. Ordinary accents such as á, ñ, ó are expected; check for invalid UTF-8 or U+FFFD/mojibake instead of flagging every U+00C3. One precise scan of changed source files suffices unless evidence shows another problem.
4. Write a short evidence pointer + filename/line and bounded terminal output, not a full 10,000-line log in model context. Keep complete command output in safe local evidence when required, with sanitized report excerpts in Git.
5. Never require the model to reread the long historical harness docs or the entire v2.2 contract on every task. Use MAPA-DE-LECTURA and only task-relevant skill sections.

## Tests, gates and QA
6. During implementation run **targeted tests**/static checks after each material change. Run the approved **full deterministic packet gate** once per final candidate state. The final PHP suite, contract checks, typecheck/build and security tests remain mandatory exactly as declared by the active packet.
7. Where a gate currently runs focused suites and then a full suite, retain both for the **currently approved packet**. For future packets, prefer focused tests during work and *one* full PHP suite at candidate finalization, avoiding a focused-suite replay already covered by that full run **only if** the new packet/gate definitions explicitly permit it. Never silently remove an existing mandatory gate.
8. The independent reviewer must inspect changed/untracked files, attack surfaces and acceptance criteria; it **can reuse successful deterministic gate evidence with identical verified fingerprints** instead of routinely rerunning the same full test suite. It may run targeted adversarial repro tests to challenge evidence. Reviewer never approves its own changes or relies on an unverified handoff.
9. Each correction cycle begins from **the previous QA findings** and the changed-file fingerprints. Review precisely the fixes, their propagation and any newly affected security/behavior paths. Orchestrator still reruns *all mandatory final gates* on the changed candidate and obtains a new independent QA verdict before publication.
10. Publisher compares staged inventory + untracked list + fingerprints with the exact files covered by gates and QA. Rerun only if an actual source/verified evidence mutation occurred; do not rerun the full suite just to restate an unchanged checked SHA. This does not change mandatory exact-SHA CI after push.
11. No infinite retries, no competing subagent review trees, no repeated diagnosis without a new signal. Maximum correction rounds per active packet remains three; stop with concrete BLOCK when a contract/owner decision or tool permission is genuinely missing.
12. Treat time-consuming local commands and model token consumption separately. Do not claim savings until native metered counts or a controlled A/B with explicit limitations exists.

## Instrumentation and privacy
13. For `audit=ON;phase=0`, OpenCode follows **only** `docs/work/features/HOBS-001.md`; do not load the GPT-only future-phase protocol. Use safe native read-only session stats (up to three comparable completed sessions); no sync/CI preflight, collector/parser, agent-written JSONL, extra QA/publisher or commit. First-subagent-request tokens/context growth only if metered; unknowns null.
14. Phase 1/2 are separately approved. Phase 2 temporary audit records stay in ignored local storage outside feature-code fingerprints. **Generate the audit artifact after original feature QA/publisher/exact-SHA CI** and publish as a distinct audit-only documentation commit with its own privacy review and CI. Its own audit-publication events are excluded from the measured window. The final report distinguishes measured coverage from missing phases and never includes raw exports, stdout, .env, secrets, PII or prompts.

## Stage timing
15. At each genuine stage boundary (implementation, verification, QA, correction, publication and CI wait) spend one tiny CLI call: `py -3 scripts/sf_timeline.py begin <ID> <stage> --actor orchestrator ...` and the matching `end ... --status ...`. Data stays in the ignored `docs/work/.run/<ID>.timeline.jsonl`; `status`/`summary` are read-only recovery aids that report **suma** vs **union** without double counting overlap, while wall-clock idle and model inference remain UNKNOWN. Telemetry never authorizes skipping a gate, and a telemetry failure neither blocks nor unblocks approved work.

## Do not optimize away
RBAC and entitlement checks, CSRF/session/bootstrap guards, transaction/locking tests, privacy and security adversarial review, exact-SHA CI, safe Git, separate read-only QA, owner decisions for deployment/remote DB, and completeness of the final release gates.
