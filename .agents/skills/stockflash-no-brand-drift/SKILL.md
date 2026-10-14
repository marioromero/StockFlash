---
name: stockflash-no-brand-drift
description: Revisa la consistencia visual entre pantallas y denuncia desviaciones de marca.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-no-brand-drift


Aplicar revisión sobre diff y capturas: logo oficial, etiqueta de producto, tokens compartidos, fuentes, tamaños táctiles, estados, foco, contrastes, layouts. Orden de consulta: 1. contrato (`docs/branding/brand-contract.md`), 2. mapa (`docs/branding/module-identity-map.md`), 3. inventario (`docs/branding/brand-assets.md`). Exigir que el asset exista en `docs/branding/brand-assets.md` y no presentarlo como oficial si el inventario lo marca provisional. Devolver PASS/BLOCKER con evidencias y archivos; NO cambiar assets ni logo, NO aprobar por intuición. Se usa principalmente desde reviewer cuando hay cambios UI.
