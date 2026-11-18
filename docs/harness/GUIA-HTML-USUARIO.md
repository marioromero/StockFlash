# StockFlash user guide protocol (internal agent instructions in English)

**Owner decision 2026-11-10:** ChatGPT is the **exclusive author** of semantic user content at `public/docs/index.html`. The user guide itself is written in **Spanish (es-CL)**. OpenCode may integrate technical links/routes, validate HTML/JS/CSS, accessibility, navigation, responsive behavior, tests and publication, but may NOT write, translate, regenerate or expand the explanatory user-facing text or ask another agent to do so.

Canonical URL `/docs/`. The guide should explain actual verified options, buttons, fields, steps, permissions and accessible examples; support navigation/search/accordion/anchors/mobile and keyboard. Other legacy documentation directories do not replace this canonical guide. Do not invent unimplemented features or confuse local code with public deployment.

## Work cycle
1. At the beginning of each UI-visible feature inspect `docs/work/GUIA-HTML-ESTADO.md`, the active packet and real code. Note only affected pages/flows.
2. After implementation + gates + independent QA + code publication, compare feature SHA/routes against the guide's last coverage. If missing, record `GUIA_PENDIENTE_CHATGPT` in the technical ledger and SFH1 with feature, SHA, actual routes/components and brief user impact. **Do not block correct code publication just because GPT editorial content is pending.**
3. ChatGPT reads the real code/QA and edits semantic HTML; OpenCode validates technical integration, publishes approved edition, then updates ledger as `GUIA_ACTUALIZADA` with covered SHA.
4. Technical QA: exact links/anchors, keyboard/mobile search and navigation, GET /docs/, dashboard Help entry, es-CL text and no secrets/PII. If editorial-only HTML changes alter docs integration tests, ChatGPT repairs tests or arranges exact-SHA CI before another feature starts.
5. Neither ChatGPT nor OpenCode wakes the other automatically; owner manually types `ok` in addressed chat according to SFH1.

## Ownership boundaries
- ChatGPT-only English continuity: `docs/harness/CONTEXTO-CHATGPT.md` and `docs/harness/CHATGPT-ONLY-INSTRUCTIONS.md`.
- OpenCode-owned metadata: `docs/work/GUIA-HTML-ESTADO.md`.
- ChatGPT-owned user semantic content: `public/docs/index.html`, **Spanish**.
No OpenCode semantic changes to GPT-authored HTML, no invented guide status, no false claim of ChatGPT automation. Internal protocol text stays English.
