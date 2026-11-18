# StockFlash owner roadmap HTML protocol (internal English)

**Owner decision 2026-11-10.** ChatGPT exclusively writes and edits **ALL** `resources/roadmap/index.html` content, design, milestone states and scripts; user-visible roadmap remains Spanish (es-CL). OpenCode may implement and test route, auth, dashboard link and update `docs/work/ROADMAP-HTML-ESTADO.md` technical metadata ONLY. Never generate the roadmap HTML from OpenCode, even with a script.

The roadmap visualizes evidence-based R0–R10 states, what is implemented/verified, what remains, and the first commercial R7 POS+Bodega release without integrated DTE. Do not invent completion percentages, dates, costs or commitments. A feature VERIFIED technically is NOT by itself sold/deployed.

## Access and validation
- HTML source outside `public/` because the route is **private**. `GET /roadmap` requires auth, password-change/session checks and server-side `settings.manage` permission. Unauthorized users must not access it as a static file.
- Dashboard link is shown only to users with access. `/docs/` remains public and independent.
- Validate GET authorization/redirect/403, R0–R10, R7 commercial target, accessible expand/collapse, mobile readability, no externally loaded secrets/resources.

## Closing a feature
1. OpenCode compares feature SHA and changes against `docs/work/ROADMAP-HTML-ESTADO.md`; if milestones/capabilities changed, flag `ROADMAP_PENDIENTE_CHATGPT` with actual SHA, gate/QA/CI state and verifiable changes. Do not touch roadmap HTML.
2. When owner manually triggers ChatGPT with `ok`, ChatGPT checks real GitHub evidence and writes substantive roadmap revisions, updating its English continuity when relevant.
3. OpenCode tests/validates/publishes the exact ChatGPT HTML and records `ROADMAP_ACTUALIZADO` with covered SHA. Pending editorial update does not block correct code publication, but remains visible in the technical ledger/SFH1.
4. A roadmap edit cannot grant a new feature VERIFIED status. No automatic cross-chat wakeup or background polling.

All agent instructions in this protocol remain English; the owner-facing page remains Spanish.
