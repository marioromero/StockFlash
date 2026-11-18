# StockFlash — Token audit design v2.1 (CHATGPT PLANNING ONLY)

**Do not load this document into the OpenCode HOBS-001 Phase 0 context.** The entire current Phase 0 operating instruction lives in `docs/work/features/HOBS-001.md`; that concise packet supersedes any Phase 0 operational detail below. This longer document is retained for ChatGPT to assess potential Phase 1/2 designs and their costs **after** the Phase 0 RESULT. It is NOT authorization to build a collector or to implement later phases.

**Scope:** OpenCode development harness, not business-app telemetry. Owner-facing communications and application/UI/manual/roadmap remain Spanish; internal harness English. External-review prompt stays OUTSIDE Git.

**Non-negotiable:** reduce audit observer cost and evidence bias. Never ask an agent to narrate its own tool calls, capture hidden reasoning, replay past sessions, execute a dummy feature just to collect data, or dump session transcripts into an LLM/Git. Unknown values stay null / UNAVAILABLE, not estimated from Thought seconds or call counts. Existing independent QA, security and exact-SHA CI remain unchanged for functional work.

## Phase 0 — native historical baseline ONLY (current HOBS-001 scope)

1. **GPT planning note only; OpenCode should not load this section.** Phase 0 has no new code, parser, hooks, gates, QA subprocesses, audit JSONL, Git publication, code-SHA/CI preflight or safe Git sync. Native CLI syntax must be verified against the installed client, not guessed from online examples.
2. Read **only 2–3 comparable completed StockFlash feature sessions** if available; read-only, no replay. Begin with safe aggregate usage counters and session-list metadata. Before requesting deeper inspection, verify whether metadata actually exposes: (a) per-turn tokens/timestamps; (b) parent/child session links and per-child first-request input; (c) tool start/end timestamps; (d) cache-inclusion rules. Record `available|not_exposed|not_checked` separately for each field. `stats` aggregates do NOT by themselves prove per-turn coverage.
3. If native stats are insufficient, a supported local metadata query is acceptable ONLY when format/path/permissions are verified: for SQLite, true `sqlite3 -readonly` + `PRAGMA query_only=ON`, schema-name/column-name discovery only, explicit allowlisted output columns and bounded `LIMIT` with project/session scope; never `SELECT *`. Allowed categories include tool name/family, agent role, model ID, event type and status, plus numeric/timestamp/anonymous parent links; never tool arguments, prompts, message text, outputs, paths or secrets. If impossible without new code, report the gap rather than authoring a parser in Phase 0.
4. HOBS-001 Phase 0 delivers **one compact SFH1 RESULT in the existing GitHub issue #29**: installed CLI version; safe commands actually supported; 2–3 sessions selected or explicit reason not comparable/available; actual token/context/subagent/latency values if available; per-metric coverage and privacy limitations; proposed Phase 1 only when the gap justifies its cost. **Do not publish an audit file or commit anything in Phase 0.** The GitHub issue is the initial readable evidence for ChatGPT. No full `sf-run`, PHPUnit, build, reviewer/publisher loop or CI triggered merely by the read-only audit.
5. The SHA/CI start guard still applies: check both workflows 2/2 SUCCESS on exact base HEAD before a local safe sync. On `SYNC_BLOCKED`, stop without reset/stash/clean. No secrets, real DB, real installer, deployment, global config modifications, plugins or operational permissions beyond approved safe read-only diagnostics.

### Comparable historical runs

Group sessions by **same provider + exact model ID/version, same agent/harness configuration, same feature class** (e.g. CRUD/catalog vs installer/security/infra), and broadly similar changed-file count **and** changed-line count (default 0.5×–2× range; treat as matching heuristic, not statistical proof). Record gate/test footprint and number of QA corrective rounds separately, because these explain rather than erase rework. Split pre/post harness changes; never pool incomparable models or substantially different tasks into a causal savings percentage. If 2–3 matches do not exist, provide descriptive case studies and explicit `baseline_not_comparable`.

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
