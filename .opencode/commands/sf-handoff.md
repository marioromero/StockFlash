---
description: Produce an evidence-linked compact SFH1 handoff without duplicating reports.
agent: plan
---

Read current active packet, Git SHA, executed gates/reviewer reports and exact-SHA CI; do not claim unverified data. Emit concise English SFH1 issue comment with `src`, `dst`, increasing `i`, feature, SHA, gate/QA/CI and safe evidence paths. If guide/roadmap changed, report `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT` and affected routes; do not author GPT-owned HTML or ChatGPT continuity. If `audit=ON`, include sanitized audit JSONL + analysis paths and coverage. User-visible chat normally `OK`, or 1–3 short es-CL lines when a real handoff/blocker needs action. No full log or secret data pasted to chat.
