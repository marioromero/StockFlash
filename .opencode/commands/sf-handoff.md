---
description: Produce an evidence-linked compact SFH1 handoff without duplicating reports.
agent: plan
---

Read current active packet, Git SHA, executed gates/reviewer reports and exact-SHA CI; do not claim unverified data. Emit concise English SFH1 issue comment with `src`, `dst`, increasing `i`, feature, SHA, gate/QA/CI and safe evidence paths. If guide/roadmap changed, report `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT` and affected routes; do not author GPT-owned HTML or ChatGPT continuity. If `audit=ON;phase=0`, return only observed native-usage metrics, supported command/version, comparable session count, explicit coverage gaps and `next=GPT` in SFH1; **no JSONL file exists or is required**. Artifact paths apply only to separately approved prospective Phase 2 after the feature's original CI, published as an audit-only follow-up with its own review/CI. User-visible chat normally `OK`, or 1–3 short es-CL lines when a real handoff/blocker needs action. No full log or secret data pasted to chat.
