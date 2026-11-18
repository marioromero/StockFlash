# HOBS-001 — Read-only baseline and lightweight OpenCode usage audit

status: APPROVED
approval_basis: explicit_owner_request_2026-11-17
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: HARNESS
active: docs/work/CURRENT.md
audit: ON

## Outcome

Validate the new English-only StockFlash harness on the installed OpenCode version; establish a **passive, reproducible, privacy-safe baseline** of token usage, context growth, subagent startup overhead and elapsed-time bottlenecks. Only add instrumentation when supported native session metadata cannot answer an important question. Produce machine-generated summary and sanitized metrics for ChatGPT/owner; external AI prompt stays OUTSIDE the repository.

Canonical policy: `AGENTS.md`, `docs/harness/AUDITORIA-TOKENS-IA.md` **v2**, `docs/harness/WORKFLOW-EFFICIENCY.md`, `docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md`. All agent-facing instructions in English; UI/manual/owner replies Spanish.

## Mandatory sequence (one safe wave)

1. **Hard start gate:** verify both current `main` GitHub workflows (PHP/Node/smoke AND contracts) SUCCESS at the exact latest HEAD. If pending/failing, send BLOCK (do not claim green). Perform one safe `scripts/safe_git_pull.py` in orchestrator; on `SYNC_BLOCKED`, stop without reset/stash/clean.
2. **Version/capability probe:** `opencode --version`, installed `opencode stats --help`, `opencode session --help`, `opencode debug --help`, config/agent introspection if supported; do not assume flags from current online docs match the local binary. Check effective .env deny and .env.example allow **only via safe mock/introspection**, never by opening real .env/credentials. Do not update global OpenCode config or install packages.
3. **Historical baseline FIRST:** try native read-only aggregated stats scoped to StockFlash and at most 2–3 recent *completed comparable* feature sessions, including their child sessions if supported. Use **read-only local session metadata** only if native stats are insufficient and safe. Export/session JSON is never committed or copied to model context; a `--sanitize` option alone is insufficient. Allowlist-parser extracts numeric telemetry, minimal timestamps, anonymous session linkage and normalized operations; drops content fields. If only one run exists, label as case study; if no usable history, state `UNAVAILABLE`. Do not recreate or replay INSTALL-001.
4. **Determine gaps:** measure actual per-turn request input/output/cache, context growth, compactions, first request input of each new subagent, wall time and inference-vs-tool/QA/CI critical path **where observable**. Unknown data stays null, not zero. No guessed tokens from Thought seconds or toolcall count.
5. **Observer implementation only if useful:** prefer native event/meter interfaces or a small bounded post-run deterministic parser; optional local hook only after verified installed-version compatibility and explicit isolation. No LLM-generated log entries, manual stage batching, per-event prompts or tool-output interception that sends raw data. If neither safe method exists, settle on **Lite session-level metrics** and report missing coverage.
6. **Temporary files never contaminate verified diffs:** keep raw transient data in a permitted local temp location or the ignored `docs/work/.run/audits/<RUNID>/`; exclude these from functional fingerprints. Do not create tracked audit JSONL during working implementation/QA. Generate final allowlisted sanitized `docs/work/audits/TOKEN-AUDIT-HOBS-001-<DATE>-<RUNID>.jsonl` and `...-ANALYSIS.md` **once** before final review/publish; if granular events were unavailable, the JSONL may contain only real session/stage aggregate events and must explicitly say so.
7. **Deterministic aggregation:** a short non-LLM utility produces counts, model request/context trends, startup token data if available, wall-time breakdown, repeat operations/fingerprints, observer overhead, coverage and limitations. Avoid a separate LLM audit reviewer or mandatory model-based semantic redaction. The independent normal QA reviewer validates output policy, sanitizer tests and schema. Model interpretation is one brief conclusion only, not per-record annotation.
8. **Tests and publishing:** add minimal bounded parser/sanitizer tests including malicious content and missing/null token fields. Define `HOBS-001` gates in `scripts/sf_verify.py` (contract checks, parser tests, approved full PHP suite, Node typecheck/build, protected file/source manifest and diff-check). Preserve all existing packet gates and independent QA. Publisher selects authorized files and verifies CI on exact published SHA; no changes to app runtime, schema, commercial contracts, GPT-owned guide/roadmap HTML.
9. **Handoff:** English SFH1 RESULT reports exact SHA, sanitized JSONL+analysis paths, number of historical sessions observed, real metered token coverage, context-growth/latency data availability, observer cost and key uncertainty. Owner-facing chat remains minimal es-CL.

## Acceptance and limitations

- Historical past session data is read **only**, never rewritten or replayed. No full-session raw transcript, prompts, hidden reasoning or unbounded session DB dumps are published or handed to the model.
- A **minimal JSONL v2** (required identifiers/timing/role/event/source/coverage; optional measurements) validates deterministically, with unknown usage null. All data marked `source` and honest coverage; per-subagent/token details only if exposed by native client/provider.
- Critical-path reporting accounts for parallel overlap; unknown inference/queue spans remain unknown, never estimated as elapsed minus tool time.
- Reviewer sees exact evidence and checks sanitizer without performing a duplicate full model-based audit; mandatory feature gates remain intact.
- Baseline 2–3 comparable runs **when available**; n=1 is descriptive only, never evidence of a percentage saving. No artificial extra full feature or independent model review solely to populate measurements.
- Working-tree fingerprints remain immune to temporary audit output; final sanitized results undergo QA and explicit publisher staging.
- No cost, permissions or safety claims made without real measurements; never advertise a made-up 80% saving.

## Prohibitions

No real `.env` reads/writes, credentials, keys, RUT/PII, source transcripts, network export, arbitrary global plugin, vendor installation, service exposure, hidden chain-of-thought, remote DB, installer execution, migrations/seeds, cron, SSH/FTP/cPanel/deploy, force/reset/stash/clean or weakening of exact-SHA CI/independent QA. GitHub publication is limited to sanitised derived audit facts and authorized code/tests. Exporting the sanitized report beyond the owner-approved private GitHub repo requires the owner's separate decision.
