# StockFlash harness audit — 2026-11-17 (English)

## Scope and methodology
Reviewed the active governance and workflow surfaces: `AGENTS.md`, `LEEME-PRIMERO.md`, `docs/governance/CONSTITUTION.md`, `opencode.json`, all 5 `.opencode/agents/*.md`, all 9 `.opencode/commands/*.md`, all 11 StockFlash skill files, the 18 `docs/harness/*.md` in the pre-change tree, `scripts/sf_verify.py`, `scripts/safe_git_pull.py` and recent INSTALL-001 logs/QA summaries. Baseline code SHA `cbdfacab64263f93ce6a7d756e3074455b40de26`. **This is a static workflow audit, not measured token telemetry or an E2E OpenCode benchmark.**

## Findings and treatment
| ID | Severity | Evidence | Treatment |
|---|---|---|---|
| H01 | HIGH | AGENTS, sf-run, orchestrator, sf-feature and sf-publish repeat the same approval, sync and publish rules | Move canonical policies to AGENTS; keep short role-specific command instructions and cross-reference |
| H02 | HIGH | Reviewer R1 INSTALL-001 used 103 toolcalls / 9m 2s; reviewer reruns typecheck/build after full 32-gate verification (owner-provided local transcript) | Diff/finding-first QA; reuse fingerprinted deterministic evidence; independent targeted adversarial tests remain |
| H03 | MEDIUM | INSTALL/SETUP gate definitions run focused test folders AND complete PHP suite; separate reviewer reruns some commands | Retain existing mandatory gates; future packets should avoid duplicate test commands by design, never silently skip contracted gates |
| H04 | MEDIUM | Broad scans of 206 files and repeated accented-character searches examined valid Spanish Unicode as if suspicious | Restrict encoding scans to changed files; detect U+FFFD/invalid UTF-8 and mojibake, not ordinary accented letters |
| H05 | HIGH | `opencode.json` explicitly permits reading `*.env` / `*.env.*`, despite AGENTS disallowing reading real env | Changed `opencode.json` read rules to deny actual .env/.env.* with explicit example-file exception; effective local client permission precedence still requires a targeted runtime test |
| H06 | MEDIUM | `opencode.json` has broad bash allow / git push* allow at top-level while subagents attempt narrower deny; privilege semantics matter | Preserve proven publisher's exact `git push origin main`; test effective configuration before tightening generic patterns, never assume Markdown denies enforce sandbox |
| H07 | MEDIUM | Historical docs `HANDOFF-CHAT-02`, `SEGURIDAD-DE-AGENTES`, `RELEASE-CI-CD-PLAN` still describe BOOT as uncommitted / ask-only / CI absent, contradicting live CURRENT | Mark historical status explicitly, rewrite agent-facing material English, use CURRENT and current CI as truth |
| H08 | MEDIUM | Spanish text repeated across AGENTS, commands, skills, GPT context; large startup reads cost context | English-only concise operational instructions; short reading map and GPT-only language-lock file; preserve Spanish UI and product contracts |
| H09 | HIGH | First audit draft included an external-Claude prompt INSIDE repo, despite prompt being owner-facing | Supersede unmerged PR #27; keep only logging protocol in harness; deliver external prompt separately in chat |
| H10 | MEDIUM | Subagent events/true input-output token counts were not instrumented | Activate sanitized JSONL capture next task; null for unavailable token counters; no fake estimates or extra LLM call/event |
| H11 | MEDIUM | `sf-run` command and orchestrator each initiate safe sync; `sf-review` may sync at QA stage | Exactly one safe sync at orchestration start; on resume or standalone command confirm status, avoid unsafe hidden pulls with dirty tree |
| H12 | HIGH | Documentation-only git commits can change base SHA after successful prior CI | Require exact-SHA green 2/2 for editorial HEAD before issuing next packet; do not infer green from code SHA |
| H13 | LOW | Multiple planning/autonomy/old handoff docs suggest obsolete workflows | Keep archive as historical/non-authoritative and exclude from default reading; canonical active rules dominate |

## Protections deliberately retained
Approved packet and delegated owner boundaries; explicit commercial ADR gate; privacy/PII/secret redaction; RBAC, CSRF, locking and inventory invariants; independent read-only reviewer; mandatory final deterministic gates; scope-bound publisher with SHA fingerprints and fast-forward sync; GitHub CI 2/2 exact SHA; GPT-only guide/roadmap content ownership; remote DB/deploy/production restrictions; no destructive reset/stash/clean.

## Test coupling repaired
- First PR CI exposed stale checks: `scripts/check_contracts.py` expected a Spanish BOOT scaffold phrase and historic CI workflow naming; `DocsIntegrationTest` expected Spanish AGENTS/sf-run/sf-publish paragraphs. They were updated to assert the **current English safety and ownership contracts**, without skipping any feature test or final CI gate. This is a concrete example of documentation test brittleness that caused unnecessary rework.

## Open matters requiring runtime evidence
- True per-agent token usage is **UNKNOWN** until OpenCode/provider exposes metered usage. 125 toolcalls and 103 toolcalls are not tokens.
- Permission precedence in the installed OpenCode version and safe audit hooks require E2E tests before changing protection semantics.
- The audit cannot prove real cost savings without a comparable baseline and post-change feature. Do not promise a percentage.
- No background agent exists. Auditing begins when the next addressed OC turn is invoked with `audit=ON`.

## Next required evidence
An approved low-risk next feature run with `audit=ON`, sanitized per-event JSONL and a coverage-graded analysis MD. ChatGPT will compare genuine inefficiency and independent QA recheck necessity before proposing further code changes to the harness.
