---
name: stockflash-module-identity
description: Keep commercial product identities and authorization separate.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-module-identity

Consult docs/branding/module-identity-map.md only when product identity matters. Commercial products POS/Bodega/SyncWP/Fiscal/Compras/Analítica; Control internal; Caja/Precios are POS functions. Determine current product from licensed entitlement and navigation context, not URL alone. RBAC plus commercial entitlement must be checked server-side; an unapproved product capability blocks only its dependent feature.
