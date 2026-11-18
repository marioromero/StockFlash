# HOBS-003 — Retrospective forensic analysis of OpenCode latency (Phase 0C)

status: APPROVED
approval_basis: explicit_owner_complaint_2026-11-18_about_over_1h_processing_and_2026-11-17_20_to_22_Chile
new_business_decisions: NONE
execution_mode: READ_ONLY
publication_mode: NO_GIT
audit_phase: 0C
next: GPT

## Question to answer

Why did work appear to take >1 hour, especially **2026-11-17 20:00–22:00 Chile time (America/Santiago, UTC-03:00)**? Retrospective interval **2026-11-17T23:00:00Z–2026-11-18T01:00:00Z**, with comparison shoulders **19:30–20:00 and 22:00–22:30 local**. First establish whether *confirmed advanced case A* has timestamped activity then. If it does not, state that plainly; do not assign another session to that incident without verifiable safe linkage.

Source records: [HOBS-001 RESULT i=002](https://github.com/marioromero/StockFlash/issues/29) and [HOBS-002 RESULT i=002](https://github.com/marioromero/StockFlash/issues/37). HOBS-002 confirmed case A and context composition, NOT latency causality. Source-only output must be sufficient for an external Claude review; **do not generate or send a Claude prompt or raw history to another provider**.

## Work once, in the separate diagnostic OpenCode session

1. Read this packet and latest directed SFH1 comment only. A bare `ok` means execute the task, not acknowledge it. **Before any tool or Git operation read the task guard**: NO `safe_git_pull.py`, `git fetch/pull`, sync, CI, `/sf-run`, editor/implementer/reviewer/publisher, local script/parser, file creation, plugin, config change or deploy. The only write is the single GitHub SFH1 RESULT. Max **24 read-only local CLI/SQL probes** plus one issue read and one issue RESULT.
2. Reuse previously verified anonymous case A session link and SQLite metadata schema; do not explore message contents, raw IDs, prompts, reasoning, tool inputs/outputs, command strings, paths, secrets or titles. Open SQLite in *true* read-only mode with `PRAGMA query_only=ON`, no `immutable=1`, sidecar creation, copy, attach, workaround or write. Use explicit safe known numeric/timestamp/categorical `json_extract` paths, fixed SQL output columns and bounded queries. Skip if access safety is uncertain.
3. Convert stored epoch milliseconds to local timestamps using **America/Santiago offset -03:00 on 2026-11-17**. Verify message creation/completion timestamps and tool-part `state.time.start/end` presence in this installed OpenCode 1.19.5 metadata schema. Treat a message's `created→completed` as an **assistant-message span**, NOT automatically provider inference; tool span as tool-active time only when status/completion fields are reliable. Missing timestamps => `not_exposed`, never zero.
4. Query the **two-hour incident** plus shoulders: present bounded **15-minute bins** with parent assistant start/completion counts, child/subagent starts/completions, tool starts/completions/errors and broad tool family, observed tool elapsed minutes, and gaps with no recorded event. Label user message/idle intervals when detectable by timestamps without content. No cumulative sums across simultaneous children as elapsed critical path.
5. Extract at most **8 longest potentially user-visible waits** overlapping 20:00–22:00 local: each with local start/end, duration minutes, anonymous agent role (parent/child), event category (assistant span/tool/child/empty gap), result status and `covered_by_observed_tool|overlap|no_explanation`. Identify when an assistant request did not complete vs tools waiting vs true event-free intervals. If only session lifecycle wall time is available, do not mislabel it as model processing.
6. Determine evidence for likely bottleneck **by classification only when observed**: slow shell/test/build, repeated error/retry/status loops (without argument contents), long serial subagent dependency, overlapping child tasks, assistant spans unexplained by tools, periods with no event (could be owner away), or unknown provider/queue/network/model inference. Output a ranked **EVIDENCE / HYPOTHESIS / NOT_EXPOSED** distinction, not invented root causes. Do not identify same-input duplicate tool calls without safe fingerprints.
7. If local metadata cannot explain a >60-minute reported wait, explicitly say `LATENCY_CAUSE_NOT_RECOVERABLE_FROM_HISTORY` and recommend **only the minimum prospective observation** necessary on a naturally authorized future development action (model request t0/t1, tool t0/t1, child linkage, status, no content). Do not implement observer or run synthetic features in this phase.

## One SFH1 result

Exactly one concise English `SFH1|src=OC|dst=GPT|kind=RESULT|feature=HOBS-003|phase=0C` in the active issue, max **55 lines**. Include exact window, whether case A was active, event-coverage matrix, 15-minute bins, top observed waits, attribution confidence, evidence vs hypotheses and unobservable causes, plus recommendation to GPT for whether a tiny prospective timing collector is justified.

The only allowed data in GitHub are aggregated counts/durations, masked categories, rounded local timestamps, and anonymized labels. No raw transcript/source, tools' arguments, message IDs/titles, command strings or PII. Report any guard breach explicitly. End `scope=READ_ONLY;files=UNCHANGED;no_git_sync;no_parser;next=GPT`. If no safe metadata or GitHub comment access, report a concrete BLOCK instead of an empty OK. Do not inject diagnostics into the owner's advanced coding session.
