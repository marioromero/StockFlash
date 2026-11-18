# StockFlash — Token and execution audit protocol v1 (agent-facing, English-only)

**Status:** PREPARED. **Activation:** next approved feature/handoff explicitly containing `audit=ON`. Do not replay INSTALL-001 retroactively or rewrite an in-flight session. **Purpose:** supply ChatGPT with traceable data for an independent efficiency review; external AI review prompts are provided to the OWNER OUTSIDE this repository.

## Measurement rules
- Record each **observable** main-agent and subagent stage; each tool invocation when native events are available, each shell process, file read/search/write, targeted test/full gate/CI, model-visible compaction, Git/publisher step, QA finding and correction cycle. For inaccessible subagent internal calls, record a single aggregate with `coverage=summary_only`, `toolcalls_reported` and clear missing coverage. Do not fabricate a full trace.
- True token usage requires provider/OpenCode meter data; preserve exact `model_id`, input/output, cache read/write, reasoning token counts **only when reported**, with source and inclusion semantics. If unavailable, set null and explain. Local test duration and `Thought` seconds are NOT tokens; `response_bytes_to_model` is a context-volume proxy, NOT a token count.
- Avoid observer overhead: prefer OpenCode-native events/hooks if verified against installed version; otherwise use safe stage-level batching into JSONL. Do NOT invoke the LLM once per tool event or pipe full logs back into context.
- Do not collect hidden chain-of-thought, complete prompts, raw customer data, .env, tokens/secrets, dump files, private keys, credentials or unredacted stdout. Even a third-party export of this audit requires owner authorization.

## Artifacts (per feature/run)
- Event stream: `docs/work/audits/TOKEN-AUDIT-<FEATURE>-<YYYYMMDD>-<RUNID>.jsonl`, append-only **UTF-8 JSON per line**. Write checkpoints after each subagent/phase; not one Git commit per event. Record complete metadata for each observable process; truncate and redact unsafe stdout and refer to local-only evidence by safe ID.
- Analyst report: `docs/work/audits/TOKEN-AUDIT-<FEATURE>-<YYYYMMDD>-<RUNID>-ANALYSIS.md` with coverage, per-agent/stage summary, top context-volume consumers, duplicate scans/tests, legitimate QA reruns, causes of corrective cycles, proposed optimizations and testable A/B metrics.
- Do not create/edit these files until the packet/handoff activates the audit. The sanitized final artifacts are published through normal safe Git publisher along with the feature, not by background timer.

## JSONL schema
Every event:
`schema_version`=1, `event_id` (unique), `run_id`, `feature`, `timestamp_start_utc`, `timestamp_end_utc` (nullable), `duration_ms` (nullable), `stage`, `cycle`, `agent_role` (orchestrator|implementer|reviewer|publisher|other), `agent_instance_id`, `parent_span_id` (nullable), `event_type`, `operation`, `status`, `coverage` (native|wrapper|manual|summary_only), `git_head` or `tree_fingerprint`, `description` (one sentence).

When applicable: `tool_name`, `command_redacted`, `target_paths` (relative, sanitized), `path_count`, `read_bytes`, `write_bytes`, `stdout_bytes`, `stderr_bytes`, `response_bytes_to_model`, `response_lines_to_model`, `output_truncated`, `exit_code`, `evidence_ref`, `test_count`, `assertion_count`, `toolcalls_reported`.

`tokens` object: `{input,output,reasoning,cache_read,cache_write,total,source,availability_reason,includes_subagents}`, with null numbers when unavailable. `cost_usd` is optional provider-reported cost, NOT estimated without a verified price table. Do not double count a parent total that already includes child tokens.

Repeat diagnostics: `is_repeat`, `repeat_of_event_id`, `repeat_reason` (retry_after_change|qa_independent|same_input_recheck|duplicate_unnecessary|unknown), `input_fingerprint`, `risk_class`, `must_keep`.

## Capture checkpoints
- `run_start`: base SHA, active packet, OpenCode client version, model/provider names if available, instrumentation mode, scope/limits (no secrets).
- For each subagent: `subagent_start` / `subagent_end`, count of observable toolcalls, wall time and summary of hidden/uncaptured calls.
- For each deterministic gate: ID, command (redacted), file fingerprint, elapsed time, exit code, tests if known, report path. Distinguish local computation from model inference.
- For each QA iteration: new findings, causal changed-file inventory, test evidence reused vs invalidated, additional file reads and repeated commands.
- `run_end`: status (complete|blocked|interrupted), final SHA, number of agents/events, coverage denominator (if observable), missing data, measured tokens by source and CI status.
- The analysis must identify operations repeated unnecessarily **with event IDs and same-input fingerprints**, not label required independent adversarial QA as waste by default.

## Report checklist
1. Feature, date, exact SHA(s), observed tools/models, coverage and limitations.
2. Table by agent/cycle/stage: calls, wall time, local shell time, read/response bytes, measured tokens/cache/cost if available, compacted contexts, tests.
3. Top fifteen model-context outputs; measured token consumers separately if available.
4. Duplicated file reads, glob/grep, PHPUnit suites, typecheck/build, CI calls; justify which are required by independent QA, security or changed fingerprints.
5. Evidence-based short list of changes, expected direction, risk and preservation of contractual gates; no fabricated % savings.
6. Suggested A/B with comparable task, same model and verified before/after data; identify unknown denominators.
7. Explicitly explain whether the capture mechanism *actually* covered every subagent. Do not claim perfect telemetry when only phase summaries exist.

## Safety and completion
- Use secret/PII redaction and semantic review before publication, not regex alone. Do not include raw logs, hidden reasoning or credentials in Git.
- Instrumentation cannot grant permissions, read new secrets, execute installation/deploy/remote DB, or alter the QA/release gate.
- At completion OpenCode reports via SFH1: `audit=RECORDED`, source SHA, two artifact paths, measured-token availability and coverage level. ChatGPT reads from GitHub and independently evaluates optimization options.
- **No Claude/Gemini/third-party prompt in the repository**; provide the owner an exportable, sanitized evidence bundle and prompt separately in chat.
