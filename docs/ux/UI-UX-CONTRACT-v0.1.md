# Contrato UI/UX StockFlash v0.1 — alcance de bootstrap
**Estado: propuesta aprobada parcialmente; queda diseño detallado por Feature Packet.** Sujeto a especificaciones v2.2 en revisión. La identidad base está en `docs/branding/brand-contract.md`.

## Dos interfaces principales
**Shell Backoffice:** navegación izquierda colapsable, identidad visible StockFlash + producto activo, header con sucursal, usuario, estado de módulos, menú ajustes accesible según permisos. Pantallas dentro de producto no cambian etiqueta.
**Shell POS:** operacional a pantalla amplia, foco de búsqueda, lector, catálogo/cuadrícula/lista configurable, carrito y resumen, cliente y vendedor, acciones táctiles. En tablet 10" usar composición más compacta con carrito recuperable y sin ocultar venta en curso.

## Modo POS único con dos flujos
1) Venta directa: seleccionar productos → carrito → cliente/vendedor opcional según política → pago único o dividido → operación transaccional → comprobante interno o flujo fiscal habilitado.
2) Ticket separado: vendedor autorizado crea ticket y cajero autorizado lo recupera y cobra; política v2.2 sigue vigente.
3) Pausa: carritos identificados por estación, no por usuario solamente; recuperar solo en misma estación. Persistencia/restauración según POS-07 P1, con diseño de privacidad y riesgo de colisión pendiente de ADR.
4) Split pay: efectivo/debito/credito/transferencia con registro separado por medio y monto, saldo pendiente y vuelto en efectivo. No reintentar una venta completada como si fuera nueva.
5) Etiqueta de producto permanece en cabecera, aunque se muevan pantallas internas (POS/Caja/Ventas). El producto SyncWP no convierte al backend `channels` en entidad WordPress.

## Estados y calidad
Filtros y formularios coherentes, mensajes de error accionables, skeletons cuando proceda, alto contraste, foco teclado, tablets, iconografía consistente, controles touch >=44px. Cualquier pantalla debe usar tokens compartidos y assets oficiales cuando estén disponibles. No introducir librerías visuales nuevas por feature sin aprobación.

## Cobertura por alcance
P0 POS-01..08, Caja CSH-01..07, SAL-02/03/05/08 y RNF-01..09 son el baseline; prestaciones P1/P2/P3 deben quedar expresamente etiquetadas, sin anticipación automática.
