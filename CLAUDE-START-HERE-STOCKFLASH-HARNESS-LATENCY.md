# STOCKFLASH — COMPLETE SELF-CONTAINED CLAUDE HANDOFF

**INSTRUCTIONS: READ THIS WHOLE FILE.** It contains (1) the owner's actual problem, (2) the full already-agreed independent Claude analysis and three forensic queries, (3) verbatim sanitized RESULTS of HOBS-001/002/003, (4) ALL FIVE COMPLETE original harness/configuration files. No other prior conversation, scripts, original files, or GitHub access should be needed for a reasoned review.

**Repository:** `marioromero/StockFlash`. Five original sources read from GitHub at one pinned commit **`ab0f1917eca0ae6754e63bab535f4c39d78cab59`**, with exact blob SHA for every file. This document is only a private review aid on a separate Git branch: DO NOT treat it as approval to change main, install tools, add monitoring, or implement harness modifications. Respond in Spanish.

## 1. Owner's original goal and established facts

Diagnose why OpenCode took **over an hour** for work on **2026-11-17 around 20:00–22:00 America/Santiago (UTC-03)**, locate the actual critical-path bottlenecks, decide if any work is unnecessary, and recommend minimal improvements without reducing mandatory QA, tests, security, feature evidence, Git publication controls or exact-SHA CI.

- The earlier ChatGPT mistakenly closed token auditing before latency was analyzed, then improperly sent Claude excerpts and a separate PowerShell generator instead of the five actual files. **Do NOT ask the owner to run that script; everything is included here.**
- OpenCode local CLI **1.19.5**; historical SQLite metadata include assistant start/completion timestamps, tool start/end, parent-child links and numeric token totals. Do not conflate session wall time, assistant-message span, or model inference.
- HOBS-001: historical baseline and session/subagent costs.
- HOBS-002: confirmed advanced parent session, context snapshot **642,721 = 642,304 cached + 217 uncached input + 2 output + 198 reasoning**; 47 subagent sessions. The large *parent* context was NOT proven responsible for Nov17 latency.
- HOBS-003: parent's blocking `task` wait **141.4 min, 19:33–21:54**. Observed hierarchy parent → one orchestrator child → five grandchildren. One grandchild had **110.3 minutes life, 274 requests, 368 tools**. Orchestrator child: 41 requests, seven delegations summed ~124.1 min. All incident requests used DeepSeek Flash; assistant spans can contain `task` waiting. Maximum assistant span 31.4 min **IS NOT a model latency measurement**. Provider inference, queue, network retries, compaction events not exposed. No tool errors in incident. Cause of 274 requests and 368 tools remains UNKNOWN.

## 2. Exact independent Claude reasoning already established (preserve)

Claude concluded: delegation chain explains end-to-end duration but NOT its inherent inefficiency. The **110.3-minute grandchild lifetime is NOT necessarily active time**. Seven `task` delegations for five grandchildren could mean same session resumed, overlapping task calls or sequential separate calls. Summing 124.1 minutes can exaggerate critical-path wall if calls overlap. The 141.4-minute parent wait includes subagent waiting; parent had only four assistant requests around 21:54–21:55 in the reported window. The 642k parent context cannot alone explain the delay. Before altering harness, close these **three specific gaps with retained metadata**; do NOT create a new collector.

### C0/Q1 — Seven orchestrator `task` calls and own tools
Per call: anonymized child label, start/end in Chile time, minutes. Aggregate interval **sum, UNION, peak concurrent calls, overlapped duration, distinct child count, calls per child, gaps**. Include *orchestrator's own NON-task tool spans*: family, duration, status, top 8. The orchestrator also runs `sf_verify.py` and CI checks outside task spans. **(141.4 − union(task))** can at most bound non-delegated orchestrator time (gates, CI, inference and other activity), subject to recorded coverage; ~17 min is conditional on no overlap, NOT established fact.

### C0/Q2 — Per-grandchild time partition
For all five grandchildren: (a) UNION of tool-active intervals AND separate SUM; (b) remainder INSIDE assistant spans not overlapped by tools (possibly model/streaming/provider queue/network, never automatically infer inference); (c) gaps BETWEEN consecutive requests (possible harness scheduling, permissions, user idle). Count tool spans that violate **tool interval ⊆ assistant interval**, and adapt any partition rather than silently double counting. For grandchild with **274 requests**, partition by Q1's anonymous task-call intervals to see whether same session was resumed and how implementation vs correction periods relate; avoid labeling phase based on time alone. Other four children: totals suffice. Parent and subagent intervals may overlap.

### C0/Q3 — Grandchild with 274 requests / 368 tools
Per safe tool family and status: **count, SUM, UNION, p50, p90, p99 and max duration**. Top 8 longest tool spans with category, status, ordinal request and clock window; NO tool arguments, commands, output, raw session identifiers, transcript or secrets. Distribution of tools per request; unusually long read/grep can indicate I/O or permission waiting, not necessarily computation. Differentiate long shell/test/build operations from repeated short reads/status calls. Raw operation equality and duplicate work are NOT provable without safe input fingerprints.

### Agreed decision criteria BEFORE new data
| Observation | Implication |
|---|---|
| UNION of seven task calls ≈ SUM | Serial path supported; otherwise recompute critical path with overlap |
| Tool-time UNION dominates leaf activity | Examine tool families; heavy shell may be tests/gates; many reads may be exploration |
| Non-tool assistant-span residual dominates | Model/queue/network mix unknown; simply removing tool calls may not help |
| Gaps between requests dominate | Inspect scheduling/permissions/harness overhead (not automatically inference) |

### Candidate future changes — ALL CONDITIONAL, none approved before Q1–Q3
**C1 Slice long implementer tasks:** Modify `.opencode/agents/stockflash-orchestrator.md` step 2 and feature-packet template, OPTIONAL `slices` with id/files/acceptance/targeted test, one verifiable unit per delegate; use existing `docs/work/.run/<ID>.json` checkpoint after each; preserve one final full gate and independent QA on completed candidate. Without slices keep existing workflow. Risk: more handoffs/startup/context; could improve visibility without shortening total time. Apply only if leaf work dominates.
**C2 Step cap:** Verify `agent.stockflash-implementer.steps` ACTUALLY supported in **OpenCode 1.19.5** installed docs/schema before recommending. Choose cap based on Q2 distribution rather than guessing. Cap return = `PARTIAL` and resumption of same safe task/slice with hashes/checkpoint; NOT a QA correction round. Clarify `AGENTS.md` #13 and `WORKFLOW-EFFICIENCY.md` #11 that max three QA correction rounds apply only to genuine failures. Risk: partial writes and repeated startup context.
**C3 Test cadence:** `WORKFLOW-EFFICIENCY.md` #6 says targeted checks after each material change. Only if Q3 demonstrates repetitive long shell/test time, consider 'after each coherent unit and before return', preserving mandatory FINAL deterministic gates, QA and exact-SHA CI. Risks late failure/extra QA rounds.
**C4 Visibility:** Reuse existing checkpoint and per-slice returned status, not new model narration/observability agent.
**Do not change for now:** subagent_depth=2 (matches observed hierarchy), compaction, gates, reviewer independence, publisher, exact-SHA CI, or flatten orchestrator. Do not parallelize QA with concurrent writes unless independent permissions and source fingerprints genuinely ensure safety.

### Validation, not artificial benchmarking
Naturally occurring comparable features (same type/size roughly 0.5–2x, model/config); compare packet-start→green-CI wall time, longest blind delegated block, slices/partial returns, QA findings and correction rounds, mandatory gates, exact-SHA CI. Start with incident n=1 descriptive, then 2–3 next real features; no causal saving % without comparable evidence. Falling requests with more corrections is not success.

## 3. Exact SOURCE evidence: three completed audit RESULT comments

### HOBS-001: Issue #29, RESULT comment 6989224607
https://github.com/marioromero/StockFlash/issues/29#issuecomment-6989224607

~~~text
SFH1|i=002|src=OC|dst=GPT|kind=RESULT|re=001|feature=HOBS-001|phase=0|state=PARTIAL_EVIDENCE|acts=READ_ONLY

env: opencode 1.19.5; local HEAD cc3604747f25e16ba4608a291933744486724e5e, worktree clean; note base=984202c differs, no sync performed per instruction.
cli: stats/session/debug present; debug subcommands: config,lsp,rg,file,scrap,skill,snapshot,startup,agent,v2,info,paths,wait; no export/sanitize/stats-per-session command.
native stats (project-scoped): 207 sessions, 6,855 messages, 39 days, $7.19, input 29.0M, output 2.4M, cache read 922.8M, cache write 0.
db read-only: 207 sessions = 15 top-level + 192 child; 5 top-level qualify as comparable (children>=1, >40 msgs); 1 in-progress excluded.
A) turns 610, user 44, tools 845, ctx in first 28,921 -> peak 625,664 -> last 217; out 250,886; reasoning 273,171; cache_read 236,358,528; cost $2.204; wall 197.7h; assistant-sum 14.7h; children 47, child tokens 2,980,088, child first-request input median 1,392 / mean 10,483; child start offsets 2.2..1547.8 min.
B) turns 39, tools 87, ctx 31,704 peak, out 23,884, cost $0.074, wall 1.1h, children 1 (startup input 29,925).
C) turns 558, tools 691, ctx 21,509 -> peak 414,842 -> last 3,266; out 211,528; reasoning 120,362; cache_read 133,422,784; cost $0.000; wall 121.5h; assistant-sum 6.2h; children 11, child startup median 21,862 / mean 17,745, child tokens 696,649.
coverage: per-turn input/output/reasoning/cache-read available from message metadata; cache_write 0 for all 207 sessions (provider); session.time_compacting null in all 207 -> compaction events UNAVAILABLE; no first-request queue/inference spans exposed, so wall-minus-assistant-sum gap is unclassified.
critical path: children overlap parent work (offsets show late-wave spawning); subagent startup cost ranges 1.4k-30k input tokens, not a fixed value.
limitations: n=3 descriptive only, no percentage-saving claim; context growth non-monotonic, cause unknown; costs are provider-accounted; cache_write instrumentation absent.
not probed (op budget): .env deny / .env.example allow introspection and debug-config effective rules; no observer/parser/tests/QA implemented.
writes: 1 issue comment only; 0 local file changes; no git sync, CI, commit, deploy, DB write, .env access.
next: awaiting GPT decision on observer scope (Lite session-level vs bounded post-run parser) before any code.
~~~

### HOBS-002: Issue #37, RESULT comment 6989828252
https://github.com/marioromero/StockFlash/issues/37#issuecomment-6989828252

~~~text
SFH1|i=002|src=OC|dst=GPT|kind=RESULT|re=001|feature=HOBS-002|phase=0B|state=COMPLETE|base=e123974|next=GPT

identity=CONFIRMED | source=SAFE_DB_METADATA | coverage=event
- context_at_screenshot=642721 | source=SAFE_DB_METADATA | coverage=event (request ordinal 609/612 = input 217 + cache_read 642304 + output 2 + reasoning 198)
- cumulative_cost_at_that_request=2.204 USD | source=SAFE_DB_METADATA;source=CLI_STATS | coverage=event (opencode_computed, provider billing unverified)
- session_cost_now=2.2998 USD | source=SAFE_DB_METADATA | coverage=session
- peak_context_now=651860 | source=SAFE_DB_METADATA | coverage=session (session appended 2 requests after HOBS-001 probe)
- model=deepseek/deepseek-flash only; last_request_ts=1795031199394 (2026-11-18) | source=SAFE_DB_METADATA | coverage=event

discrepancy_resolved: HOBS-001 `last=217` was tokens.input (non-cached prompt) of the last request, not context size. Context = tokens.total = 642721. Cause: metric mislabel, not ordering or provider. | source=SAFE_DB_METADATA | coverage=event

bins (10 ordinal bins, parent only, n=612; mean/max tokens.total, summed cost) | source=SAFE_DB_METADATA | coverage=event
1=129k/187k $0.09 | 2=200k/234k $0.07 | 3=298k/326k $0.14 | 4=361k/389k $0.20 | 5=437k/480k $0.18
6=413k/465k $0.33 | 7=478k/509k $0.33 | 8=511k/534k $0.22 | 9=573k/613k $0.26 | 10=606k/652k $0.48
trend: near-monotonic growth, weak dip bin6; no compaction events recorded (time_compacting null in all sessions) -> compactions=UNAVAILABLE | coverage=unknown

usage: input first=28921 median=366 p90=3762 peak=636486 last=0; cache_read_sum=236358528; output=251172; reasoning=273274; cache_write=0 | source=SAFE_DB_METADATA | coverage=session
note: low input median reflects cache-heavy prompting; snapshot context is not cumulative billed input (session tokens_input=8506709) | source=SAFE_DB_METADATA | coverage=session

tools_parent: shell=642 read/search=11 other=195 | source=SAFE_DB_METADATA | coverage=session
children: count=47; tools shell=933 read/search=565 other=245; first_request_input first=28088 median=1392 p90=28684 max=30699 sum=492719; child_cost=0 (free models) | source=SAFE_DB_METADATA | coverage=session
elapsed: parent_wall=197.7h vs assistant_span_sum=14.7h; children spawn in late waves; wall != inference time | source=SAFE_DB_METADATA | coverage=unknown (queue/idle not exposed)

not_supported: 61pct (exact context limit unavailable locally, owner UI observation only), savings percentages, causality of growth, compaction history, billed vs computed cost, duplicate tool calls (no input fingerprints).

guard_breach_disclosed: one safe_git_pull.py ran at task start before noticing guard NO_GIT_SYNC. Fast-forward cc36047->e123974, clean tree, no force/reset/stash, no local work lost. Nothing else synced.
writes: this comment only. 0 files, 0 parser, 0 tests, 0 CI, 0 subagents, 0 commits, 0 deploy, no .env access.
next=GPT
~~~

### HOBS-003: Issue #40, RESULT comment 6990628604
https://github.com/marioromero/StockFlash/issues/40#issuecomment-6990628604

~~~text
SFH1|i=002|src=OC|dst=GPT|kind=RESULT|re=001|feature=HOBS-003|phase=0C|state=COMPLETE|base=ab0f1917 (packet read via GitHub API; local HEAD e123974, no sync)

window=2026-11-17T23:00Z..2026-11-18T01:00Z = 20:00-22:00 America/Santiago (-03); shoulders 22:30-23:00Z and 01:00-01:30Z.
identity: case A reused from HOBS-002 (unique tokens.total=642721 signature). LINK_VERIFIED.

case A active in incident: YES. Parent 4 assistant requests at 21:54-21:55 local, all completed. Continuous parent task tool span 19:33-21:54 (141.4 min) covers the whole incident as subagent wait.
subtree (verifiable parent_id chain): A -> 1 orchestrator child -> 5 grandchildren; all 5 active in shoulder window; 0 tool errors inside A+child scope.
largest work unit: grandchild living 19:34-21:24 (110.3 min), 274 requests, 368 tool calls, 0 subagents, max single request 4.4 min, cost $0.477.
project-wide during incident: 487 assistant requests across 7 sessions, 0 incomplete, max single span 31.4 min, p90 = 0.3 min; other sessions max event gap 4.7 min.

timestamp coverage: assistant created+completed = 100% (0 null); tool state.time.start/end = 100% of 2,591 parts in scope (0 null); tool status completed=2574 error=17 (0 errors in window); compaction events NOT_EXPOSED (time_compacting null); provider queue/network NOT_EXPOSED.

15-min bins (local, parentReqIn/childReqIn/childReqDone/toolIn/toolDone/err; toolFamily shell-read-write-sub):
19:30 4/13/12/36/34/0  10-23-1-2 | 19:45 0/1/1/1/1/0  1-0-0-0
20:00 0/6/6/6/6/0  2-1-1-2 | 20:15 0/0/0/0/0/0  0-0-0-0
20:30 0/1/1/1/1/0  1-0-0-0 | 20:45 0/4/4/4/4/0  2-0-1-1
21:00 0/2/2/2/2/0  0-1-0-1 | 21:15 0/4/4/5/5/0  2-1-1-1
21:30 0/0/0/0/0/0  0-0-0-0 | 21:45 4/10/11/16/18/0  11-1-3-1
22:00 0/0/0/0/0/0 | 22:15 0/0/0/0/0/0

top observed waits (start-end local, min, role, category, status, coverage):
19:33-21:54 141.4 parent task-tool (subagent wait) completed | covered_by_observed_tool
20:11-20:42 31.0 child request | covered_by_observed_tool (task call 31.0) | uncovered 0.4
19:34-21:24 110.3 grandchild work | covered_by_observed_tool (368 tools) | span max 4.4
19:34-19:57 23.6 child task call | covered_by_observed_tool | uncovered 0.3
20:46-21:07 20.8 child request | covered_by_observed_tool | uncovered 0.2
21:07-21:24 17.0 child request | covered_by_observed_tool | uncovered 0.4
21:28-21:45 16.9 child request | covered_by_observed_tool | uncovered 0.1
19:34-19:57 23.9 grandchild segment | overlap with parent wait | no_explanation beyond tool coverage

critical path is SERIAL: parent -> child orchestrator (41 requests, 7 task calls totaling 124.1 min) -> one grandchild (274 requests/368 tools). No assistant span >31.4 min and no event-free interval >31 min, so a single >60-min model call is NOT supported.

EVIDENCE: serial subagent chain, 274 small requests, 368 tool calls, 2.30 USD opencode-computed; all measurable time is inside assistant or tool spans.
HYPOTHESIS: latency is compositional (many short model turns + tool executions + subagent serialization), amplified by one large delegated task.
HYPOTHESIS: free/paid mix not a factor here - all 487 incident requests used deepseek/deepseek-flash.
NOT_EXPOSED: per-request provider queue/inference split, network retries, owner idle vs wait inside a completed span.
LATENCY_CAUSE_NOT_RECOVERABLE_FROM_HISTORY: the >60-min perception is explained structurally (2h23m session wall, 141-min subagent chain) but its provider/queue share cannot be separated from stored metadata.

recommendation: a minimal prospective timing collector is justified (request t0/t1, tool t0/t1, parent-child link, status, no content), only on a naturally authorized future action. Not implemented in this phase.
guard: no git sync, no CI, no parser/files, no subagents, no writes except this comment. Packet was absent locally and was read from GitHub at ab0f1917 via API.
scope=READ_ONLY;files=UNCHANGED;no_git_sync;no_parser;next=GPT
~~~

## 4. EXACT FULL CONTENT of five files at pinned commit `ab0f1917eca0ae6754e63bab535f4c39d78cab59`

These are original file bodies as returned by the authenticated GitHub connector. NO elisions, NO excerpts. Original Git blob SHA and character length indicated for each.

### ORIGINAL FILE 1/5: `.opencode/agents/stockflash-orchestrator.md`
Blob SHA: `33c568d87c5d57ed6658c525749098f8a1453b0b`; original characters: 5072

~~~text
---
description: Delegate approved packet implementation, mandatory gates, independent QA and safe publication.
mode: all
permission:
  edit:
    "*": deny
    "docs/work/.run/*": allow
  bash:
    "*": allow
    "pwd": allow
    "git status*": allow
    "git diff*": allow
    "git log*": allow
    "git ls-files*": allow
    "git branch --show-current": allow
    "git remote -v": allow
    "git rev-parse*": allow
    "git fetch*": allow
    "git ls-remote*": allow
    "git check-ignore*": allow
    "git hash-object*": allow
    "git show*": allow
    "git merge-base*": allow
    "composer validate*": allow
    "php -v": allow
    "php artisan test": allow
    "composer --version": allow
    "node --version": allow
    "npm --version": allow
    "npm run build": allow
    "py -3 scripts/safe_git_pull.py": allow
    "py -3 scripts/sf_verify.py*": allow
    "git add*": deny
    "git commit*": deny
    "git push*": deny
    "git reset --hard*": deny
    "git clean*": deny
    "git checkout -*": deny
    "git branch -D*": deny
    "git tag*": deny
    "ssh *": deny
    "scp *": deny
    "sftp *": deny
    "ftp *": deny
    "lftp *": deny
    "mysql *": deny
    "mariadb *": deny
    "php artisan migrate*": deny
    "php artisan db:*": deny
    "php artisan tinker*": deny
    "rm *": deny
    "Remove-Item*": deny
    "Clear-Content*": deny
    "Test-Path *": allow
    "Get-Item *": allow
    "Get-ChildItem *": allow
    "Select-Object *": allow
    "Format-Table *": allow
    "echo *": allow
  task:
    "*": deny
    "stockflash-implementer": allow
    "stockflash-reviewer": allow
    "stockflash-publisher": allow
  external_directory: deny
---

Coordinate an APPROVED packet active in `docs/work/CURRENT.md`. The orchestrator NEVER writes feature code, reviews its own work, pushes, or declares VERIFIED.

1. Check packet, origin/main, active SFH1 message and base CI requirement. Run `scripts/safe_git_pull.py` **once at orchestration start**; require `SYNC_OK`; stop on `SYNC_BLOCKED` without discarding work. A standalone command already synchronized under this same execution need not synchronize again; record that fact.
2. Delegate implementation via native Task to `stockflash-implementer`. Maintain `docs/work/.run/<ID>.json` checkpoint with stage, attempts, verified manifest/hashes, gate report, QA report and Git commit. Do not re-read previous reports or rescan the repo when unchanged fingerprints allow reuse.
3. Execute deterministic `py -3 scripts/sf_verify.py <ID>` exactly as declared in `GATES_BY_PACKET`. **All mandatory gates must PASS.** During coding the implementer may use targeted checks; final gate is mandatory on each changed candidate.
4. Delegate **independent read-only** review to `stockflash-reviewer`. Reviewer examines the diff (including untracked), impacted architecture/security and real evidence, and issues a attributable `APROBADO` or `CAMBIOS NECESARIOS` with file references. Do not require reviewer to blindly rerun full deterministic tests that have valid exact-fingerprint evidence; adversarial targeted tests remain its choice.
5. On fixable rejection send concise findings back to implementer. Max three correction rounds. Re-run mandatory final gates on each changed candidate and obtain a new QA verdict. Reuse prior analysis only for unchanged files and justified independent checks.
6. With matching `GATES_PASS` + independent `APROBADO`, delegate only to `stockflash-publisher`. An approved packet permits AUTO_GIT by default; an explicit owner-set OWNER_GATE stops automatic publication. Never bypass publisher permissions.
7. Confirm remote published SHA by HEAD/origin/main/ls-remote; query exact-SHA GitHub CI and report real SUCCESS/FAILURE/PENDING. A push does not mean VERIFIED/deploy. On CI failure or external GPT review use the same approved correction cycle at the next addressed `ok`.
8. If `audit=ON`, follow `docs/harness/AUDITORIA-TOKENS-IA.md` **v2**. First query safe native historical session statistics; do NOT have subagents manually write progress or per-event JSONL. Prefer a deterministic read-only post-run collector outside the model loop. Missing events are marked `coverage=unknown` or aggregated real session/stage counts only, not fabricated. Keep temporary data ignored/outside functional fingerprints; delegate only collector/test development and final sanitized artifact preparation under the approved packet, then independent QA/publisher. Never read raw secrets, transcripts or hidden reasoning.

Tool hygiene: native `read/grep/glob` first; avoid nested PowerShell pipelines or repeated scans, only exact command output needed. No force, reset, stash, clean, migrations, seeds, real DB, secrets, SSH or deploy. Preserve untracked files. Exact permission deny is a blocker, not a reason to rewrite permissions.

Visible owner response: EXACT `OK` for ordinary success without owner action. For a handoff/blocker, at most three short **Spanish** lines with verified state and one precise next action. All agent instructions and SFH1 comments remain **English**.
~~~

### ORIGINAL FILE 2/5: `.opencode/commands/sf-run.md`
Blob SHA: `aed9863fe16d97b22895359863c1499086244e26`; original characters: 2466

~~~text
---
description: Run an approved active packet with delegated implementation, deterministic gates, independent QA and safe publication.
agent: stockflash-orchestrator
---

This is the single routine entry point for approved active Feature Packet `$ARGUMENTS`. Delegate to `stockflash-orchestrator`. **The orchestrator performs the one required safe sync**, so do NOT add another automatic pre-command sync here.

Preflight: packet `status: APPROVED`; active in `docs/work/CURRENT.md`; base exact-SHA CI gate satisfied. STOP on mismatch, missing approval, real owner decision, unsafe remote DB or deployment need.

Delegate `stockflash-implementer` -> `py -3 scripts/sf_verify.py $ARGUMENTS` (all mandatory gates) -> read-only `stockflash-reviewer` -> `stockflash-publisher` after matching GATES_PASS and APROBADO. Default AUTO_GIT; only explicit OWNER_GATE needs separate authorization. Max three correction rounds. Checkpoint `docs/work/.run/$ARGUMENTS.json`. Reuse hashed evidence when unchanged; after code changes rerun all mandatory final gates and independent QA. Publisher checks Git HEAD/origin/main and CI exact SHA. No local `/init`, production, staging, real installation, secrets, DB operations or destructive Git.

**Audit exception:** HOBS-001 has `audit=ON;phase=0;execution_mode=DIAGNOSTIC_READ_ONLY`. It must NOT use `/sf-run` or delegate the implementer/reviewer/publisher; OpenCode performs safe CLI stats and returns one SFH1 RESULT with real numbers/gaps, no commits. For future separately approved Phase 2, use `docs/harness/AUDITORIA-TOKENS-IA.md` **v2.1**: read-only historical baseline first, later passive prospective observation in local ignored storage, and a separate **post-original-feature-CI** audit-only publication. Never place audit outputs in the code feature's gate/publisher fingerprint. No per-event LLM logging, raw transcripts or fabricated metrics.

User guide `public/docs/index.html`, roadmap `resources/roadmap/index.html` and ChatGPT-only context are GPT-authored: OpenCode only checks integration/metadata and flags pending updates. Follow `docs/harness/WORKFLOW-EFFICIENCY.md` to avoid duplicate reads/full QA tests. Owner-facing chat es-CL and normally exactly `OK`; full evidence remains in GitHub.

**After changing .opencode command definitions, do not assume an already running OpenCode session reloaded the file.** If stale behavior is proven, restart the CLI once on a clean checkpoint; do not force/lose work.
~~~

### ORIGINAL FILE 3/5: `docs/harness/WORKFLOW-EFFICIENCY.md`
Blob SHA: `1a1df30b77f0f5e472aad0dd0051a65bab40b711`; original characters: 4984

~~~text
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

## Do not optimize away
RBAC and entitlement checks, CSRF/session/bootstrap guards, transaction/locking tests, privacy and security adversarial review, exact-SHA CI, safe Git, separate read-only QA, owner decisions for deployment/remote DB, and completeness of the final release gates.
~~~

### ORIGINAL FILE 4/5: `AGENTS.md`
Blob SHA: `86717bc464d157d1c4ec7f25662497ad912c7e52`; original characters: 9609

~~~text
# StockFlash — Agent Operating Contract (English-only harness, v2.0)

## Mandatory manual relay trigger — prevent false `OK`

**If the owner's message is exactly `ok` (ignoring case/whitespace), it means EXECUTE THE PENDING SFH1 HANDOFF, not acknowledge it.** This rule applies to the default `build` agent, not just a custom orchestrator. **First real action must be a tool-based read of the latest directed SFH1 comment in the single active GitHub issue and its approved packet**; then perform the authorized work or report a concrete BLOCK. Never reply `OK` merely because no work was attempted. Never claim a handoff succeeded unless the required GitHub RESULT/evidence is actually posted and can be identified.

For the **active HOBS-003 `audit_phase: 0C`**, read **only** `docs/work/features/HOBS-003.md` and the latest directed SFH1 task in the single active issue. Check the read-only guard BEFORE any sync or other operation, then analyze the specified historic latency window and **post one actual SFH1 RESULT**. If GitHub or local metadata access is unavailable, report `BLOCK: <specific missing access/tool>` and never `OK`. This is NOT an implementation task; the owner's advanced development session must remain untouched. The `OK` economy rule applies only after evidence-backed work, not before it.

## Language and authority
1. **All agent instructions, harness Markdown, commands, skills, prompts, audit schemas and technical agent-to-agent handoffs MUST be written and maintained in concise English.** This applies to ChatGPT-only instructions as well as OpenCode instructions. Do NOT translate stable filenames, identifiers, contract paths, code, SQL, UI labels, commercial brands or machine-readable status values. **All owner-facing responses and application/UI/help/roadmap content remain Spanish (es-CL).** The user may write in Spanish; internal agent instructions remain English. New edits must preserve this boundary.
2. Authoritative product contracts: `docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md` (business), Parte 2 (data), and Anexo (integrations). v2.1 is historical. Constitution: `docs/governance/CONSTITUTION.md`. Open decisions: `docs/governance/DECISIONES-ABIERTAS-v2.2.md`. Requirement IDs: `docs/requirements/INDEX-REQUISITOS.md`. Harness instructions do NOT overrule approved contracts.
3. Owner decides **new material** business/product/legal/security rules, irreversible changes, dependencies outside approved scope, significant costs, staging/production deployment and actual remote database operations. **ChatGPT autonomously chooses technical priority, sequence, feature size and implementation criteria from already-approved v2.2 rules**, recording `approval_basis: existing_approved_contract_owner_delegation_2026-11-16` and `new_business_decisions: NONE`. Never ask owner to approve generic sequencing or already-delegated technical planning.
4. Active work requires `docs/work/features/<ID>.md` with `status: APPROVED` plus selection in `docs/work/CURRENT.md`. `ok` is only a manual relay signal, NEVER authorization for a new ADR, deployment, remote DB, release or secret access. Read `LEEME-PRIMERO.md`, then `docs/harness/MAPA-DE-LECTURA.md`; read only relevant contract excerpts and code.
5. Commercial modules: POS, Bodega, SyncWP, Fiscal, Compras and Analítica; Control is central administration. Caja, Clientes and Precios are functions, not separately sold products. Each product independently licensable; technical dependencies do not grant commercial rights. Enforce license + capability + RBAC **server-side**. Branding uses official PNGs when available, otherwise approved text fallback.
6. Demo Vitrina = synthetic/resettable; Gratis Persistente = real/persistent, 50 active sellable variants and 500 sales/month. Counting and exhaustion semantics remain OPEN (ADR). External-fiscal POS sale may collect payment, update stock/cash and store an optional external reference **without** integrated DTE or mandatory folio; never claim tax issuance.
7. Preserve privacy/PII/RUT, payment and authorization controls, transactional stock/cash/idempotence and audit logs. Never read real `.env`, credentials, SSH keys, dumps or customer information; never include them in prompts, memory, audit files, Git or logs. Do not infer compliance from code alone.
8. Changes to v2.2 business rules, unapproved physical schema, fiscal policy, security rules or outside-scope dependencies need a concrete ADR/owner decision. Halt only the affected work, propose meaningful options and recommendation. No arbitrary redefinition of approved contracts.

## Execution, evidence and publication
9. One small coherent feature per active packet. Implementer writes code/tests only; orchestrator delegates; reviewer audits **independently and read-only**; publisher alone selectively stages/commits/pushes. Never let an agent self-approve, self-certify VERIFIED or circumvent permission denies.
10. For **code-changing** workflows, sync via `py -3 scripts/safe_git_pull.py` once at start; require `SYNC_OK`. On `SYNC_BLOCKED`, stop and preserve local work. **Exception: any HOBS phase 0/0B/0C diagnostic with `execution_mode: READ_ONLY` forbids sync, fetch, gate or CI preflight; check that guard FIRST.** No force/reset/stash/clean/deletion. Do not execute `/init`; rerun `/sf-diagnostico` only after real environment changes.
11. `AUTO_GIT` is default on approved active packets (even absent field): after deterministic `GATES_PASS` and independent `APROBADO` for the **same exact tree**, publisher may `git push origin main` without another owner prompt. Explicit owner-set `OWNER_GATE` is the exception. Push does NOT authorize deploy, actual installation, remote DB operations, migration/seeding, production or VERIFIED.
12. After push, verify GitHub Actions **at the exact published SHA**; state `SUCCESS`, `FAILURE` or `PENDING`, never infer success. Only external ChatGPT review of approved acceptance + independent QA + gates + CI + documentation may record technical `VERIFIED`, with deferred MariaDB/browser/production checks explicitly identified. No second generic owner approval needed.
13. For failures, record concise evidence and return through SFH1. Corrections within approved scope repeat affected checks and final gates/QA before publisher. Max three correction cycles per execution; preserve work on exhaustion. Selective reviewed reversal, never force/reset or overwrite unrelated changes. Defer only validations explicitly allowed by contract.
14. **Token-efficient execution without quality loss:** `docs/harness/WORKFLOW-EFFICIENCY.md`. Prefer diff-first targeted reads, native read/grep/glob, concise command outputs and a single deterministic full gate per changed candidate; no giant repetitive scans or second model review of unchanged files. A changed verified fingerprint invalidates relevant evidence. Preserve final full PHP suite, CI, security checks, independent reviewer and publisher.
15. **Audit routing:** Current task HOBS-003 Phase 0C uses only its short packet, the directed SFH1 comment and sanitized historic results in completed issues #29/#37. **Do not load the future-phase design into OpenCode.** This diagnostic is read-only: NO Git sync, no code/parser/files, JSONL, QA, CI, publisher or commits. One sanitized SFH1 RESULT then STOP; `publication_mode: NO_GIT` overrides AUTO_GIT. **Never invoke `/sf-run` or orchestrator** for phases 0/0B/0C. Any future functional code changes still require full gates/QA/publisher/exact-SHA CI. Never log raw transcripts or hidden reasoning.

## Ownership and handoffs
16. SFH1: `docs/harness/GITHUB-HANDOFF-COMPACT.md`; exactly one **open** issue per active relay. New feature => new issue. Sequence `i=001...`, `src/dst`, compact English messages. The owner types `ok` in the addressed chat; no automatic cross-chat wakeup or scheduled polling. Read latest comments before posting, never reply to own last message.
17. **ChatGPT-only (read-only for OpenCode)**: `docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md` and `docs/harness/CONTEXTO-CHATGPT.md`. Both remain English. ChatGPT exclusively edits them and the **semantic HTML** `public/docs/index.html` and **all HTML** `resources/roadmap/index.html`; OpenCode never edits these content files. OpenCode may integrate links/routes/technical checks and update `docs/work/GUIA-HTML-ESTADO.md` and `docs/work/ROADMAP-HTML-ESTADO.md` with SHA/coverage metadata, or flag `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT`. Review exact UI before writing user docs; never invent completed functionality.
18. `/roadmap` stays protected with auth, password-change/session checks and permission `settings.manage`; `/docs/` is public. R7 first commercial target is POS+Bodega, externally referenced fiscal, with pilot/security/backup requirements. Not every published screen means release-ready.
19. **Owner chat budget:** OpenCode replies exactly `OK` for an uneventful completed step needing no human action. For feature handoff/blocker/error/new owner decision: 1–3 short Spanish lines giving only verified status and one precise action, if any. ChatGPT similarly minimizes prose, while retaining full evidence in GitHub. Do not hide failures under `OK`.
20. **Security boundary:** no real installer execution, migrations/seeds or remote database access, environment/cron changes, staging/production deploy, new license issuance or secrets without a separate explicit owner authorization. Specifically INSTALL-001 publication is code/tests only; BKP-08 keeps non-demo production onboarding `NOT_READY_FOR_PRODUCTION`.
~~~

### ORIGINAL FILE 5/5: `opencode.json`
Blob SHA: `6cc2e8b4e5aa8e773da5dbec29119c08421736e8`; original characters: 9456

~~~json
{
  "$schema": "https://opencode.ai/config.json",
  "permission": {
    "read": {
      "*": "allow",
      "*.env": "deny",
      "*.env.*": "deny",
      "*.pem": "deny",
      "*.key": "deny",
      "*id_rsa*": "deny",
      "*id_ed25519*": "deny",
      "*secrets*": "deny",
      "*.env.example": "allow",
      "**/*.env": "deny",
      "**/*.env.*": "deny",
      "**/*.env.example": "allow"
    },
    "edit": {
      "*": "allow",
      "docs/product/*": "deny",
      "docs/governance/CONSTITUTION.md": "deny",
      "docs/branding/brand-contract.md": "deny",
      "docs/branding/module-identity-map.md": "deny",
      "AGENTS.md": "deny",
      "opencode.json": "deny",
      "*.env": "deny",
      "*.env.*": "deny",
      "*.pem": "deny",
      "*.key": "deny",
      "docs/harness/CONTEXTO-CHATGPT.md": "deny",
      "docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md": "deny",
      "public/docs/index.html": "deny",
      "resources/roadmap/index.html": "deny"
    },
    "bash": {
      "*": "allow",
      "pwd": "allow",
      "git status*": "allow",
      "git diff*": "allow",
      "git log*": "allow",
      "git branch --show-current": "allow",
      "opencode --version": "allow",
      "php -v": "allow",
      "composer --version": "allow",
      "node --version": "allow",
      "npm --version": "allow",
      "git push*": "allow",
      "git push origin main": "allow",
      "git commit*": "allow",
      "git commit -m *": "allow",
      "git tag*": "allow",
      "git reset --hard*": "deny",
      "git clean*": "deny",
      "rm *": "deny",
      "ssh *": "deny",
      "scp *": "deny",
      "sftp *": "deny",
      "ftp *": "deny",
      "lftp *": "deny",
      "mysql *": "deny",
      "mariadb *": "deny",
      "php artisan migrate*": "deny",
      "php artisan db:*": "deny",
      "php artisan tinker*": "deny",
      "py -3 scripts/safe_git_pull.py": "allow",
      "git ls-files*": "allow",
      "git rev-parse*": "allow",
      "git fetch origin main": "allow",
      "git check-ignore*": "allow",
      "git hash-object*": "allow",
      "git ls-remote origin main": "allow",
      "git show*": "allow",
      "git merge-base*": "allow",
      "composer validate*": "allow",
      "php artisan test": "allow",
      "npm run build": "allow",
      "py -3 scripts/sf_verify.py*": "allow",
      "Test-Path *": "allow",
      "Get-Item *": "allow",
      "Get-ChildItem *": "allow",
      "Select-Object *": "allow",
      "Format-Table *": "allow",
      "echo *": "allow",
      "Remove-Item*": "deny",
      "Clear-Content*": "deny",
      "Remove-ItemProperty*": "deny",
      "del *": "deny",
      "erase *": "deny",
      "rmdir *": "deny",
      "rd *": "deny",
      "git add*": "deny",
      "git add -- *": "allow"
    },
    "external_directory": "deny",
    "webfetch": "allow",
    "websearch": "allow",
    "skill": "allow",
    "task": {
      "*publisher*": "deny",
      "*orchestrator*": "deny",
      "stockflash-orchestrator": "allow"
    }
  },
  "agent": {
    "stockflash-publisher": {
      "mode": "subagent",
      "permission": {
        "edit": "deny",
        "bash": {
          "*": "deny",
          "pwd": "allow",
          "git status*": "allow",
          "git diff*": "allow",
          "git log*": "allow",
          "git ls-files*": "allow",
          "git branch --show-current": "allow",
          "git remote -v": "allow",
          "git remote get-url origin": "allow",
          "git rev-parse*": "allow",
          "git merge-base*": "allow",
          "git check-ignore*": "allow",
          "git hash-object*": "allow",
          "git ls-remote*": "allow",
          "git show*": "allow",
          "git fetch origin main": "allow",
          "git add*": "deny",
          "git commit*": "deny",
          "git push*": "deny",
          "git add -- *": "allow",
          "git commit -m *": "allow",
          "git push origin main": "allow",
          "git add -- .": "deny",
          "git add -- ./": "deny"
        },
        "task": {
          "*": "deny",
          "stockflash-orchestrator": "deny",
          "*orchestrator*": "deny"
        },
        "external_directory": "deny"
      }
    },
    "stockflash-implementer": {
      "permission": {
        "bash": {
          "git commit*": "deny",
          "git push*": "deny"
        },
        "task": {
          "stockflash-publisher": "deny",
          "*publisher*": "deny",
          "stockflash-orchestrator": "deny",
          "*orchestrator*": "deny"
        },
        "edit": {
          "*": "allow",
          "app/*": "allow",
          "bootstrap/*": "allow",
          "config/*": "allow",
          "database/*": "allow",
          "resources/*": "allow",
          "routes/*": "allow",
          "tests/*": "allow",
          "scripts/*": "allow",
          "public/images/brand/*": "allow",
          "docs/work/*": "allow",
          "docs/harness/*": "allow",
          ".opencode/commands/sf-run.md": "allow",
          ".opencode/agents/stockflash-orchestrator.md": "allow",
          ".gitignore": "allow",
          "docs/work/features/*": "allow",
          ".opencode/agents/stockflash-publisher.md": "deny",
          ".opencode/commands/sf-publish.md": "deny",
          "docs/product/*": "deny",
          "docs/governance/CONSTITUTION.md": "deny",
          "docs/branding/brand-contract.md": "deny",
          "docs/branding/module-identity-map.md": "deny",
          "AGENTS.md": "deny",
          "opencode.json": "deny",
          "*.env": "deny",
          "*.env.*": "deny",
          "*.pem": "deny",
          "*.key": "deny",
          "docs/harness/CONTEXTO-CHATGPT.md": "deny",
          "docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md": "deny",
          "public/docs/index.html": "deny",
          "resources/roadmap/index.html": "deny"
        }
      },
      "mode": "all"
    },
    "stockflash-reviewer": {
      "permission": {
        "bash": {
          "git commit*": "deny",
          "git push*": "deny",
          "Test-Path *": "allow",
          "Get-Item *": "allow",
          "Get-ChildItem *": "allow",
          "Select-Object *": "allow",
          "Format-Table *": "allow",
          "echo *": "allow",
          "Remove-Item*": "deny",
          "Clear-Content*": "deny",
          "Remove-ItemProperty*": "deny",
          "del *": "deny",
          "erase *": "deny",
          "rmdir *": "deny",
          "rd *": "deny"
        },
        "task": {
          "stockflash-publisher": "deny",
          "*publisher*": "deny",
          "stockflash-orchestrator": "deny",
          "*orchestrator*": "deny"
        }
      }
    },
    "stockflash-orchestrator": {
      "description": "Delegate approved features to implementer, deterministic gates, independent QA and safe publisher.",
      "mode": "all",
      "permission": {
        "edit": {
          "*": "deny",
          "docs/work/.run/*": "allow"
        },
        "bash": {
          "*": "allow",
          "pwd": "allow",
          "git status*": "allow",
          "git diff*": "allow",
          "git log*": "allow",
          "git ls-files*": "allow",
          "git branch --show-current": "allow",
          "git remote -v": "allow",
          "git rev-parse*": "allow",
          "git fetch origin main": "allow",
          "git ls-remote origin main": "allow",
          "git check-ignore*": "allow",
          "git hash-object*": "allow",
          "git show*": "allow",
          "git merge-base*": "allow",
          "composer validate*": "allow",
          "php -v": "allow",
          "php artisan test": "allow",
          "composer --version": "allow",
          "node --version": "allow",
          "npm --version": "allow",
          "npm run build": "allow",
          "py -3 scripts/safe_git_pull.py": "allow",
          "py -3 scripts/sf_verify.py*": "allow",
          "git add*": "deny",
          "git commit*": "deny",
          "git push*": "deny",
          "git reset --hard*": "deny",
          "git clean*": "deny",
          "git checkout -*": "deny",
          "git branch -D*": "deny",
          "git tag*": "deny",
          "ssh *": "deny",
          "scp *": "deny",
          "sftp *": "deny",
          "ftp *": "deny",
          "lftp *": "deny",
          "mysql *": "deny",
          "mariadb *": "deny",
          "php artisan migrate*": "deny",
          "php artisan db:*": "deny",
          "php artisan tinker*": "deny",
          "rm *": "deny",
          "Test-Path *": "allow",
          "Get-Item *": "allow",
          "Get-ChildItem *": "allow",
          "Select-Object *": "allow",
          "Format-Table *": "allow",
          "echo *": "allow",
          "Remove-Item*": "deny",
          "Clear-Content*": "deny",
          "Remove-ItemProperty*": "deny",
          "del *": "deny",
          "erase *": "deny",
          "rmdir *": "deny",
          "rd *": "deny"
        },
        "task": {
          "*": "deny",
          "stockflash-implementer": "allow",
          "stockflash-reviewer": "allow",
          "stockflash-publisher": "allow"
        },
        "external_directory": "deny"
      }
    },
    "build": {
      "permission": {
        "task": {
          "*publisher*": "deny",
          "*orchestrator*": "deny",
          "stockflash-orchestrator": "allow"
        }
      }
    }
  },
  "compaction": {
    "auto": true,
    "prune": true
  },
  "default_agent": "build",
  "subagent_depth": 2
}
~~~

## 5. EXACT next task for Claude in this new session

**Read every section above and all five complete file bodies.** Then respond IN SPANISH:

1. **Validate the historical C0/Q1–Q3 plan**, including interval union and overlap semantics, the exact windows and parent-child linkage, completeness of timestamp fields, partition assumptions, and what tool/assistant residuals do *not* reveal. If needed, produce a concise SINGLE **read-only** OpenCode instruction for one SFH1 RESULT with the three aggregated queries; no parser, collector, code, raw transcript or Git sync. The prior HOBS issues are closed, so a new small approved SFH1 packet would have to be created by ChatGPT, not automatically run from old issues.
2. **Inspect the actual five full source files**, cite exact paragraph/step/JSON key and quote no more than short snippets as necessary. Determine precisely where C1/C2/C3 changes would conflict with existing permission rules, checkpoint strategy, correction cycles, code-changing workflow, mandatory QA/gates and publisher. Identify actual contradictions between `AGENTS.md`, `sf-run.md`, orchestrator instructions, `WORKFLOW-EFFICIENCY.md` and `opencode.json`.
3. Distinguish *EVIDENCE*, *HYPOTHESIS* and *NOT_EXPOSED*. Neither long parent assistant/task span nor session life nor cached context demonstrates long provider inference. Do not assert duplicate tools without evidence.
4. Recommend small, staged, **conditional** harness changes after Q1–Q3, with exact edit locations/acceptance checks. Preserve independent QA, deterministic gates, CSRF/RBAC/transaction protections, publisher, AUTO_GIT safeguards and exact-SHA CI.
5. No further requests for source files or earlier Claude chat: this document includes all original files and previous analysis. No more PowerShell scripts. Do not implement anything or send another generic audit. No fabricated savings percentages.

## 6. Privacy/provenance reminder

This packet contains project harness instructions/configuration and previously sanitized GitHub result comments only, not customer data, secrets, raw session IDs, transcripts, prompts/reasoning, tool arguments, or command outputs. Reading this packet grants no authority to modify the repository, call a provider, or run commands.

**END OF COMPLETE CLAUDE HANDOFF.**
