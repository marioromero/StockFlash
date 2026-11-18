# HOBS-001 — Read-only OpenCode historical token baseline (Phase 0)

status: APPROVED
approval_basis: explicit_owner_request_2026-11-17; owner_followup_review_2026-11-18
new_business_decisions: NONE
publication_mode: NO_GIT_PHASE0
execution_mode: DIAGNOSTIC_READ_ONLY
stage: HARNESS
active: docs/work/CURRENT.md
audit: ON
audit_phase: 0

## Purpose

Before investing model tokens to build a collector, answer what existing OpenCode completed sessions can actually tell us: total token use, per-turn context growth, subagent startup cost, repetitive tools and critical-path latency. **Phase 0 is ONLY read-only diagnosis and an SFH1 issue comment**, not feature implementation, not a test-only synthetic run, and not a prerequisite to commit an audit file. `docs/harness/AUDITORIA-TOKENS-IA.md` v2.1 is authoritative. Next phases require a separate GPT-approved packet after reviewing the findings.

## Execute once, in order

1. Confirm latest `main` HEAD and GitHub PHP/Node/smoke + contract workflows **both SUCCESS at exact SHA**. If not, STOP/BLOCK. If code needs syncing, run `py -3 scripts/safe_git_pull.py` exactly once; on `SYNC_BLOCKED`, preserve all work and stop. Do not initiate `/sf-run` or delegate implementer/reviewer/publisher for a no-code diagnostic.
2. Check installed `opencode --version`, `opencode stats --help`, `opencode session --help` and available *safe* read-only options. Do not assume the currently published CLI flags apply to this installed version. Check effective English harness/permission metadata only through safe introspection, never open real `.env`, credentials or protected content.
3. Fetch **aggregate metered usage and session-list metadata** for the StockFlash project, using only approved read-only CLI commands. Select at most 2–3 **completed comparable feature sessions** if available: same provider/exact model and harness configuration; similar feature class, file count and changed LOC (rough heuristic 0.5×–2×); record differing gate footprint and QA correction rounds rather than masking them. If no matches, present cases separately and `baseline_not_comparable`. Never replay INSTALL-001 or a prior session.
4. Establish whether available local usage metadata *actually exposes* request-level tokens/timestamps, parent-child session linkage, first request input tokens per subagent, cache inclusion, compaction events and tool timing. For each record `available|not_exposed|not_checked`. Do **not** assert that `stats` aggregated output supplies per-turn data. Only if a supported metadata-only read is verifiably safe, inspect exact permitted numeric/timestamp/link fields; no raw session export or message content into model, Git or chat. If a parser would be needed, **do NOT write it in Phase 0**; report the gap to GPT.
5. Publish **one compact English `SFH1|kind=RESULT|phase=0` comment on issue #29** with CLI/version/commands, comparable session count, actual numeric totals where visible, per-metric coverage, time/critical-path limits, session privacy safeguards and Phase 1 recommendation. The issue comment is already on GitHub and serves as the baseline record. No audit JSONL/Markdown file in Phase 0; no Git commit, no publisher, no newly introduced gates, no synthetic new feature, no additional QA subprocess.

## Expected Phase 0 result

- Attributable measurement with explicit `source=CLI_STATS|SESSION_METADATA|UNAVAILABLE`, `coverage=session|stage|event|unknown`, `tokens=MEASURED|PARTIAL|UNAVAILABLE`, never invented estimates or guessed percentages.
- Report whether parent/child relationships, first subagent request, per-turn context and tool/CI wall-time exist in accessible metadata, even if unavailable.
- If source content/raw transcript would be needed, skip it; describe privacy-safe gap and decide no continuation until GPT reviews.
- State `scope=READ_ONLY_NATIVE_METADATA;parser=NOT_BUILT;code_changes=NO;commits=NO;next=GPT`. A no-code audit does not need a new functional gate/QA/publisher loop; all ordinary product gates/QA/CI remain mandatory for product code.

## Explicit prohibition

No modifying source, harness, .env, real database, installer, credentials, OpenCode global settings or project roles; no plugin installation, hooks, exports containing message text, new scripts, QA cycle, staging/deploy, cron, migrations, forced Git, git reset/stash/clean or touching GPT-authored Spanish HTML. Do not publish a raw session export or individual private message text. Keep owner chat succinct in Spanish and internal SFH1 English.
