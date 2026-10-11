# StockFlash — memoria breve, no normativa (2026-10-10)
- Owner aprobó POS y Bodega comercializables por separado desde primer lanzamiento; próximos SyncWP, Fiscal, Compras y Analítica.
- Demo Vitrina reseteable ficticia y Gratis Persistente real con 50 variantes vendibles / 500 ventas mensuales; política exacta aún ABIERTA.
- Se aceptó registro de venta y caja sin DTE integrado; documento fiscal externo opcional, nunca declarar emisión verificada automáticamente.
- StockFlash Control es plataforma central del fabricante con suscripciones/cobro manual.
- Brand StockFlash grafito `#303846`, Flash azul `#0856FF`; etiqueta POS turquesa `#03C6BE`; PNG en `public/images/brand/` suministrados por el owner.
- Fuentes vigentes: docs/product/especificacion-v2.2-*CONSOLIDADO-EN-REVISION.md. v2.1 es histórica.
- Arquitectura: Laravel + Vue 3/Inertia + MariaDB + hosting compartido cPanel, staging aislado, desarrollo en Warp/OpenCode.
- No enviar secretos, ventas, RUT ni PII a agentes/modelos/Engram. Sin autorización para release ni cambios de contrato.
