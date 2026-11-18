# StockFlash — Token audit design v2.1 (CHATGPT PLANNING ONLY)

**Do not load this document into the OpenCode HOBS-001 Phase 0 context.** The entire current Phase 0 operating instruction lives in `docs/work/features/HOBS-001.md`; that concise packet supersedes any Phase 0 operational detail below. This longer document is retained for ChatGPT to assess potential Phase 1/2 designs and their costs **after** the Phase 0 RESULT. It is NOT authorization to build a collector or to implement later phases.

**Scope:** OpenCode development harness, not business-app telemetry. Owner-facing communications and application/UI/manual/roadmap remain Spanish; internal harness English. External-review prompt stays OUTSIDE Git.

**Non-negotiable:** reduce audit observer cost and evidence bias. Never ask an agent to narrate its own tool calls, capture hidden reasoning, replay past sessions, execute a dummy feature just to collect data, or dump session transcripts into an LLM/Git. Unknown values stay null / UNAVAILABLE, not estimated from Thought seconds or call counts. Existing independent QA, security and exact-SHA CI remain unchanged for functional work.

## Phase 0 — read-only baseline (authoritative packet elsewhere)

**Actual OpenCode instruction is ONLY `docs/work/features/HOBS-001.md`.** Do not copy this GPT planning document into OpenCode Phase 0 context. The packet limits the probe to 15 local commands, 2–3 comparable *completed* historical sessions, a maximum 40-line SFH1 RESULT, safe CLI JSON projection (e.g. `jq`), and **SQL aggregate counts/grouping rather than raw event rows**. It excludes the active audit session or explicitly flags it when inseparable from project totals; verifies token-cache inclusion rules; permits safe anonymized tool/role/status categories, and prevents raw titles, tool arguments and prompts from reaching the model. Optional SQLite queries require true read-only mode, allowlisted columns and no unsafe WAL workarounds. There is **no Git synchronization, CI preflight, collector, JSONL, QA subprocess or commit** in this read-only phase.

After inspecting observed Phase 0 metrics and coverage limitations, ChatGPT alone decides whether Phase 1 is worth the observer cost. One case study or incomparable models must not become a causal savings claim. All functional-code workflow safety requirements remain intact.

## Phase 1 — minimal deterministic metadata extractor (NOT automatic)

**Only after ChatGPT inspects Phase 0 evidence and opens/approves a separate scoped packet.** Address the **specific missing high-value metrics**; do not build a complete logger upfront. Prefer supported native aggregate/session metadata and numeric-only read-only structured access. No network service, plugin installation, global settings, raw transcript output, per-event model calls or second audit agent. Test omission of arbitrary text/PII and correct unknown/null accounting with tiny synthetic fixtures. The collector runs **outside the model loop**. Stop if privacy cannot be guaranteed without excessive implementation.

## Phase 2 — prospective full-cycle observation (NOT automatic)

Requires separate scope and justified Phase 1 evidence. Collect during a **naturally needed approved feature** in local ignored/temp storage: `docs/work/.run/audits/<RUNID>/` or permitted OS temp. **No tracked audit files during feature gates, QA or publisher**. If a safe observer exists, record actual events through the feature's **implementation → deterministic gates → independent QA and fixes → code publisher → exact-code-SHA GitHub CI**. Unobserved steps are `coverage=unknown`, never reconstructed by model narration.

**Two distinct, nonrecursive publications:**
- **Feature code SHA:** normal gates, QA, publisher, exact-SHA CI; do not inject a changing JSONL/analysis into its staged tree or its fingerprints.
- **Audit-only SHA AFTER the feature code CI has finished:** one deterministic post-run aggregation produces sanitized `docs/work/audits/TOKEN-AUDIT-<FEATURE>-<DATE>-<RUNID>.jsonl` and `...-ANALYSIS.md`, reviewed as a **separate documentation-only follow-up** (independent privacy/coverage review, selective Git publication, CI exact audit SHA). This second commit is NOT the feature's reviewed code SHA and does not retroactively invalidate that code evidence. The audit-only follow-up's own QA/publisher/CI are expressly **outside the observed window**, to avoid infinite logging/publication recursion.
- If Phase 2 cannot safely capture after feature completion, report implementation-only coverage and missing QA/CI/publisher metrics explicitly rather than claiming full-cycle coverage. Do not generate/overwrite previously reviewed artifacts.

## Measurement and minimal event format (Phase 1/2 only)

Priority P0: native **metered** tokens per request (input/output/cache/read/write/reasoning when surfaced); **context input per turn and its growth**, peak/median/p90, and **first-request input tokens for each subagent**. Clearly flag when parent totals include child usage or cached tokens are already included in input; do not double count. Token metrics may be unavailable in old session formats.

Priority P1: observed wall-clock start/end, inference intervals, local tools/tests, waiting, QA/publisher, CI and parent-child dependencies. **Critical path** is the longest actual dependency sequence; overlapping subagents cannot be summed as sequential time. Without reliable timestamps/session links, report unknown, never `wall - tools = model time`.

Priority P2: repeat reads/glob/command/test on the **same input**, normalized operation category, true output bytes delivered to the model when exposed; stdout/filesize only explicitly labeled proxy. **Deterministic classification candidates**:
- identical normalized operation + identical input fingerprint + **same agent** + no intervening relevant change ⇒ `same_input_repeat_candidate`;
- identical fingerprint + **different reviewer agent/QA stage** ⇒ `independent_QA_candidate`, not automatically waste;
- source/input fingerprint changed ⇒ `after_change_recheck`;
- missing source/timeline/fingerprint ⇒ `unclassified`.
A repeated operation can be necessary; the parser reports candidates and evidence, never unilaterally removes gates.

Minimal observed JSONL record:
`{v:2,run,span,parent,agent,stage,kind,t0,t1,status,source,coverage}`, with nullable observed UTC timestamps; `source` is `native_usage|native_event|cli_stats|read_only_history|safe_hook|derived_summary` and `coverage` is `event|stage|session|unknown`. Optional measured values: `ms,model,usage:{input,output,cache_read,cache_write,reasoning,total,cost_usd},first_turn,request_ordinal,compaction,operation_id,source_fingerprint,output_to_model_bytes,exit_code,overlap_group,accounting`. No subjective `risk_class`, `must_keep`, explanatory free text or fabricated zero values. A stage aggregate must be labeled `source=derived_summary;coverage=stage`, never passed off as per-tool telemetry.

**Fingerprint privacy:** do not publish unsalted hashes of short predictable/config/sensitive contents (dictionary attacks). Prefer an opaque per-run ID derived from safe Git tree state for public code, or use **HMAC-SHA256 with a private project key held outside Git/logs** if cross-run equality is genuinely needed. If no safe secret-free fingerprint scheme is possible, omit fingerprint and mark comparison unavailable. Do not create, rotate or expose new secrets solely for Phase 0. Never compute fingerprints from real .env/credentials/PII.

## Aggregation, privacy, and validity

- Deterministic numeric aggregation, deduplication and tables are performed by non-LLM code **only if Phase 1/2 is approved**. The model provides a short interpretation of aggregated facts; no additional full audit subagent or per-row semantic redaction. Keep instrumentation overhead separately measured (wall/CPU/bytes and extra model calls, if observable).
- Strict output **allowlist**: only anonymized IDs, measured usage, timestamps, safe status, command **category** and allowed fingerprints. Never emit raw exports, message bodies, prompts, completions, hidden reasoning, tool stdout, command arguments, source content, customer paths, secrets, environment values, email/RUT/PII. An export claiming to be sanitized is not automatically safe.
- The existing private GitHub repo is already approved for **sanitized derived** evidence, not raw transcripts. Uploading files to Claude or any other provider is a separate owner decision.
- A single run cannot establish an improvement percentage. For before/after claims prefer ≥3 reasonably comparable runs per condition with same provider/model/config and similar work; stratify feature type, correction rounds and QA footprint. Without those, report only directional evidence and uncertainties. No artificial repeated QA/feature work just for statistical significance.
- Preserve every existing mandatory functional check, independent QA, safe Git publication and exact-SHA CI. **Phase 0 has no functional changes and therefore does not introduce new feature gates**. Any Phase 1/2 implementation will be treated as a separate change with its own approved acceptance/QA and CI.

## Phase 0 completion

OpenCode's next addressed SFH1 RESULT must state `phase=0;scope=READ_ONLY_NATIVE_METADATA;code_changes=NO;commits=NO;parser=NOT_BUILT;coverage=...`, include compact actual usage/latency if visible, document missing data, and set `next=GPT`. ChatGPT will decide whether Phase 1 is cost-justified; no automatic continuation.
