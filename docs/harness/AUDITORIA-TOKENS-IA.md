# StockFlash — Passive token/context/latency audit v2

**Activation:** approved `audit=ON` packet (HOBS-001). Internal instructions English; owner-facing output Spanish. **No historical replay:** read-only analysis of existing completed sessions is allowed. An external AI review prompt belongs to the owner, NOT Git.

## 1. Measurement order — do not make the audit consume more tokens

1. Check **installed** `opencode --version` and `--help`; commands available in current official docs include `opencode stats --project . --json`, `opencode session list --format json`, `opencode session export <ID> --sanitize`, and `opencode debug paths db`, **but installed flags may differ**. Prefer aggregate stats and bounded metadata. A `--sanitize` export may still contain sensitive text; never copy raw export into an LLM prompt or Git.
2. Read **only 2–3 recent comparable completed StockFlash feature sessions** when permitted/safely accessible, including linked subagents where possible. Use native statistics first; if insufficient, a read-only metadata-only local parser with explicit project/session filter. No full DB dump, replay, hidden reasoning or raw session transcripts. n=1 is descriptive, not a statistical baseline.
3. Add a tiny **external-to-model** observer/parser only for critical missing metrics. Prefer native usage/events; optional local hook only after version/security verification. Do **not** ask any agent/subagent to write tool-by-tool JSONL, stage journals or an LLM analysis per event. No automatic plugin installations, global configuration changes, network service or extra model calls for logging.
4. **Lite fallback:** if native per-event data is unavailable, report real session/stage aggregates and `coverage=session|stage|unknown`; never fabricate individual events, tokens, durations or coverage.

## 2. Priority measurements

| Priority | Metric | Interpretation |
|---|---|---|
| P0 | Metered input/output/cache/reasoning tokens **per turn**, model and session, if exposed | Track total context input over turns, first-turn subagent input (startup overhead), peak/median/p90, compactions. Cached tokens may already be included in input; do not double count parent/child totals |
| P1 | Wall-clock start/end, model inference, tools/tests, QA, CI, waits and parent/child spans | Calculate observed **critical path**, not a sum of parallel subagents. Model/idle time remains unknown without valid timestamps; `Thought: 352ms` is not a token count |
| P2 | Repeated reads/globs/commands/gates, same input fingerprint, output **actually delivered to model** | Flag preventable repeats separately from independent QA and reruns after source changes. Shell stdout/file size is only a proxy when model-delivery size cannot be measured |

Use only provider-reported prices for costs. No speculative savings percentages.

## 3. Minimal JSONL v2 schema

Each observed record: `v:2`, `run`, `span` (unique), `parent` (nullable), `agent` (anonymous ID/role), `stage`, `kind` (model|tool|subagent|gate|ci|session), `t0`, `t1` (UTC or null), `status`, `source` (native_usage|native_event|cli_stats|read_only_history|safe_hook|derived_summary), `coverage` (event|stage|session|unknown).

**Optional only if observed:** `ms`, `model`, `usage:{input,output,cache_read,cache_write,reasoning,total,cost_usd}`, `first_turn`, `request_ordinal`, `compaction`, `operation_id` (normalized allowlist, no raw arguments), `source_fingerprint`, `output_to_model_bytes`, `exit_code`, `overlap_group`, `accounting:inclusive|exclusive|unknown`. Missing values: null/omit, never zero by guess. No subjective fields such as `risk_class` or `must_keep` in raw events.

Valid **derived stage summary** when no per-tool visibility (must not be presented as individual tool telemetry):

```json
{"v":2,"run":"HOBS001-R1","span":"s1","parent":null,"agent":"reviewer#1","stage":"qa","kind":"subagent","t0":null,"t1":null,"status":"completed","source":"derived_summary","coverage":"stage","ms":null,"usage":null}
```

## 4. No working-tree or privacy contamination

- Keep temporary raw/metadata files **outside tracked work**: permitted local OS temp preferred; otherwise ignored `docs/work/.run/audits/<RUNID>/` (no permission widening). Exclude temporary audit paths from **functional fingerprints**, but not from security checks. Do not commit per-event checkpoints.
- One **deterministic post-run** parser writes sanitized final `docs/work/audits/TOKEN-AUDIT-<FEATURE>-<DATE>-<RUNID>.jsonl` and `...-ANALYSIS.md` **once before QA/publish**; if event data is absent, write only honest actual session/stage summaries. Include final artifacts in the explicit QA/publisher manifest. Changing audit-only output after QA requires re-review of that output, not automatic rerun of unchanged functional tests when gate definitions explicitly allow it.
- Use a strict **allowlist serializer**, not just regex redaction. Publish safe anonymous identifiers, measured numbers, timing, normalized command category and verified coverage only. Never serialize prompts, completions, raw export JSON, source bodies, tool stdout/stderr, secret-bearing arguments, real `.env`, keys, credentials, RUT/PII, customer paths, hidden reasoning or arbitrary free text. Test against adversarial fixtures. No second model-based "semantic redaction" pass; existing independent reviewer validates safe outputs.
- The **existing private GitHub repo** is an already owner-approved destination for sanitized derived metrics. A raw transcript is unsafe there. Sending even sanitized reports directly to Claude/another provider is a **separate owner decision**, not authorized by Git publication.

## 5. Deterministic aggregation and evaluation

The parser (not an LLM) calculates: actual request/agent counts, context growth per turn, first subagent request input if metered, cache-inclusive/exclusive rules, compactions, durations and overlapping critical path where provable, repeated operation/fingerprint pairs, model-visible bytes (or clearly labeled proxy), and missing coverage. It also measures **observer overhead** (local elapsed/CPU/bytes/model calls if measurable). No model fills tables or invents time spans. LLM gives only a short interpretation of deterministic findings and 3–5 prioritized improvements.

Baseline: prefer 2–3 existing comparable runs **before** changing further performance behavior. Test the collector with a tiny safe fixture; collect prospective results during the next **naturally required** feature, not an artificial full run. A credible savings claim later needs same model/config and comparable tasks, ideally >=3 runs per condition; otherwise use directional conclusions with limitations.

Completion: normal QA + required gates + publisher + exact-SHA CI preserved. SFH1 RESULT reports sanitized artifact paths, measured/partial/unavailable tokens, real coverage, baseline n and chief unknowns. ChatGPT reads those files directly from GitHub. No new business/remote DB/installer/deploy/secret permission follows from `audit=ON`.
