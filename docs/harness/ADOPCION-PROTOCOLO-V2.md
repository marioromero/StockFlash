# Claude Master Harness v2 adoption review — historical snapshot (2026-10-15)

**Status: historical evaluation, NOT a live instruction.** Follow AGENTS.md, WORKFLOW-EFFICIENCY.md and CURRENT.md instead.

The owner-supplied generic Claude v2 protocol proposed LITE/STANDARD/FULL modes, staged plans, decision classes, RUN-REPORT, queue-run, automatic VERIFIED, broad permissions, deferred security, per-agent model choices, harness versioning, stack research and handoffs.

StockFlash was already a FULL-scope project with approved v2.2, ADR-01..06 open, feature packets and real CI. Adopted principles: classify material decisions; preserve product-contract precedence; log deferred validations; use reproducible evidence; support short checkpointed handoffs; test model choices empirically; freeze workflow redesign unless a concrete defect or measured saving exists.

Not adopted: silently calling all specs final, auto-VERIFIED merely because code published, unrestricted `doom_loop` or filesystem permissions, postponing privacy/security to release, unaudited batch queues or model fallback. The owner later approved **ChatGPT-only technical VERIFIED** after original packet approval + exact-SHA gates/independent QA/CI; current AGENTS policy supersedes the 2026-10-15 view.

Version compatibility note: OpenCode configuration syntax/version must be checked against the **installed client** before migrating. Do not mix incompatible V1/V2 permission layouts. Source notes in historical git version; no runtime changes are authorized by this review.
