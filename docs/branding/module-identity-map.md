# Mapa de identidad: productos comerciales vs módulos técnicos

| Identidad visual permanente | Archivo PNG oficial previsto | Capacidades visibles | Servicios técnicos heredados del modelo v2.2 usados internamente |
|---|---|---|---|
| StockFlash POS | `stockflash-pos.png` | caja, venta, cliente, vendedor, precio, pago, tickets, devoluciones, inventario mínimo | `pos`, `sales`, `cash_control`, `catalog`, `inventory`, `customers`, `sellers`, `reports_basic` |
| StockFlash Bodega | `stockflash-bodega.png` | catálogo, stock, kardex, ajustes, traspasos, valorización | `catalog`, `inventory`, `reports_basic` |
| StockFlash SyncWP | `stockflash-syncwp.png` | WooCommerce, publicación, mapeos, pedidos, integraciones, reconciliación | `channels`, `woo_sync`, `catalog`, `inventory`, `sales` |
| StockFlash Fiscal | `stockflash-fiscal.png` | emisión tributaria integrada y documentos vinculados a transacciones | `fiscal`, `sales` |
| StockFlash Compras | `stockflash-compras.png` | proveedores, recepciones, abastecimiento según versión | `purchasing`, `inventory`, `catalog` |
| StockFlash Analítica | `stockflash-analitica.png` | analítica avanzada según datos disponibles | `reports_advanced`, `reports_basic` |
| StockFlash Control (solo fabricante) | `stockflash-control.png` | clientes comerciales, licencias, planes, suscripciones, cobro manual | aplicación administrativa separada, no módulo licenciado al comercio |
| StockFlash (general) | `stockflash.png` | onboarding, login, selección de producto | `core` |

**Etiquetas comerciales ≠ nombres de tablas o carpetas.** No crear una etiqueta por pantalla «Precios», «Caja», «Ventas», etc. Al cambiar de área interna dentro del producto, no cambiar logo. `SyncWP` solo denomina el producto comercial Woo inicial; el contrato `SalesChannelAdapter` sigue genérico.

La interfaz de producto debe mostrar exclusivamente prestaciones que el plan, los derechos comerciales y el RBAC permitan. Una pantalla escondida NO constituye autorización de backend.

Solo los PNG oficiales enviados por el diseñador se distribuirán; por ahora la carpeta incluye instrucciones, no archivos falsos.
