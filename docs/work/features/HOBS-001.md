# HOBS-001 — Harness runtime verification and token/process observability

status: APPROVED
approval_basis: explicit_owner_request_2026-11-17
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: HARNESS
active: docs/work/CURRENT.md
audit: ON

## Purpose and authoritative policy

The owner explicitly requested a full agent-harness audit, English-only internal instructions, and a per-action process/token audit whose sanitized evidence ChatGPT reads directly in GitHub. Static refactor merged in PR #28; this packet completes **runtime verification and low-overhead instrumentation** in OpenCode. Sources: `AGENTS.md`, `docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md`, `docs/harness/WORKFLOW-EFFICIENCY.md`, `docs/harness/AUDITORIA-TOKENS-IA.md`, `docs/harness/HARNESS-AUDIT-20261117.md`, `docs/harness/GITHUB-HANDOFF-COMPACT.md`.

**Language policy**: every agent-facing instruction, harness/telemetry schema, internal GPT-only file, OpenCode agent, command, and skills remains **English**. Do NOT translate Spanish end-user UI, manual, roadmap HTML, exact paths, status codes, or commercial contracts.

## Scope (one safe diagnostic wave)

1. Read active packet and latest GitHub main SHA, perform `scripts/safe_git_pull.py` only once in the orchestrator, preserve all local changes. Verify installed OpenCode client/runtime version and actual role/permission resolution **without reading protected real files**. Verify `.env` access is effectively blocked and `.env.example` remains allowed using permission introspection/mock/path check, not by opening actual .env.
2. Select the **lowest-overhead observed mechanism** supported by the installed OpenCode runtime to capture primary-agent and ALL accessible subagent events/tool calls (native events/hooks or small deterministic local wrapper). Register subagent parent/span IDs, start/end, tool name, sanitized command/paths, time, output sizes, exit/status, stage, cycle, exact tree fingerprints; include provider-reported token usage per agent if surfaced. Never infer token count from Thought seconds or shell duration.
3. If safe native hooks are unavailable, declare explicit `coverage=summary_only` for opaque subagent calls, preserve toolcall counts, wall time and visible stage outputs. No fabricated per-event detail. Do not silently install a global plugin, invoke a network endpoint, auto-spawn agents or require a new model call for each event. Any optional hook must be **opt-in, testable, auditable, disabled by default**, and must not capture secrets, source body, hidden reasoning, private prompts or sensitive environment.
4. Collect a **representative test run** of this feature's own safe operations (targeted file reads, contract checks, deterministic gates, implementation/review/publication stages). Write append-only sanitized `docs/work/audits/TOKEN-AUDIT-HOBS-001-<YYYYMMDD>-<RUNID>.jsonl` and `...-ANALYSIS.md` with all fields/coverage limitations specified by the audit protocol. Instrumentation installed after a stage began cannot retroactively recreate its events; record missed coverage transparently.
5. Audit redundancy against actual observed events: duplicated safe sync, redundant rereads, recursive searches, repeated PHPUnit/typecheck/build, reviewer toolcalls and correction loops; distinguish required independent QA from avoidable duplicated LLM analysis. Report top costs by **real metered tokens** if available, otherwise by context output bytes and tool time clearly labeled proxy.
6. Implement minimal deterministic tests for logger/schema/secret-redaction, JSONL append-only parsing, duplicate span IDs, sanitizer behavior and coverage/unknown-token semantics. Do not edit core functional application, schema or v2.2 contracts. If runtime diagnostics reveal a policy/config conflict, fix only within this harness packet; submit exact proof and maintain existing role segregation/QA/CI.
7. Complete normal mandatory harness gates (define `HOBS-001` in `scripts/sf_verify.py` with static checks, sanitizer unit tests, contracts, full PHP suite, Node typecheck/build, diff/protected paths), independent QA, selective publisher and exact-SHA GitHub CI. Preserve all current test gates on other packets. Check docs guide/roadmap integration flags but do not rewrite GPT-owned semantic HTML.
8. User-facing OpenCode chat remains es-CL and minimal; SFH1 and internal evidence remain English. Send final result with audit artifact paths, observed number of agents/events, actual token coverage and limitations. An external Claude-review prompt is **not** part of repository files; ChatGPT supplies it to the owner separately in chat.

## Forbidden operations

No real `.env` reads/writes, credentials, keys, RUT/PII, customer data, network upload of logs, raw conversation transcripts, hidden chain of thought, GitHub secrets, remote DB, real installer, migration/seed, cron, cPanel, SSH/FTP deploy, force/reset/stash/clean, new commercial decisions or changes to owner-authored guide/roadmap HTML. No bypassing OpenCode permission denies. No weakening mandatory final gates, QA independence, code fingerprinting, exact-SHA CI or safe publisher.

## Acceptance criteria

- WHEN the installer/roles are loaded on the installed OpenCode version, THEN the effective configuration is checked using safe non-secret probes and the result is recorded, including any unresolved version/precedence ambiguity.
- WHEN `audit=ON` is received, THEN all **observable** main/subagent stages produce a structured append-only trace or an explicitly labeled partial-coverage summary. Unknown tokens remain null, not zero or guesses.
- WHEN two equivalent commands run on the same fingerprint, THEN their event IDs, output sizes, duration and duplication reason are reportable without dumping raw outputs.
- WHEN a command or model event could include secret data, THEN the log redacts or omits payload and tests demonstrate no secret-bearing raw output is published.
- WHEN the runner completes, THEN the English analysis can be read independently in GitHub and reused for a third-party critique with no private project source or hidden reasoning.
- WHEN source changes, THEN required final gate and independent QA evidence is revalidated on the new candidate, and the publisher commits only reviewed files.
- Owner receives a short completion handoff; ChatGPT will evaluate the logged evidence and decide any further harness optimizations without generic approvals.

## CI start gate

Do NOT begin implementation until GitHub Actions PHP/Node/smoke AND Docs contracts on the **exact main HEAD including this packet and current ledger** are both SUCCESS. On failure/PENDING, report real BLOCK, do not guess or start. This packet is a technical, local-only diagnostic; publication to GitHub is authorized, deployment/remote DB are not.
