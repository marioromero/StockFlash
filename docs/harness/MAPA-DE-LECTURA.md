# Selective Reading Map v2 — minimize context without losing evidence

1. First read `AGENTS.md`, the **active section** of `docs/work/CURRENT.md` and `docs/work/features/<ID>.md`. Expand only when an actual dependency exists.
2. Locate requirement IDs via `docs/requirements/INDEX-REQUISITOS.md`; open exact passages of v2.2, not all three large specs. Historical v2.1 never overrides v2.2.
3. Start with `git status` and changed + untracked file inventory. Read the patch and the smallest connected call graph, contracts and tests needed to understand behavior. A full repository recursive scan is an exception that requires a reason.
4. Skills are loaded only for matching tasks. Reviewer examines acceptance, diff, relevant dependencies, previously observed findings and test evidence, not the whole project by default.
5. Prior QA/handoff reports, `docs/harness/CONTEXTO-CHATGPT.md`, Control spec and Engram are NOT baseline reads for an ordinary implementation.
6. Expand if there is a real cross-module dependency, contract ambiguity, material security impact or missing requirement text; report the exact gap. Do not rebuild indices/module inventories each feature.
7. For token-efficient validation consult `docs/harness/WORKFLOW-EFFICIENCY.md`. Preserve original command stdout only locally when needed; pass short status + artifact pointer to agents.
8. Update this map only when a proven reusable reading pattern changes. **All harness instructions remain English; app UI/manual Spanish.**
