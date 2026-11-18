---
description: Implement an approved active packet and relevant tests only; no Git publication.
agent: stockflash-implementer
---

A standalone implementer entry point for approved active packet `$ARGUMENTS`. Sync safely **once** at standalone start using `py -3 scripts/safe_git_pull.py`; stop on SYNC_BLOCKED with work preserved. Under the orchestrator, use its already verified sync rather than repeating it.

Follow `AGENTS.md`, `docs/harness/MAPA-DE-LECTURA.md`, active packet and `WORKFLOW-EFFICIENCY.md`. Implement only approved scope, with targeted tests and checkpoints; do not stop after an initial diagnostic subtask if approved functional work remains. No new dependencies, schema/fiscal/brand/contract changes without decision. BOOT-001 bootstrap is historical, not an instruction to recreate Laravel.

No commit/push/deploy, real .env/DB/installer/cron/SSH/FTP operations or contract edits. Respect denied tools; approval for ordinary allowed calls is already granted. After functional changes, flag real user-guide/roadmap semantic gaps for ChatGPT via technical ledgers and SFH1 without writing the HTML. If `audit=ON`, collect only observable events under the approved audit protocol. Internal instructions English; visible UI es-CL.
