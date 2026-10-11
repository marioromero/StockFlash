# StockFlash — Contrato de marca e interfaz base v1 (aprobado parcialmente)

## Identidad canónica
Marca madre: **StockFlash**. Gris grafito `#303846` para «Stock»; azul `#0856FF` para «Flash»; turquesa POS `#03C6BE`. El diseñador entregará PNG transparentes del logo genérico y logos compuestos por producto. **Los PNG oficiales prevalecen sobre recreaciones tipográficas.** Nunca dibujar un nuevo logo por cuenta propia.

La aplicación debe mostrar permanentemente en su shell activo `StockFlash <PRODUCTO COMERCIAL>`, no el nombre de la pantalla o tabla técnica. La etiqueta cambia sólo cuando el usuario navega a otro producto comercial.

## Paleta interfaz inicial (tokens para implementar por OpenCode)
- brand.graphite: `#303846`; brand.blue: `#0856FF`; brand.turquoise: `#03C6BE`.
- text.primary: `#303846`; text.secondary: `#64748B`; bg.page: `#F5F7FA`.
- bg.surface: `#FFFFFF`; border.subtle: `#E2E8F0`.
- Estados (éxito, advertencia, error) con contrastes accesibles, independientes de acentos de módulos.
- No utilizar texto blanco pequeño sobre turquesa brillante `#03C6BE`; preferir texto oscuro, o un tono de fondo más oscuro previamente validado por contraste.
- Colores de etiqueta de los otros productos: **provisionales** hasta recibir PNG oficiales. No se pueden inferir del logo POS como verdad final.

## UX operacional
- Interfaz corporativa limpia, escaneable y consistente; evitar efectos decorativos excesivos, degradados fuertes, saturación de tarjetas e iconografía mezclada.
- Navegación administrativa mediante sidebar colapsable y cabecera breve; navegación accesible por teclado.
- POS optimizado para 15 pulgadas táctiles y tablet 10 pulgadas; mantener compatibilidad escritorio/escáner; tamaño de objetivo táctil >=44 px y preferencia >=48 px en botones de operación.
- POS escritorio: categorías opcionales, búsqueda y botonera/lista en centro, carrito visible; en tablet se permite panel lateral/drawer.
- Búsqueda, clientes, vendedor, autorización PIN, cobro mixto y venta pausada deben completarse sin perder carrito.
- Maquetación responsive, estados vacío/carga/error/deshabilitado, foco visible, etiquetas claras y contraste adecuado.
- Usar componentes de diseño compartidos (botones, inputs, tablas, alertas, confirmaciones). No inventar estilos por pantalla.
- Fuente de UI: elegir con criterios de legibilidad y licencia adecuada, registrar la decisión ADR; **no modificar el arte de los PNG**.

## Assets oficiales
Ruta prevista: `public/images/brand/`. Nombres definidos en `docs/branding/module-identity-map.md`. Si aún no hay PNG: mostrar fallback temporal de texto accesible (StockFlash + nombre producto), sin usar logotipos remotos ni generar nuevos archivos gráficos.

## Pruebas de revisión UI
Logo correcto, etiqueta estable, affordances y estados interactivos, viewport escritorio + 10", contraste, foco teclado, escáner, tactilidad, ausencia de errores consola. Revisor compara siempre con contrato y captura, no con su gusto personal.
