# HOBS-002 — Targeted read-only analysis of an advanced OpenCode session

status: APPROVED
approval_basis: owner_request_to_diagnose_61pct_context_session_2026-11-18; HOBS-001_phase0_issued_result_i002
new_business_decisions: NONE
execution_mode: READ_ONLY
publication_mode: NO_GIT
audit_phase: 0B
next: GPT

## Objective

Identify whether the owner-observed advanced development session is the HOBS-001 case **A**, and characterize context growth using existing *numeric-only* OpenCode 1.19.5 session metadata. Owner screenshot: **642,721 tokens of context (61% UI), OpenCode-displayed US$2.20**, around 2026-11-18. HOBS-001 issue #29 case A reported peak 625,664 input and computed cost $2.204. This is **strong but NOT definitive identity evidence**. No introspection of the running advanced session, no prompt injected into it and no new instrumentation. Do not claim exact percentages of savings or wasted tokens.

## Procedure — one new read-only execution

1. Load only THIS packet and latest directed SFH1 task; refer to **issue #29 RESULT i=002**, numeric metadata only. **Do not load** the long token-audit design or unrelated project contracts/old session transcripts. On this task, a bare `ok` must trigger actual work and an SFH1 response, never empty `OK`.
2. At most **18 local read-only CLI/SQLite diagnostic operations**, plus one bounded SFH1 issue read and one final result comment. Verify local OpenCode installed version and safe metadata access. Reuse HOBS-001's already verified schema if trustworthy, not new schema spelunking. **No Git sync, CI preflight, subagents, code, parser, files, tests, commits or deploy.**
3. Match **case A** with the advanced running session *only if* corroborated by safe anonymous session ID and numeric signals (UI cost near 2.20; historical peak 625,664; latest request timestamp where available). Never trust title or proximity alone. If identity unconfirmed, set `identity=LIKELY_NOT_VERIFIED`, perform only a safe aggregate case A profile and do not attribute it conclusively to the screenshot.
4. For a confirmed case or case A as unconfirmed: SQL aggregation only, preferably **<=10 chronological bins** by request ordinal over selected session (parent-only), to show input/context, output, cache read, usage/cost trend and timestamp availability. Record *first / median / p90 / peak / last* **only where semantic meaning is known**; min/max and approximate quantiles must be labeled. Distinguish snapshot input/context from cumulative billed tokens. Explicitly note the HOBS-001 apparent case A `last=217` vs owner UI 642,721 discrepancy; determine whether sample ordering/session-type/provider caused it **without reading message bodies**, or report unresolved.
5. SQL aggregate tool **counts** by safe family read/search/shell/other and optionally by bin; summarize child count, first request input token distribution and parent/child cache accounting. Do not infer duplicated same-input tool calls without input fingerprints. Parent/child elapsed intervals can overlap; wall time is not inference time. No claims about actual compaction events when `time_compacting` absent.
6. Preserve strict HOBS-001 safety controls: native CLI structured output only through safe inline `jq` allowlist, SQLite true read-only and `query_only=ON`, numeric fields/known safe categories only, aggregate-first SQL, no raw session IDs/titles/prompts/tool arguments/outputs/paths/real secrets. No database exports/copies/sidecar creation, `immutable=1`, plugins or schema modifications. If WAL/permissions prevent safe access stop with a specific blocker.
7. Costs are `opencode_computed` unless provider-billed source is positively verified. If context model exact limit is unavailable, owner UI's `61%` is an observation, not measured telemetry. Unknowns remain null.

## Output

**Exactly one** sanitized English `SFH1|src=OC|dst=GPT|kind=RESULT|feature=HOBS-002|phase=0B` in the active issue, max **35 lines**, linked to original task. Include `identity=CONFIRMED|LIKELY_NOT_VERIFIED`; chronological bin summary of safe numbers; parent vs child/context/cache/tool counts; what the data support vs do **not**; `next=GPT`. Each measurement: `metric=value|null | source=CLI_STATS|SAFE_DB_METADATA|UNAVAILABLE | coverage=session|event|unknown`. On inaccessible GitHub/CLI return actionable `BLOCK`, never `OK`. **This read-only report is the only permitted write.** No extra work after posting.
