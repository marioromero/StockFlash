---
description: Independently review exact candidate diff, untracked files, acceptance and gate evidence.
agent: stockflash-reviewer
---

Run read-only `stockflash-reviewer` for `$ARGUMENTS`. In standalone use, ensure the safe-sync preflight is compatible with local dirty files; if orchestration already synced, **do not sync twice**. Never discard work.

Inventory tracked diff AND untracked files, check approved packet and same-tree deterministic gate report, inspect relevant paths/dependencies and security/UX. Reuse complete-suite/typecheck/build evidence **only** when exact fingerprints and exit status match; independently probe suspected defects with targeted tests. When re-reviewing, start from prior BLOCKER IDs and changed fixes, widen only for observed effects. Return APROBADO/CAMBIOS NECESARIOS with file/line and non-executed test disclosure. Never self-implement, change files, run real migrations or publish.

OpenCode cannot edit GPT-authored `public/docs/index.html` or `resources/roadmap/index.html`; flag stale sections through metadata. Apply English harness language policy. HOBS audit phases are closed; no additional audit collector or model-review pass is required.
