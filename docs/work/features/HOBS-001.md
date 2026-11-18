# HOBS-001 — OpenCode historical token baseline (Phase 0 ONLY)

status: APPROVED
execution_mode: READ_ONLY
publication_mode: NO_GIT_PHASE0
approval_basis: explicit_owner_request_2026-11-17; reviewed_2026-11-18
audit: ON
audit_phase: 0
next: GPT

**Only this packet and the latest SFH1 messages in issue #29 are operational instructions.** Do not load the long GPT planning protocol `docs/harness/AUDITORIA-TOKENS-IA.md`. No Git sync/CI preflight, `/sf-run`, agents, tests, new scripts/files, parser, export, writes, commit or deploy. Max **15 local CLI/SQL commands total**; if the cap is reached, stop and report partial coverage. Do not spend commands seeking unavailable fields.

## Read-only baseline

1. Check installed `opencode --version`, `stats --help`, `session --help` and optionally `debug --help`. Use only version-confirmed read-only commands. `debug paths` may locate local session storage; **never run `opencode session export` or debug commands that dump session/message/tool content**. No plugins, global configuration changes or hidden reasoning.
2. Get StockFlash-scoped stats and up to **three completed** historical sessions. Exclude the **current Phase 0 audit session** (and any live sessions) from candidates and per-session totals. If project-level `stats` includes the current session and cannot separate it accurately, label the aggregate `includes_current_audit_session`; do not subtract invented values.
3. **Free-text session titles are unsafe.** When supported, pipe structured CLI JSON directly through a **`jq` allowlist projection** in the same shell command, before any output reaches the model: only anonymous labels (`s1`, `s2`...), numbers, timestamps, model IDs and bounded safe categories. A local inline `jq` filter is allowed, **not** a saved script/parser. Verify the documented JSON shape/fields without printing raw output. If safe projection cannot be guaranteed, skip `session list` and mark `not_exposed`. Never print/store/report titles, raw IDs, prompts, arguments or other text.
4. Comparable sessions: same provider/exact model and harness configuration, similar feature class and roughly 0.5×–2× changed files/lines. If useful, read only approved feature-packet **ID/type/header** (up to three packets) and `git log --shortstat` with **SHA/date only, NO commit subject/body or diff**; use already known feature SHA mapping. Dates/shortstat alone do **not** prove session-to-feature linkage. If unverifiable, label `not_comparable`, report cases separately and do not invent an A/B result. Record QA correction rounds/gate footprint only if already visible in safe metadata.
5. Check actual availability of: request input/output/cache/reasoning tokens, cache-accounting semantics, per-turn input/context growth, first input turn per subagent, anonymous parent-child linkage, tool categories/status, request/tool timestamps and compactions. Mark each `available|not_exposed|not_checked` and use null for missing values. **Cache-read tokens may already be included in input**; do not sum cache+input or parent+child totals until accounting semantics are confirmed.

## Optional local SQLite metadata — aggregate queries FIRST

Skip if installed version, format, path, access rights or confidentiality cannot be verified safely. If storage is SQLite and a true read-only CLI exists, open with `sqlite3 -readonly <verified-local-db-path>` and `PRAGMA query_only=ON`. Inspect only schema table/column **names**, never text rows. Restrict to selected project/session IDs and **explicit verified allowlisted columns**. No `SELECT *`, raw JSON/body, user text, paths, message contents, tool arguments or outputs. Do not write ad hoc parsers.

- Prefer **SQL `COUNT`, `SUM`, `MIN/MAX`, `GROUP BY`** with bounded safe categories: mapped tool family (`read/search/shell/other`), agent role, model ID, event type and status. Cap query output at **30 aggregate groups**. Only sum counters whose per-record semantics are verified; do not double count cached or repeated cumulative totals.
- **Individual rows are prohibited**, except at most the **first request per observed subagent** (max 12 rows total), projected to an anonymous label, numeric token counters and timestamp only. Use an explicit `LIMIT` and validated field names. No session titles or raw session IDs in model output.
- If concurrent OpenCode use / SQLite WAL causes lock, write-access demand or uncertain/stale snapshot, report `WAL_READONLY_UNAVAILABLE` or `SNAPSHOT_UNCERTAIN`. **No database copy, WAL/SHM creation, read-write retry or workaround.** Safe metadata missing? Stop this probe.

## One compact SFH1 RESULT, then STOP

Read only the **latest SFH1 comments** from issue #29 (avoid unrelated historical comments). Post exactly one English `SFH1|src=OC|dst=GPT|kind=RESULT|phase=0`, **maximum 40 lines**, with: CLI version/supported commands, historical sessions selected/comparability, current-session exclusion, actual aggregate totals, request/context/first-subagent/cache/tool/latency availability, missing signals, and whether a future minimal extractor is worth its implementation overhead.

**One line per metric:** `metric=value|null | source=CLI_STATS|SAFE_DB_METADATA|UNAVAILABLE | coverage=session|stage|event|unknown`. Do not claim repeated same-input operations without tool arguments/fingerprints. No guessed token prices, savings, durations or critical paths. Finish with `scope=READ_ONLY;commands<=15;parser=NOT_BUILT;files=UNCHANGED;commits=NO;next=GPT`. Brief Spanish owner-facing reply only; no further work.
