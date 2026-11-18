---
name: stockflash-security-privacy
description: Review RBAC, PII/RUT, secrets, sessions, CSRF and audit access.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-security-privacy

Read relevant Part 1 §6, SEC-01..18, PRV-01..12 and exact Part 2 constraints. Enforce permissions server-side, sensitive-read audits, encrypted RUT/blind index, session/CSRF and fail-closed. No real secrets, PII, RUT, dumps, .env or hidden prompts in tests/memory/logs/token audits. Use synthetic staging data. Do not invent a generalized compliance product.
