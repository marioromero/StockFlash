---
name: stockflash-module-identity
description: Mantiene la marca comercial estable POS/Bodega/SyncWP/Fiscal/Compras/Analítica/Control.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-module-identity


Leer `docs/branding/module-identity-map.md`. Detectar producto activo por contexto comercial de navegación/entitlement, no por ruta interna; Caja y Precios dentro de POS siguen siendo POS. SyncWP marca WooCommerce, pero dominio genérico sigue `channels`. Control es app interna del fabricante. No mezclar permiso RBAC con entitlement comercial; ambos se verifican en servidor. Si no existe una autorización comercial aprobada, detener la funcionalidad.
