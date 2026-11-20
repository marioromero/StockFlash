# StockFlash owner roadmap HTML protocol (internal English)

**Owner decision 2026-11-10.** ChatGPT exclusively writes and edits **ALL** `resources/roadmap/index.html` content, design, milestone states and scripts; user-visible roadmap remains Spanish (es-CL). OpenCode may implement and test route, auth, dashboard link and update `docs/work/ROADMAP-HTML-ESTADO.md` technical metadata ONLY. Never generate the roadmap HTML from OpenCode, even with a script.

Owner superseding layout decision (2026-11-20): show ONLY a minimal metrics strip (verified requirements count/total and derived percentage, plus P0 subset), then a single chronological collapsible R3–R10 traceability timeline with nested module → feature → subdetail. No hero descriptions, duplicated summary cards, search/filters, launch cards or other sections. Derive percentages exclusively as VERIFIED requirement IDs / total IDs, NOT time, work effort or commercial readiness. Product foundational R0–R2 is outside this v2.2 requirement denominator. Preserve exact ID coverage, classify ARQ/SEC/BKP/UPD/LIC separately (not as Demo), and update states only after verified evidence. A feature VERIFIED technically is NOT sold/deployed.

## Access and validation
- **Owner superseding decision (2026-11-20): GET /roadmap is public read-only**, no login or `settings.manage` gate. Keep the source in `resources/roadmap/index.html` and expose through the exact public GET route only. No stateful/API/private/admin data may enter the HTML.
- `/docs/` remains independently public as already implemented. `/setup` is a one-time installation assistant and MUST remain protected against reopening after data exists; its unavailable page may link to the public roadmap/manual.
- Validate unauthenticated 200 + content type on /roadmap and /docs/, server route outside auth middleware, public metadata and R3–R10 hierarchical native details, precise ID counts/verified-ratio math, R7 pilot goal, mobile readability, no secrets/PII or external resources. No false production/verified claim.

## Closing a feature
1. OpenCode compares feature SHA and changes against `docs/work/ROADMAP-HTML-ESTADO.md`; if milestones/capabilities changed, flag `ROADMAP_PENDIENTE_CHATGPT` with actual SHA, gate/QA/CI state and verifiable changes. Do not touch roadmap HTML.
2. When owner manually triggers ChatGPT with `ok`, ChatGPT checks real GitHub evidence and writes substantive roadmap revisions, updating its English continuity when relevant.
3. OpenCode tests/validates/publishes the exact ChatGPT HTML and records `ROADMAP_ACTUALIZADO` with covered SHA. Pending editorial update does not block correct code publication, but remains visible in the technical ledger/SFH1.
4. A roadmap edit cannot grant a new feature VERIFIED status. No automatic cross-chat wakeup or background polling.

All agent instructions in this protocol remain English; the owner-facing page remains Spanish.
