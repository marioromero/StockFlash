# CATPROD-001 — Cierre técnico externo ChatGPT (2026-11-17)

**VERDICT: VERIFIED técnico**, no desplegado. El cierre se fundamenta en la delegación y el Feature Packet aprobados; no requiere una segunda aprobación del propietario.

- **Código publicado y revisado:** `c8e1338c5814a9c8741cf1a010cb1346636638a7` (GitHub `main`), que corrige H-01/H-02/H-03 de `docs/work/CATPROD-001-EXTERNAL-REVIEW-20261117.md`.
- **Alcance confirmado:** admin `/catalog/products`, productos simples/variables, combos solo como dato P0, variantes, SKU y código interno únicos, barcodes globalmente únicos y primario por variante, atributos/tags, costos protegidos, RN-70, transacciones/auditoría, búsqueda y paginación. Origen del alta manual fijado por servidor a `pos` y sin edición arbitraria; precio neto/margen/markup calculados según RN-25; calculadora por objetivo de margen/markup CAT-04 bajo `catalog.view_cost`. No POS, DTE, inventario físico, ventas ni integración de canales.
- **QA independiente:** `docs/work/CATPROD-001-REVIEW-20261117-R2.md` — `APROBADO`, cero bloqueantes. Verificación local informada y reproducida por reviewer: `GATES_PASS 19/19`, catálogo `116 passed / 1810 assertions`, suite PHP `476 passed / 4192 assertions`, `npm run typecheck` y `npm run build` PASS.
- **GitHub CI exacta:** 
  - [PHP 8.3 + Node 24 + smoke GET /](https://github.com/marioromero/StockFlash/actions/runs/44142115506): tres jobs `completed/success`, verificados directamente mediante jobs de GitHub Actions.
  - [DOC contracts and hashes](https://github.com/marioromero/StockFlash/actions/runs/44142115528): job `completed/success`, verificado directamente.
  - Ambas ejecuciones informadas por OpenCode para `c8e1338`; la verificación directa en esta sesión constata conclusiones exitosas de todos los jobs.
- **Guía editorial ChatGPT:** tema `catalogo-productos` (25.º tema) añadido en `public/docs/index.html`, commit `b3938efd151444fed9aaed869e3cb3f696521453`. Cobertura técnica, sin prometer despliegue.
- **Roadmap editorial ChatGPT:** `resources/roadmap/index.html` actualizado, commit `b1ca40eb37968d9a49d87ac7add84e478048cdcf`; R3 reconoce productos verificados, R4 conserva existencias reales pendientes.

## Hallazgos pendientes no bloqueantes

- **N-01 (MEDIA, PRE-POS):** tasa referenciada desactivada puede producir fallback incorrecto en cálculo derivado de margen/markup; corregir fallback (variante válida → producto válido → predeterminada) y añadir regresión **antes de reutilizar `CatalogPricing` en POS/Fiscal**. La política de tasas desactivadas requiere interpretación normativa/ADR si se modifica la semántica comercial retroactiva; no inferirla.
- N-02: evitar divergencia entre calculadora JS y servicio, idealmente pruebas unitarias JS o control compartido.
- N-03: mostrar fuente de la tasa efectiva cuando se refine UX.
- Sin MariaDB real/FULLTEXT, concurrencia simultánea, navegador real ni comprobación visual/táctil; se exige antes de despliegue. `remote_db=UNTOUCHED`; no se autorizó despliegue, migraciones ni uso remoto.

**Criterio de cierre:** lo anterior no impide el cierre **técnico P0** porque no se ejecutan ventas ni documentos fiscales y las aceptaciones del packet, gates, QA y CI están satisfechas. Este cierre no afirma readiness comercial, producción ni ejecución real de pruebas diferidas.
