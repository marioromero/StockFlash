---
description: Run an approved active packet with delegated implementation, deterministic gates, independent QA and safe publication.
agent: stockflash-orchestrator
---

This is the single routine entry point for approved active Feature Packet `$ARGUMENTS`. Delegate to `stockflash-orchestrator`. **The orchestrator performs the one required safe sync**, so do NOT add another automatic pre-command sync here.

Preflight: packet `status: APPROVED`; active in `docs/work/CURRENT.md`; base exact-SHA CI gate satisfied. STOP on mismatch, missing approval, real owner decision, unsafe remote DB or deployment need.

Delegate `stockflash-implementer` -> `py -3 scripts/sf_verify.py $ARGUMENTS` (all mandatory gates) -> read-only `stockflash-reviewer` -> `stockflash-publisher` after matching GATES_PASS and APROBADO. Default AUTO_GIT; only explicit OWNER_GATE needs separate authorization. Max three correction rounds. Checkpoint `docs/work/.run/$ARGUMENTS.json`. Reuse hashed evidence when unchanged; after code changes rerun all mandatory final gates and independent QA. Publisher checks Git HEAD/origin/main and CI exact SHA. No local `/init`, production, staging, real installation, secrets, DB operations or destructive Git.

If SFH1 includes `audit=ON`, follow `docs/harness/AUDITORIA-TOKENS-IA.md` **v2**: query supported native read-only project/session statistics FIRST, establish a 2–3-comparable-session baseline if available, and only then add low-overhead deterministic instrumentation for missing fields. **No agent-authored per-event/stage logs, no extra LLM audit agent, no raw session exports in context or Git.** Keep temporary data local/ignored and outside functional fingerprints; once, before independent QA, generate sanitized final JSONL (genuine observed events/session summaries only) plus computed analysis, explicitly mark unknown tokens/coverage, publish safely after gates+QA and report paths in SFH1.

User guide `public/docs/index.html`, roadmap `resources/roadmap/index.html` and ChatGPT-only context are GPT-authored: OpenCode only checks integration/metadata and flags pending updates. Follow `docs/harness/WORKFLOW-EFFICIENCY.md` to avoid duplicate reads/full QA tests. Owner-facing chat es-CL and normally exactly `OK`; full evidence remains in GitHub.

**After changing .opencode command definitions, do not assume an already running OpenCode session reloaded the file.** If stale behavior is proven, restart the CLI once on a clean checkpoint; do not force/lose work.
