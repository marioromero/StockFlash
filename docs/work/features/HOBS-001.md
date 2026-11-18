# HOBS-001 — OpenCode historical token baseline (Phase 0 ONLY)

status: APPROVED
execution_mode: READ_ONLY
publication_mode: NO_GIT_PHASE0
approval_basis: explicit_owner_request_2026-11-17; owner_reviewed_2026-11-18
audit: ON
audit_phase: 0
next: GPT

**Scope:** Only this packet and the latest directed SFH1 comments in issue #29 are operational. Do NOT load the longer GPT-only `docs/harness/AUDITORIA-TOKENS-IA.md`. No Git sync/CI preflight, `/sf-run`, subagents, tests, new files/scripts/parser, session export, code edits, database writes, commits or deploy. **The sole permitted write is ONE SFH1 RESULT comment in issue #29.**

**Budget:** at most **20 local diagnostic CLI/SQL operations**, counting each executed subcommand or SQL query even when combined in one shell invocation. Allow separately **one** issue read, **one** issue RESULT write and up to **three** approved feature-packet header reads; these exceptions are not unlimited. Combine `--help` calls only if practical. On budget exhaustion, stop with partial coverage. Do not keep probing unavailable fields.

## 1. Read-only native baseline

1. Check the installed `opencode --version`, `opencode stats --help`, `opencode session --help` and optionally `opencode debug --help`. Run only locally verified read-only options. `opencode debug paths db` may locate session storage; **never use `opencode session export`**, session content-dumping commands, or `debug` commands that reveal transcripts, messages, prompts or tool arguments. No plugins, global config edits or background service.
2. Obtain StockFlash-scoped usage statistics and completed-session metadata. **Exclude the current Phase 0 session and all active sessions** from candidate selection and per-session metrics. If a project-wide statistic includes the live audit session and exact exclusion is impossible, label `includes_current_audit_session` and do not guess a subtraction.
3. Session titles are untrusted free text. When supported, pipe **structured CLI JSON directly into an inline `jq` allowlist projection before any output reaches the model**. Only anonymous labels (`s1`–`s3`), numbers, timestamps, canonical model IDs and safe categories may emerge. To check the response shape, output **key names only**, never values, and only if keys cannot be user-provided/dynamic. If `jq` is absent, JSON shape cannot be verified, or projection fails, skip the list; do not install tools or expose raw output. Never publish real session IDs/titles, customer names, paths, prompts or arguments. A one-off inline `jq` projection is allowed, a saved parser/script is not.
4. Prefer **2–3 genuinely comparable completed feature sessions**: same provider/exact model and agent/harness configuration, similar feature class and roughly 0.5×–2× changed files and changed lines. To verify a known link, consult only up to three approved feature-packet **ID/type/header** and safe commit statistics (`git log --format='%h %as' --shortstat`; no commit subject/body, filenames or diff). A matching date/shortstat alone does NOT link a session to a feature.
5. **Fallback / case studies:** if session-to-feature linkage or comparability cannot be verified, from up to ten most recent **completed** sessions select three with the highest safely available request counts; if request counts are unavailable, take the three most recent completed. Label `case_study;not_comparable;selection_biased` and report separately. Do not call these an A/B baseline, estimate savings or open application code/diffs to force a match. QA rounds and gate footprint only if already visible in safe metadata.
6. Verify which metrics actually exist: per-request input/output/cache/reasoning tokens; cache accounting; per-turn input/context trend; first request input per subagent; anonymous parent-child links; tool category/status; request/tool timestamps; compactions and latency. Mark `available|not_exposed|not_checked` for each; unavailable numbers stay null. **Cache-read tokens can already be included in input**, and parent totals may include child tokens; do not double count without verified semantics. If OpenCode reports a cost, label `cost_source=opencode_computed` unless a separately verified provider-billed origin exists; never call a computed value a billed charge.

## 2. Optional SQLite metadata probe — aggregates before rows

**Skip entirely** unless the local OpenCode version, database format/path, access permission and safe schema are verifiable. For SQLite use actual CLI read-only mode (`sqlite3 -readonly <verified-local-db-path>`) together with `PRAGMA query_only=ON`; read-only means **no filesystem modifications**, including new WAL/SHM sidecars. Inspect only bounded **table/column names** first (SQLite schema/PRAGMA), not row values. Restrict all data queries to selected project/session scope, explicit verified allowlisted fields and `LIMIT`; **never `SELECT *`**. No ad hoc scripts or saved exports.

**JSON metadata:** `json_extract(column, '$.explicit.path')` is permissible ONLY on a verified structured metadata column with a known safe key path and verified data type (numeric/timestamp/canonical model ID/agent role/event type/status/tool name or family). If essential to discover keys, allow top-level and one-level **key names only** from a known metadata-only column, with project/session restrictions and max 30 returned keys. Filter to a strict fixed schema-key allowlist **in SQL before output**; no dynamic/user-supplied keys. **Never select/print whole JSON, unapproved key names, JSON values, message content or free text.** If the column/key safety or token semantics cannot be established, skip it; do not explore message-body JSON.

**Aggregates first:** prefer SQL `COUNT`, `SUM`, `MIN/MAX`, `GROUP BY`, grouped by bounded approved tool family (`read/search/shell/other`), agent role, model ID, event type/status, **at most 30 output groups**. Only sum per-event counters when known noncumulative; do not double count cached input or inclusive child usage.

**Rows exception:** individual rows are forbidden except the **first metered request of each observable subagent**, max **12 rows total** (anonymous label + numerical token counters + timestamp), only when a trusted parent/child link and request order exist. Never output real session IDs or titles. Do not infer which request was first from missing timestamps.

**WAL/locking:** if a live WAL database needs sidecar creation, is locked, unavailable or may return a stale/uncertain snapshot, report `WAL_READONLY_UNAVAILABLE` or `SNAPSHOT_UNCERTAIN`. No read-write retry, `immutable=1`, database copy, WAL/SHM creation, `ATTACH`, exports, plugins or other workaround.

## 3. One SFH1 RESULT then STOP

Read only the latest directed SFH1 comments in issue #29; do not echo unrelated issue comments. Post exactly **one** English `SFH1|src=OC|dst=GPT|kind=RESULT|phase=0`, **max 40 lines**, including installed version/safe commands, candidate count and comparability, current-session exclusion, actual tokens/cost (if metered), cache-accounting caveat, availability of per-turn/subagent/tool/latency metrics and whether a future small extractor is justified.

**Per metric:** `metric=value|null | source=CLI_STATS|SAFE_DB_METADATA|UNAVAILABLE | coverage=session|stage|event|unknown`. Clearly label `case_study`, biased selection and unknowns. Without tool arguments/fingerprints, same-input repeats are `not_exposed`; do NOT invent duplicate counts, billed costs, savings, durations or critical paths.

Final marker: `scope=READ_ONLY;commands<=20;parser=NOT_BUILT;files=UNCHANGED;commits=NO;next=GPT`. Owner-visible reply brief Spanish; no further operations after RESULT.
