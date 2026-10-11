---
name: stockflash-brand-system
description: Aplica la identidad corporativa y los PNG oficiales StockFlash en una pantalla o componente.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-brand-system


Leer `docs/branding/brand-contract.md` y `docs/branding/module-identity-map.md` SOLO cuando se modifique interfaz. Renderizar marca oficial si existe PNG; de lo contrario fallback textual de marca madre + etiqueta activa. Colores `#303846`, `#0856FF`, `#03C6BE` con contraste. La marca NO se recrea en CSS si existe asset oficial. No inventar paletas sin revisión.
Antes de finalizar: confirmar logo correcto en desktop/tablet y etiqueta visible. Si falta arte, informar y dejar placeholder textual, sin descargar un logo externo.
