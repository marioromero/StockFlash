# StockFlash — Read Me First (agent instructions in English)

This repository contains the StockFlash product contract, active work packets and OpenCode harness. `main` is the code source; GitHub CI verification is distinct from staging/production deployment. **Always read current reality from `docs/work/CURRENT.md` and latest GitHub SHA**, not from an old historical handoff.

## Source-of-truth order
1. `AGENTS.md` and `docs/governance/CONSTITUTION.md` (policy and precedence).
2. v2.2 business, data and integration contracts in `docs/product/`; historical v2.1 does not override them.
3. `docs/governance/DECISIONES-ABIERTAS-v2.2.md` for still-open product decisions; never invent them.
4. Approved active feature packet and `docs/work/CURRENT.md` for implementation scope.
5. `docs/harness/MAPA-DE-LECTURA.md` for selective reading, `WORKFLOW-EFFICIENCY.md` for economical QA.

## Approved commercial baseline
StockFlash products: POS, Bodega, SyncWP, Fiscal, Compras, Analítica. Control is internal. Commercial entitlements are separate from technical dependencies. Demo Vitrina uses synthetic resettable data; Gratis Persistente uses real persistent data, with 50 sellable variants and 500 monthly sales, subject to open counting rules. First commercial launch targets POS+Bodega without integrated DTE issuance; external fiscal references are optional. UI and support text remain es-CL even though this harness is English.

## Operating method
- OpenCode works only from an active APPROVED packet, with orchestrator, implementer, independent read-only reviewer and publisher.
- `/sf-run <ID>` is the routine delegated workflow. `/sf-plan` or `/sf-feature` apply only if needed. `/sf-diagnostico` is not repeated on every feature.
- Sync safely via `scripts/safe_git_pull.py`; never use reset, stash or clean to bypass conflicts. Preserve local changes.
- QA, Git publication, CI and technical VERIFIED are separate events. Publishing code does not authorize deployment or remote DB activity.
- The user manually relays `ok` between assistants using the active SFH1 GitHub issue; no unattended cross-chat orchestration.
- Optional next-cycle token audit is specified in `docs/harness/AUDITORIA-TOKENS-IA.md`; external review prompts are delivered to the owner OUTSIDE the repository.

## Security
No secrets, personal data, real `.env` reads, SSH/FTP credentials, production DB access, real migration/seeding or deploy without separate explicit approval. Development/test evidence must be sanitized. Protect /roadmap, /install and /setup as contracted. Do not use `/init` or run cPanel/SSH deploy as a side effect of ordinary code work.

## Language boundary
Keep every agent-facing harness instruction English, including GPT-only text. Do not translate filenames, commands, machine-readable statuses or Spanish content in `docs/product/`, end-user documentation, application UI or roadmap HTML.
