# HOBS-001 — OpenCode token baseline, Phase 0 ONLY

status: APPROVED
approval_basis: explicit_owner_request_2026-11-17; refined_2026-11-18
execution_mode: READ_ONLY
publication_mode: NO_GIT_PHASE0
audit: ON
audit_phase: 0
next: GPT

**This file is the complete Phase 0 instruction. Do not read `docs/harness/AUDITORIA-TOKENS-IA.md` (ChatGPT's future-phase design), invoke `/sf-run`, spawn subagents, create scripts/files, run gates/QA/CI, synchronize Git, commit or deploy.** There are no source changes, so no code-SHA/CI preflight is needed. Confirm the intended StockFlash repository via `git rev-parse --show-toplevel` only if necessary. Read the existing SFH1 issue #29 and this packet, not the entire harness.

## Read-only probe

1. Check installed `opencode --version`, `opencode stats --help`, `opencode session --help` and (only if useful) `opencode debug --help`. Use **only confirmed read-only options** from this exact local version. Obtain StockFlash-scoped project/session aggregate usage and a list of completed sessions. **Session titles are free text and may contain sensitive data: project only anonymous session IDs and safe metadata before displaying CLI output to the model; never include titles in the SFH1 RESULT.** If titles cannot be suppressed safely without writing code or accessing raw text, skip that listing and mark its data unavailable. Never replay a session or assume stats provide per-turn data.
2. Examine **up to three** completed, reasonably comparable feature sessions. Compare only when provider + exact model and harness configuration match, feature class is similar, and file/LOC change size is broadly comparable (rough 0.5×–2× if already known). Report corrections/QA round count and gate footprint separately. If these attributes are not in safe metadata, mark `not_comparable`; **do not open code or scan diffs just to force a match**. One run is a case study, not a savings estimate.
3. Determine whether these *actually exist* in safe accessible metadata: per-request input/output/cache tokens, per-turn context trend, first subagent request input, parent-child session IDs, request/tool timestamps, compactions and tool category/status. For each field report `available|not_exposed|not_checked`; unknown numbers remain null.

## Optional local session DB probe — metadata ONLY

**Skip entirely if the installed version/storage type, access path, permissions or privacy cannot be verified safely.** If the format is SQLite and a read-only CLI exists, use its **true read-only open mode** (e.g. `sqlite3 -readonly <verified-local-db-path>`) AND `PRAGMA query_only=ON`. **If a live SQLite/WAL database rejects read-only access, is locked or yields an uncertain/stale snapshot, stop this DB probe and report `WAL_READONLY_UNAVAILABLE` or `SNAPSHOT_UNCERTAIN`. Never copy the database, create missing WAL/SHM files, reopen with write permission or attempt a workaround.** No creation, migration, `ATTACH`, exports, plugins, writes or service changes.

- Schema discovery may inspect **table names and column names only** (bounded `sqlite_master` / `PRAGMA table_info`); no table rows/content for discovery. Verify identifiers before building a query.
- For data, use explicit **allowlisted columns** and a project/session scope with `LIMIT` (max three sessions; max 200 metadata events per session). **Never `SELECT *`**, message JSON/body, raw prompt/completion, tool arguments, tool output, source, secrets, paths or arbitrary text.
- Allowed output types: numeric token counts/durations, timestamps, anonymous parent/child links, exact model IDs, and **bounded categorical** tool **name/family**, agent role, event type and status. Tool names are useful to count read/search/shell calls; raw tool inputs are NOT allowed, so repeat-by-same-input remains `not_exposed`.
- If safe column-level output cannot be guaranteed **without writing a parser**, stop and report the gap. Do not execute ad hoc code to decode raw session records.

## One SFH1 result, then STOP

Post **one concise English `SFH1|src=OC|dst=GPT|kind=RESULT|phase=0`** to existing issue #29, showing installed version/supported read-only commands, selected session count/comparability, real token and cost totals if metered, per-turn/subagent/tool/latency availability, gaps and a recommendation on whether a future parser is worth its overhead. Use `source=CLI_STATS|SAFE_DB_METADATA|UNAVAILABLE`, `coverage=session|event|unknown`; do not invent totals, critical paths, duplicate counts or savings.

End with `scope=READ_ONLY;parser=NOT_BUILT;files=UNCHANGED;commits=NO;next=GPT`. Owner-facing reply stays brief in Spanish. No Phase 1/2 instructions, artifacts, publisher, additional handoffs or continued work until ChatGPT evaluates this result.
