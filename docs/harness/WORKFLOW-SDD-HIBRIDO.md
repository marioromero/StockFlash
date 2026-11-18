# StockFlash hybrid SDD workflow — role separation

**Product authority:** owner decides NEW commercial/product/legal/security scope. ChatGPT chooses technical sequence and small packets derived from already-approved v2.2 with `approval_basis` and `new_business_decisions: NONE`. OpenCode implements within the active APPROVED packet, never edits product contracts by intuition.

**Canonical routine**: active packet + safe sync -> implementer + targeted tests -> all mandatory `sf_verify.py` gates on exact candidate -> independent read-only QA -> safe selective publisher -> GitHub CI exact SHA -> ChatGPT independent technical VERIFIED after reviewing acceptance/evidence. Max three correction rounds, with full final gate rerun after source change. No repeated owner approvals for ordinary authorized Git. No automatic deployment or remote DB access.

`/sf-run <ID>` delegates the routine. `/sf-plan`, `/sf-feature`, `/sf-review` and `/sf-publish` exist for standalone diagnostic or explicit phases but do not each reinitialize the entire workflow. Do not run setup diagnostics repeatedly. Use `docs/harness/MAPA-DE-LECTURA.md` and `WORKFLOW-EFFICIENCY.md` for selective context, and `AUDITORIA-TOKENS-IA.md` only when `audit=ON`.

**Escalation**: a genuinely unresolved ADR, new dependency/DB change or authorized-host operation requires a concrete owner choice and affects only dependent work. Handoff is through GitHub SFH1, manually triggered by owner `ok`; no background OpenCode-to-ChatGPT bridge.

**GPT-only ownership**: ChatGPT writes user guide semantic HTML, full roadmap HTML and its English continuity/context. OpenCode updates only technical metadata and integration, flags editorial gaps. User-facing text Spanish. Technical harness English.

**Output budget**: Ordinary successful OpenCode interaction says exactly `OK`; feature handoff/blocker in up to three short Spanish lines. Detailed evidence lives in GitHub, never omit actual tests or QA to shorten visible text.
