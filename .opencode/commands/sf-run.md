---
description: Run an approved active packet with delegated implementation, deterministic gates, independent QA and safe publication.
agent: stockflash-orchestrator
---

This is the single routine entry point for approved active Feature Packet `$ARGUMENTS`. Delegate to `stockflash-orchestrator`. **The orchestrator performs the one required safe sync**, so do NOT add another automatic pre-command sync here.

Preflight: packet `status: APPROVED`; active in `docs/work/CURRENT.md`; base exact-SHA CI gate satisfied. STOP on mismatch, missing approval, real owner decision, unsafe remote DB or deployment need.

Delegate `stockflash-implementer` -> `py -3 scripts/sf_verify.py $ARGUMENTS` (all mandatory gates) -> read-only `stockflash-reviewer` -> `stockflash-publisher` after matching GATES_PASS and APROBADO. Default AUTO_GIT; only explicit OWNER_GATE needs separate authorization. Max three correction rounds. Checkpoint `docs/work/.run/$ARGUMENTS.json`. Reuse hashed evidence when unchanged; after code changes rerun all mandatory final gates and independent QA. Publisher checks Git HEAD/origin/main and CI exact SHA. No local `/init`, production, staging, real installation, secrets, DB operations or destructive Git.

Run `py -3 scripts/sf_timeline.py begin <ID> <stage> --actor orchestrator ...` / `end ... --status ...` around delegated implementation, verification, QA, correction, publication and CI wait. `sf_timeline.py status <ID>` with the checkpoint is the read-only recovery aid for truncated context; telemetry never replaces the checkpoint/gates and its failure neither blocks nor unblocks work.

**Audit exception:** HOBS-001 has `audit=ON;phase=0;execution_mode=DIAGNOSTIC_READ_ONLY`. It must NOT use `/sf-run` or delegate the implementer/reviewer/publisher; OpenCode performs safe CLI stats and returns one SFH1 RESULT with real numbers/gaps, no commits. For future separately approved Phase 2, use `docs/harness/AUDITORIA-TOKENS-IA.md` **v2.1**: read-only historical baseline first, later passive prospective observation in local ignored storage, and a separate **post-original-feature-CI** audit-only publication. Never place audit outputs in the code feature's gate/publisher fingerprint. No per-event LLM logging, raw transcripts or fabricated metrics.

User guide `public/docs/index.html`, roadmap `resources/roadmap/index.html` and ChatGPT-only context are GPT-authored: OpenCode only checks integration/metadata and flags pending updates. Follow `docs/harness/WORKFLOW-EFFICIENCY.md` to avoid duplicate reads/full QA tests. Owner-facing chat es-CL and normally exactly `OK`; full evidence remains in GitHub.

**After changing .opencode command definitions, do not assume an already running OpenCode session reloaded the file.** If stale behavior is proven, restart the CLI once on a clean checkpoint; do not force/lose work.
