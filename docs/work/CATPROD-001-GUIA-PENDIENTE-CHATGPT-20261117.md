# GUIA_PENDIENTE_CHATGPT — CATPROD-001 (ciclo correctivo 2026-11-17)

- Feature: CATPROD-001 — Productos, variantes, códigos de barras, atributos y etiquetas (CAT-01/03/04/05/07/08).
- Pantalla: `/catalog/products`.
- Base del ciclo correctivo: `8be4e8157905cf4bc4f482e5d5e837547b8bae16`.
- Revisión externa origen: `docs/work/CATPROD-001-EXTERNAL-REVIEW-20261117.md` (H-01, H-02, H-03).
- Notas técnicas: `docs/work/CATPROD-001-NOTES-20261117.md`.

## Qué debe documentar ChatGPT en el manual de usuario

1. **Origen/procedencia:** todo producto creado manualmente desde `/catalog/products` queda con origen POS. El origen no es editable desde esta pantalla; se muestra solo como dato informativo. Canales o importaciones futuras tendrán su propia vía autorizada. No prometer que esta pantalla crea integraciones ni publicaciones de canal.
2. **Margen y markup:** se calculan sobre el **precio neto**, no directamente sobre el precio guardado. El precio neto considera `prices_include_tax` de Preferencias generales (CFG-003) y la tasa aplicable: variante → producto → tasa predeterminada, con exención cuando corresponde. Fórmulas contractuales RN-25:
   - margen % = (precio neto − costo) / precio neto.
   - markup % = (precio neto − costo) / costo.
3. **Calculadora de precio (CAT-04):** disponible al crear/editar una variante y en la variante inicial del alta de producto, únicamente para usuarios con `catalog.view_cost`. Permite elegir margen % o markup % objetivo, usar el costo neto y obtener una **sugerencia** para el campo Precio base, convertida según precio con/sin impuesto y tasa efectiva. No es una regla automática de venta: el usuario puede ajustar el valor y el servidor lo valida al guardar. Advertir que un margen objetivo debe ser menor a 100% y que se requiere costo mayor que 0.
4. **Protección de costos:** sin `catalog.view_cost` no se muestran costo, último costo, precio neto, margen, margen %, markup % ni la calculadora, aunque el usuario pueda consultar o gestionar el catálogo.
5. **Límites:** esta pantalla no emite DTE/SII, no calcula impuestos de transacciones, no crea stock/inventario/Kardex, ventas, caja, combos funcionales P1, promociones, CSV ni sincronización WooCommerce. MariaDB real, FULLTEXT, navegador físico y despliegue permanecen fuera de esta entrega.

OpenCode no edita `public/docs/index.html`; este archivo es solo handoff técnico. El roadmap solo debe actualizarse si ChatGPT detecta un avance real de hito, sin porcentajes ficticios.
