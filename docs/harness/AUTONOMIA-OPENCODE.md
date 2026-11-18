# OpenCode autonomy — historical findings and live boundary

This file originated in October 2026, before delegated publisher and SFH1 were proven end-to-end. **Its old "not yet automated" conclusions are obsolete.** Current operating authority: AGENTS.md, `.opencode/agents/stockflash-orchestrator.md`, CURRENT.md, and actual SHA/CI.

The implemented workflow uses an APPROVED/active Feature Packet, safe fast-forward sync, implementer, deterministic `sf_verify.py`, independent read-only reviewer, bounded corrections (three), publisher with selective Git commit/push, exact SHA GitHub CI and ChatGPT technical VERIFIED decision. OpenCode does **not** independently choose new commercial features or receive permanent authorization for deployment/remote DB.

An unattended dispatcher/queue, GitHub Action running OpenCode, model routing or `session.idle` hook is **not** installed merely because the CLI exposes relevant APIs. Such mechanisms would need an explicit security/privacy/permission review and evidence that they cannot create loops or bypass approvals. The user currently manually triggers the destination assistant with `ok` on SFH1.

Do not reinstall launchers, change the CLI schema or spin up background agents to "optimize" this project. For efficiency and observability use WORKFLOW-EFFICIENCY.md and AUDITORIA-TOKENS-IA.md, with an explicit audit=ON handoff.
