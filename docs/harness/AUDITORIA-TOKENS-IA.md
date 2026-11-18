# StockFlash — Token, context and latency audit v2 (English-only agent protocol)

**Status:** READY; **activation:** HOBS-001 / next explicitly authorized `audit=ON` run. This protocol measures the development harness, NOT the StockFlash business application. Do not recreate past sessions, replay INSTALL-001, or rewrite an in-progress session. **Historical read-only statistics ARE allowed** and preferred for a baseline. External AI critique prompts are for the owner, not this repository.

## Principle: passive, cheap and verifiable

1. **Observer outside the model loop first.** OpenCode/provider native **usage counters and existing local session history** are the first source. Run `opencode --version` and relevant `--help` to identify commands supported by the installed client; candidates in current OpenCode documentation include `opencode stats --project . --json`, `opencode session list --format json` and `opencode session export <ID> --sanitize`. Never assume the installed syntax or available per-agent fields from documentation alone. Prefer aggregated project/session statistics and structured metadata **without exporting message bodies**. Inspect local storage/session DB only through a supported read-only interface or safe bounded query if CLI metadata is insufficient and permission allows it; no raw database, transcripts, prompts or tool arguments go into Git.
2. **No agent-generated event narration.** Do not ask the implementer/reviewer/orchestrator to manually write one JSONL line per tool or thought. Collect from machine events, a read-only local post-run parser, or a small opt-in hook **after** checking installed-version support and safety. Hooks must not use an extra LLM call, forward raw payloads, modify tool outputs, spawn services, update global config or silently install packages. If no safe external observer is available, publish **aggregated actual metrics only** and mark granular coverage `unavailable`, rather than simulate a trace or require a second agent to reproduce the timeline.
3. **Instrument only the missing signals.** First collect baseline from 2–3 existing comparable StockFlash feature runs when safely available. Record known limits, old session/client/model differences and possible incomplete history. A retrospective read of existing metering is allowed; retroactively inventing per-event measurements is not. Start new instrumentation only if built-in stats cannot answer the question.
4. Preserve approved deterministic gates, independent read-only QA, secrets policy, safe publisher and exact-SHA CI. **Audit data must not change the verified source-tree fingerprint.**

## What to measure, in priority order

**P0 — actual token usage and context growth:** per model request/turn if exposed: provider/model ID, input, output, cache read/write, reasoning, cost only when metered; session and subagent linkage; first-turn input of each spawned subagent (its startup/context overhead); per-turn input and cache tokens over time, peak/median/p90, and compaction count/time. Distinguish prompt/input metering semantics by provider; cached tokens may be included within input. Never add these fields blindly or double-count parent+child inclusive totals. A `Thought: 352ms` label is **not** inference-token or total context usage.

**P1 — end-to-end latency and critical path:** wall-clock task start/end, subagent lifecycle (parallel vs serial), model inference interval **when explicitly observable**, local shell/tests/build/QA, Git/CI, and genuine wait/idle time **only when measured**. Report total elapsed and longest dependency chain; do not sum overlapping spans, count parallel waits twice, or infer model time as `wall - tool time` when timestamps/queue delays are missing.

**P2 — repeated work and model-visible output:** instrument repeated file reads, global globs, commands and gates with privacy-safe **normalized operation IDs** plus input/source fingerprints, event counts and **bytes actually returned to the model** if available. Tool stdout bytes or read-file bytes are only proxies unless delivery to model is confirmed. Classify repetition as `required_by_QA`, `after_change`, `same_input_recheck` or `unknown` by deterministic rules when possible; an LLM may discuss reasons only afterward.

## Minimal machine event schema (JSONL v2)

One compact JSON object for each **actually observed** event. Required:
- `v` (2), `run` (ID), `span` (unique), `parent` (ID or null), `agent` (agent/session ID + role when known), `stage` (short enum), `kind` (model|tool|subagent|gate|ci|session), `t0` and `t1` (UTC ISO timestamps if observed, else null), `status`, `source` (native_usage|native_event|cli_stats|read_only_history|safe_hook|derived_summary), `coverage` (event|stage|session|unknown).
- Optional **measured** fields only: `ms`, `model`, `usage:{input,output,cache_read,cache_write,reasoning,total,cost_usd}`, `request_ordinal`, `first_turn`, `compaction`, `output_to_model_bytes`, `operation_id` (allowlisted command family, NOT raw arguments), `source_fingerprint`, `exit_code`, `overlap_group`. Unsupported quantities are **null or omitted**, NEVER invented or forced to zero.
- `agent` may be anonymous stable IDs; link to parent via session/thread ID only if proven, not guessed. If the parent's token total includes children, tag `accounting=inclusive` to prevent double counting in aggregation.
- **Subjective labels** (risk class, must_keep, reason for redundancy) do **not** belong in event collection. The analysis may attach them to event/operation IDs with clear evidence.
- Example of a safe **derived stage summary**, not fabricated tool event:

```json
{"v":2,"run":"HOBS001-R1","span":"s1","parent":null,"agent":"reviewer#1","stage":"qa","kind":"subagent","t0":null,"t1":null,"status":"completed","source":"derived_summary","coverage":"stage","ms":null,"usage":null}
```

## Data capture and working-tree isolation

- **Never append audit artifacts to tracked files while gates/QA/publisher fingerprints are being computed.** Prefer a permitted local OS-temp directory; if the agent's external-directory sandbox forbids this, use already ignored `docs/work/.run/audits/<RUNID>/` inside the workspace. Exclude the temporary audit path from **functional tree fingerprints**, NOT from security screening. Keep local data minimal and delete nothing automatically.
- One deterministic **post-run** converter reads only permitted, bounded metadata and writes two **sanitized final outputs** to `docs/work/audits/` once, before final review/publisher: `TOKEN-AUDIT-<FEATURE>-<DATE>-<RUNID>.jsonl` and `...-ANALYSIS.md`. No Git commit per event. Include both in explicit review/staging manifest; a change to final published audit content after QA requires review of that content, but not a full functional gate rerun when source is unchanged and the gate manifest explicitly excludes audit outputs.
- No broad reading of entire application or all historical sessions. Restrict data to approved StockFlash project/session IDs and last 2–3 comparable completed features. Never use `opencode auth export`, read tokens, or dump the full OpenCode database.
- An `export --sanitize` is **not proof of confidentiality**. Raw/sanitized exported session JSON may contain messages, code, customer data or secrets: keep it local, inspect via an allowlist parser that **drops content fields entirely**, publish only aggregate counts/metadata. Do not print raw export contents in model context.

## Deterministic analysis, not another model job

A short **non-LLM** parser/aggregator computes event counts, elapsed times, agent startup input (if metered), token/cache usage with explicit inclusion rules, context-per-turn time series, compaction frequency, longest observed dependency chain, repeated operations on identical fingerprints, and top delivered-output sizes. Tests must validate schema, timestamps, anonymization, accounting and safe omission of secrets. Do not require an AI model to fill tables, write JSONL, explain each event or run a semantic redaction pass.

The model's **only** audit output is a short interpretation of machine-computed findings, prioritized recommendations and unmeasured limitations, using the normal independent QA handoff (no extra token-expensive audit subagent). Identifying the critical path or duplicate commands cannot be asserted without reliable session/time/fingerprint linkage.

## Benchmark and baseline

1. **Before modifying further performance behavior**, collect 2–3 historical comparable runs, if metered stats and session linkage permit. Label the pre-refactor environment/model clearly. If only one useful historical run exists, treat it as a case study, not a baseline average.
2. Validate the observer with one tiny safe fixture/smoke. Measure observer overhead (CPU/wall/bytes/LLM calls), and compare passive statistics with exported sanitized aggregates when possible. If instrumentation cost is disproportionate, stay with session-level Lite mode.
3. Keep HOBS-001 focused on validating the observer and collecting existing history, NOT running an artificial full feature solely to fill every field. The next naturally approved feature supplies prospective telemetry. **Never run an extra LLM feature/QA cycle only to benchmark.**
4. Any claimed savings later require comparable tasks and the same model/configuration, preferably >=3 runs per condition where feasible. Where this is not practical, report directional findings/uncertainty only; never invent a precise percentage.

## Privacy and publication

- GitHub is the **already owner-approved project repository and publication destination**, not the same as exporting logs to Claude/another third-party AI. Publishing **sanitized derived metrics** to the existing private GitHub repo is in scope, after independent QA; direct uploads to outside AI services require explicit owner choice. A repository's privacy setting does not make raw transcripts safe to commit.
- Sanitizer is an **allowlist serializer**, not a regex-only redactor. Keep IDs, safe relative paths if justified, normalized command family, counts and durations. **Drop by construction** raw prompts/completions, hidden reasoning, command arguments, tool stdout/stderr, environment variables, keys, tokens, personal data, source code, email, filenames that could identify customers, and arbitrary free-form strings. Run deterministic fixtures with adversarial strings. Existing read-only QA verifies the final published manifest; no mandatory second LLM "semantic review" of every log line.
- Capturing raw hidden reasoning, private prompts, exposing OpenCode service/network, reading real `.env`/remote DB, changing Git/deploy permissions, installation, migrations/seeding or sending any raw session export to GitHub/outside AI is prohibited.
- Record what was unavailable: per-agent token counts, first-subagent-turn input, exact context sizes, inference time, parallel dependencies, tool output-to-model bytes, or full subagent coverage. **No metrics => no claims.**

## Completion / handoff

- Publish a short English analysis table with: `observed baseline runs`, coverage (events/session/subagents), per-agent request count, first-turn input tokens if measured, growth of input per turn, true tokens/cache usage, critical path/latency attribution where observed, repeated command evidence, instrumentation overhead and 3–5 ranked safe improvements. Add a baseline-vs-prospective comparison only when comparable measurements actually exist.
- OpenCode SFH1 RESULT links the sanitized artifacts, source SHA, actual coverage and `tokens=MEASURED|PARTIAL|UNAVAILABLE`, without copying raw data. ChatGPT reads those two files directly from GitHub and may separately supply the **owner-only** prompt for external review.
