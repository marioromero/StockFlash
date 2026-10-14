# BRAND-001 — Inventario operativo de SVG provisionales (revisión visual)

> Estado: **provisional, solo para revisión visual**. No son logos oficiales ni VERIFIED. Los PNG oficiales prevalecen cuando lleguen (ver `docs/branding/brand-contract.md`).

## 1. Propósito

Documentar los ocho SVG provisionales de StockFlash creados bajo BRAND-001 (APPROVED 2026-10-14) para revisión visual del propietario, sin activarlas en la aplicación ni declararlas arte definitivo.

## 2. Estado provisional — no oficial

- Alcance aprobado: ocho SVG **provisionales, únicamente para revisión visual** (decisión externa a OpenCode, activada en `docs/work/CURRENT.md`).
- No sustituyen el fallback textual de las pantallas ni los PNG oficiales previstos.
- No están integrados en layouts Laravel/Vue; la vista conjunta `docs/work/BRAND-001-PREVIEW.html` es solo para inspección local.
- Prohibido presentarlos como oficiales sin aprobación visual del propietario. Estado final de esta entrega: **listo para revisión visual**.

## 3. Tabla de los 8 SVG

| Archivo | Título | Etiqueta | Uso previsto |
|---|---|---|---|
| `public/images/brand/stockflash.svg` | StockFlash — logo provisional | sin etiqueta (marca madre) | onboarding, login, selección de producto |
| `public/images/brand/stockflash-pos.svg` | StockFlash POS — logo provisional | POS turquesa | shell POS |
| `public/images/brand/stockflash-bodega.svg` | StockFlash Bodega — logo provisional | Bodega neutra | shell Bodega |
| `public/images/brand/stockflash-syncwp.svg` | StockFlash SyncWP — logo provisional | SyncWP neutra | shell SyncWP |
| `public/images/brand/stockflash-fiscal.svg` | StockFlash Fiscal — logo provisional | Fiscal neutra | shell Fiscal |
| `public/images/brand/stockflash-compras.svg` | StockFlash Compras — logo provisional | Compras neutra | shell Compras |
| `public/images/brand/stockflash-analitica.svg` | StockFlash Analítica — logo provisional | Analítica neutra | shell Analítica |
| `public/images/brand/stockflash-control.svg` | StockFlash Control — logo provisional | Control neutra | app privada del fabricante |

No existen SVG para Caja, Ventas, Clientes ni Precios: son funciones internas, nunca productos (ver `docs/branding/module-identity-map.md`).

## 4. Rutas Laravel/Vue

- Archivos físicos: `public/images/brand/*.svg` (exactamente 8, sin subcarpetas).
- URL pública Laravel (cuando se autorice su uso): `/images/brand/<nombre>.svg` vía `asset('images/brand/<nombre>.svg')`.
- Componente Vue/Inertia sugerido (no implementado en BRAND-001): `<img src="/images/brand/stockflash-pos.svg" alt="StockFlash POS — logo provisional" width="210" height="48" />`.
- Sin cambios de rutas, controladores ni componentes en BRAND-001.

## 5. Correspondencia con PNG oficiales

| SVG provisional | PNG oficial previsto |
|---|---|
| `stockflash.svg` | `stockflash.png` |
| `stockflash-pos.svg` | `stockflash-pos.png` |
| `stockflash-bodega.svg` | `stockflash-bodega.png` |
| `stockflash-syncwp.svg` | `stockflash-syncwp.png` |
| `stockflash-fiscal.svg` | `stockflash-fiscal.png` |
| `stockflash-compras.svg` | `stockflash-compras.png` |
| `stockflash-analitica.svg` | `stockflash-analitica.png` |
| `stockflash-control.svg` | `stockflash-control.png` |

Nombres y tildes según `docs/branding/module-identity-map.md`: «Analítica» con tilde, «SyncWP» con esa escritura.

## 6. Paleta aprobada

- `brand.graphite` `#303846` — «Stock» y etiquetas neutras.
- `brand.blue` `#0856FF` — «Flash».
- `brand.turquoise` `#03C6BE` — solo etiqueta POS.
- Neutros de soporte: `#FFFFFF` (texto sobre grafito), `#64748B`, `#F5F7FA`, `#E2E8F0`.
- Ningún otro color en los SVG. Las etiquetas neutras no usan acentos individuales ni colores semánticos de éxito/advertencia/error.

## 7. Wordmark + etiquetas

- Wordmark horizontal `Stock` `#303846` + `Flash` `#0856FF`, fuente del sistema en negrita, sin isotipos, degradados ni efectos.
- Productos: mismo wordmark + etiqueta rectangular de esquinas suavizadas (`rx="8"`) adosada a la derecha con el nombre del producto.
- POS: rectángulo `#03C6BE` con texto oscuro `#303846` (contraste sobre turquesa brillante, según `brand-contract.md`).
- Resto: rectángulo grafito `#303846` con texto `#FFFFFF`; tratamiento neutro provisional, sin acentos definitivos.
- SVG 1.1 por `<img>`: `xmlns`, `viewBox`, `<title>`, XML válido, sin JS, sin CSS remoto, sin recursos de red, sin raster/Base64, sin `script`, sin `foreignObject`, sin referencias externas.

## 8. Convención de nombres

- Minúsculas, sin tildes en el nombre de archivo: `stockflash-<producto>.svg` (`analitica` sin tilde en archivo, con tilde en `<title>` y etiqueta visible).
- `syncwp` siempre en minúsculas en archivo; visible como `SyncWP`.
- Base sin sufijo: `stockflash.svg`.

## 9. Uso, tamaños y accesibilidad

- Vista conjunta local: `docs/work/BRAND-001-PREVIEW.html` (fondo claro `#F5F7FA`, ocho `<img>` relativos, sin dependencias).
- Tamaños sugeridos para revisión: 210×48 (base) y hasta 210×48 equivalente en productos (viewBox `0 0 420 96` y `0 0 300 96`); escalado responsive sin recortes con márgenes internos.
- Accesibilidad: cada SVG trae `<title>`; al usar en HTML acompañar con `alt` equivalente (p. ej. `alt="StockFlash POS — logo provisional"`); contraste POS con texto oscuro; resto blanco sobre grafito.
- Legibilidad verificada sobre fondo blanco y fondo claro; no usar sobre fondos oscuros sin validación de contraste.

## 10. Limitaciones del `<text>` provisional

- Los SVG usan `<text>` con fuentes del sistema (`Arial, Helvetica, sans-serif`) solo como solución provisional documentada.
- El render varía por plataforma (métricas, aliasing, tilde en «Analítica»); no es arte definitivo ni sustituye trazados vectoriales del diseñador.
- No descargar ni incrustar tipografías sin ADR de licencia; no convertir a trazados por cuenta propia.

## 11. Sustitución por PNG oficiales

1. Al recibir los PNG oficiales del diseñador, copiarlos a `public/images/brand/` con los nombres de la §5.
2. Actualizar las vistas para preferir PNG (`asset('images/brand/<nombre>.png')`) y retirar los SVG de cualquier uso en la aplicación.
3. Conservar o eliminar los SVG solo con decisión del propietario; nunca mezclar PNG y SVG como si fueran equivalentes oficiales.
4. Registrar la sustitución en este inventario y en el packet correspondiente.

## 12. Revisión pendiente + propuesta de ajuste contractual

- Pendiente del propietario (revisión visual): proporción wordmark/etiqueta, anchos de etiqueta por producto, radio de esquinas, pesos tipográficos, tilde en «Analítica», contraste POS en tablet táctil y escritorio, escalado pequeño.
- Propuesta de ajuste contractual para owner + ChatGPT (no aplicada por el implementador): si estos SVG se aprueban visualmente, ¿pasan a ser fuente visual primaria temporal hasta los PNG/vectores oficiales, o se mantienen solo como referencia y las pantallas siguen con fallback textual? Registrar la decisión en `docs/branding/brand-contract.md` vía propuesta/ADR; este inventario no modifica contratos.
