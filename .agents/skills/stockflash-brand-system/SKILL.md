---
name: stockflash-brand-system
description: Aplica la identidad corporativa y los PNG oficiales StockFlash en una pantalla o componente.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-brand-system


Leer `docs/branding/brand-contract.md` y `docs/branding/module-identity-map.md` SOLO cuando se modifique interfaz. Orden de consulta: 1. contrato (`docs/branding/brand-contract.md`), 2. mapa (`docs/branding/module-identity-map.md`), 3. inventario (`docs/branding/brand-assets.md`). Renderizar marca oficial si existe PNG; de lo contrario fallback textual de marca madre + etiqueta activa. Exigir que el asset exista en `docs/branding/brand-assets.md` y no presentarlo como oficial si el inventario lo marca provisional. Colores `#303846`, `#0856FF`, `#03C6BE` con contraste. La marca NO se recrea en CSS si existe asset oficial. No inventar paletas sin revisión.
Antes de finalizar: confirmar logo correcto en desktop/tablet y etiqueta visible. Si falta arte, informar y dejar placeholder textual, sin descargar un logo externo.
