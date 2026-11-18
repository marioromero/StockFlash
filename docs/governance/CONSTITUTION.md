# StockFlash Development Constitution v1.2 — English agent policy

## Precedence
1. Approved v2.2 contracts: Part 1 business, Part 2 persistence, Annex integrations in `docs/product/`.
2. `docs/product/StockFlash-Control-SPEC-v0.1.md` governs only the independent central Control application and remains subordinate to v2.2/security.
3. Subsequently approved ADRs can explicitly override a narrow v2.2 rule; never do so implicitly.
4. Active APPROVED Feature Packets implement contracts; they do not change them.
5. v2.1 archives, Engram, prior handoffs and MEMORY are historical/contextual only.
6. Sections marked ABIERTAS and `docs/governance/DECISIONES-ABIERTAS-v2.2.md` require owner decisions. Unspecified limit semantics, fiscal rules and commercial access cannot be inferred.

## Product invariants
- One independent operational schema per customer installation. Central Control uses a separate database and never directly queries individual sales.
- Commercial products can be licensed independently; shared technical dependencies are not forced purchases. Enforce entitlements + activated capacity + RBAC and operating state on the server.
- Physical Kardex, stock reservations, cash/payment effects, idempotence, locks and integration boundaries must be transactional and auditable. Integrated DTE occurs only with fiscal authority/configuration. An external-mode receipt is not a tax document.
- Demo Vitrina is synthetic/resettable. Gratis Persistente holds real non-resettable data, with approved limits but still-open counting/exhaustion semantics.
- Sensitive information, blind indexes, license allowlists and privacy/security controls are not loosened for analytics, logs or token audits.
- Consistent accessible corporate design; only official approved brand assets. The owner and ChatGPT govern content; OpenCode implements and tests.
- No unapproved schema/contract/dependency changes. Independent QA, deterministic gates and exact-SHA CI before technical VERIFIED.
- Git publication only via authorized publisher after gate+review. Staging and production deployment require separate owner approval, backup/restore readiness and rollback. Git push never implies VERIFIED or deploy.

## Instruction language
The internal harness, including this Constitution and ChatGPT-only files, stays English. Spanish remains mandatory for owner-facing text, product UI and user/roadmap content. Do not translate exact file paths or machine status values.
