# Especificación General v2.1 — POS + Inventario Modular Omnicanal
## Parte 1 de 2 · Alcance, arquitectura, reglas de negocio y requerimientos

**Estado:** v2.1 **FINAL · PUNTO 0 FUNCIONAL** · **Fecha:** 04-10-2026 · incorpora revisión técnica externa de WooCommerce/Laravel/SII y cierre de refunds
**Documento hermano:** `especificacion-v2.1-parte-2-modelo-de-datos-FINAL.md` (tablas, restricciones, orden de migraciones y auditoría cruzada).
**Anexo operativo:** `especificacion-v2.1-anexo-integraciones-y-operacion-backend.md` (contratos HTTP, onboarding WooCommerce, webhooks, polling, refunds, cPanel, DTE y pruebas de integración).
Esta versión **reemplaza** la v2 y el documento de delta; no conserva numeración anterior.

---

## 0. Convenciones

**Prioridades**

| Prio | Significado |
|---|---|
| **P0** | Núcleo para el hito **Demo Alfa** |
| **P1** | Completa el **MVP** |
| **P1★** | P1 **bloqueante para la primera instalación productiva** |
| **P2** | Fase 2 (el modelo ya la contempla) |
| **P3** | Fase 3 / futuro |

**IDs:** `MÓDULO-NN`. Módulos y grupos: `CORE, CAT, INV, PUR, SAL, POS, CSH, CUS, SEL, PRC, FIS, CHN, WOO, INT, REP, PRV, SEC, BKP, UPD, LIC, ARQ, DEM, DOC`. Reglas de negocio: `RN-NN`. No funcionales generales: `RNF-NN`. Los aportes del experto de ventas se citan como `S1…S11` (sección 2).
**Nombres:** tablas y columnas en inglés snake_case; interfaz en español (es-CL).

---

## 1. Resumen ejecutivo

### 1.1 Qué es

> **POS + inventario modular + operación omnicanal + DTE chileno**, inicialmente conectado con WooCommerce, operable en cPanel y preparado arquitectónicamente para crecer.

Un sistema instalable (una instalación por cliente) que combina inventario multi-sucursal, punto de venta y sincronización con canales de venta online, diseñado como **módulos activables por licencia**. Un cliente puede contratar solo bodega, solo POS, o el paquete completo. El desarrollo se entrega y valida mediante una **cuenta demo**. El tratamiento de datos personales propio de estas funciones se diseña para ser compatible con la Ley 21.719.

### 1.2 Principio de alcance (DOC-01)

Una función de privacidad o seguridad entra al producto **solo si el POS realiza directamente la operación o procesa directamente el dato**:

> *El POS realiza X y procesa Y; por esta razón concreta necesita esta protección o función.*

Si la obligación corresponde a procesos organizacionales del cliente, queda fuera del producto. El sistema ofrece **herramientas técnicas** para que el responsable cumpla; no **gestiona** su cumplimiento.

**El producto no es:** software de compliance/GRC ni de asesoría legal, ERP, contabilidad, RRHH, SIEM ni herramienta de monitoreo de infraestructura.

### 1.3 Decisiones de diseño principales

| # | Decisión |
|---|---|
| 1 | Un solo código y un solo esquema de base de datos; los módulos se activan por licencia y configuración |
| 2 | Módulos comunicados por eventos y contratos; infraestructura (colas, caché, archivos) sustituible por configuración |
| 3 | RBAC propio y único: 5 roles de sistema, rol por sucursal, permisos por módulo |
| 4 | Kardex solo con movimientos **físicos**; las **reservas** son documentos propios |
| 5 | Stock publicado como **valores absolutos** desde **una sucursal de cumplimiento por canal** |
| 6 | El dominio no conoce a WooCommerce: `SalesChannelAdapter`; WooCommerce es el primer adaptador |
| 7 | **Un único registro por pedido online** `(canal, pedido externo)`, actualizado por cada estado |
| 8 | **Autoridad fiscal única** (`Canal/POS → Venta interna → FiscalEngine → Adaptador DTE`); DTE en el MVP productivo |
| 9 | El estado del **pago online** lo manda el canal; el POS lo importa y normaliza |
| 10 | Un solo mecanismo de precios: precio base + listas de precio |
| 11 | Privacidad técnica mínima; sin módulos de compliance |
| 12 | Seguridad base práctica (ASVS 1 general, nivel 2 selectivo); MFA para Admin |
| 13 | Control central acotado, sin datos personales ni comerciales |
| 14 | Actualizador con paquete firmado; reversión automática solo mientras siga en mantenimiento |
| 15 | Panel único de salud de integraciones (canal, DTE, licencia, respaldo) |
| 16 | WooCommerce P1 se integra externamente por `wc/v3` + `wc-auth`; **no requiere plugin propio ni acceso directo a tablas de WordPress/WooCommerce** |

---

## 2. Supuestos e interpretaciones a validar

| # | Aporte original | Interpretación adoptada | Si es incorrecta |
|---|---|---|---|
| S1 | "Cambiar precios mediante un código" | Cambiar el precio de una línea durante la venta (oferta puntual) exige un **PIN de autorizador**, con motivo y registro | Si se refería a cambio masivo por código, se cubre con listas de precio |
| S2 | "Código por vendedor" | Identifica al vendedor para atribuir ventas y comisiones. **No es una credencial** | Si debe ser secreto, agregar PIN al vendedor (P2) |
| S3 | Descuento máximo por producto, por cliente y "cuánto el descuento máximo" | Tres topes: producto (y categoría), cliente (y grupo) y operador (usuario o rol). Rige el **menor** | Cambia RN-02 |
| S4 | "Descuento según RUT del cliente" | El cliente identificado por RUT recibe su descuento asignado o el de su grupo | — |
| S5 | "Ticket → caja" | **Dos etapas**: el vendedor genera un ticket y el cajero lo cobra. Los locales pequeños usan **venta directa**. Configurable por sucursal | — |
| S6 | "Impresora fiscal genera el ticket y de ahí sale la boleta o factura" | En Chile el equivalente es: POS → motor fiscal → proveedor DTE (infraestructura del contribuyente) → impresión | Si existe un equipo fiscal físico específico, se requiere marca, modelo y protocolo |
| S7 | "Ingresar productos según su costo para sacar % de ganancia" | Se captura **costo neto** por variante; el sistema calcula margen y markup | — |
| S8 | "Informe de ventas mensuales según vendedor" | Reporte por mes y vendedor, con detalle hasta la venta | — |
| S9 | "El vendedor hace directo caja con su código" | En venta directa el operador puede identificarse como vendedor; la política es configurable | — |
| S10 | "Cuenta demo" | Instalación con plan `demo`, datos de ejemplo, reinicio programado y salvaguardas | Una demo pública multi-prospecto sería SaaS: fuera de alcance |
| S11 | Módulos | Se venden por paquetes y complementos combinables | — |

---

## 3. Visión, objetivos y alcance

### 3.1 Objetivos
1. Vender cada capacidad por separado sin bifurcar el código.
2. Mantener stock exacto y auditable (kardex valorizado) en todas las sucursales.
3. Dar control comercial: descuentos con topes, autorizaciones, margen y atribución por vendedor.
4. Conectar canales online sin depender de ellos para vender.
5. Emitir DTE desde una única autoridad fiscal, sea cual sea el origen de la venta.
6. Tratar datos personales con protecciones técnicas compatibles con la Ley 21.719.
7. Instalarse y actualizarse en cPanel compartido sin acceso SSH.

### 3.2 Dentro del alcance
Catálogo, inventario, compras, ventas (directa y ticket), POS, caja, clientes, vendedores, descuentos y promociones, combos, listas de precio, canales y WooCommerce, DTE por adaptador, reportes, licencias, instalador, actualizador, respaldos, cuenta demo.

### 3.3 Fuera del alcance
Modo restaurante (mesas, comandas, propinas), producción y recetas, multi-moneda, contabilidad, nómina, marketplaces distintos de WooCommerce (el modelo los permite, no se construyen), SaaS multi-tenant, portal de compras propio, soporte remoto.

**Fuera por el principio de alcance:** bases jurídicas, registro de tratamientos, gestión de encargados y subencargados, transferencias internacionales, plazos y flujos de solicitudes de titulares, registro de incidentes como proceso, evaluaciones de impacto (EIPD/DPIA), perfilamiento y decisiones automatizadas sobre personas (DOC-02). Si se agregan funciones de segmentación, *scoring* o IA, se reevalúa.

---

## 4. Arquitectura modular

### 4.1 Principios
1. **Un solo código y un solo esquema de base de datos** para todas las instalaciones. Todas las tablas se crean siempre; no hay migraciones condicionales.
2. Un módulo está **disponible** cuando la licencia lo incluye, está activo en la instalación y sus dependencias duras están activas (RN-67).
3. Los módulos se comunican por **eventos de dominio y contratos**, nunca por llamadas directas. Si un módulo está desactivado, nadie escucha sus eventos y el resto sigue funcionando.
4. Las claves foráneas físicas entre tablas están permitidas (el esquema es único); la restricción de acoplamiento aplica al **código**. Excepción deliberada: el origen de un movimiento de stock y de una reserva es polimórfico, sin FK.
5. Menú, rutas, permisos, tareas programadas y ajustes se construyen desde el registro de módulos.
6. Desactivar un módulo oculta su interfaz y **conserva sus datos**.
7. El código de un canal concreto (WooCommerce) vive en `app/Integrations/`, **fuera** de `app/Domain/` (CHN-07).

### 4.2 Catálogo de módulos

| Código | Módulo | Depende (duro) | Depende (blando) | Contenido principal | Fase |
|---|---|---|---|---|---|
| `core` | Núcleo | — | — | Instalador, usuarios, RBAC, sucursales, configuración, licencia, auditoría, autorizaciones, secuencias, salud de integraciones, respaldos, actualizador | P0–P1 |
| `catalog` | Catálogo | core | — | Productos, variantes, categorías, marcas, etiquetas, códigos de barra, impuestos, costo | P0 |
| `inventory` | Inventario | catalog | — | Kardex, stock, reservas, ajustes, traspasos, conteos, alertas, valorización | P0–P1 |
| `purchasing` | Compras | inventory | — | Proveedores, recepciones (P1), órdenes de compra (P2) | P1 / P2 |
| `sales` | Ventas | catalog | inventory, customers, sellers, cash_control, channels | Documento de venta, tickets, pagos, devoluciones, pedidos online | P0 |
| `pos` | Punto de venta | sales | cash_control, customers, sellers | Terminal, estaciones, impresión | P0 |
| `cash_control` | Control de caja | sales | pos | Sesiones, movimientos, arqueo, reportes X/Z | P0 |
| `customers` | Clientes | core | — | Clientes, grupos, descuentos, opt-in, herramientas de titular | P0–P1 |
| `sellers` | Vendedores | core | sales | Código de vendedor, comisiones, reportes | P0 |
| `promotions` | Promociones (POS) | catalog | sales | Reglas de descuento automático | P1 |
| `combos` | Combos | catalog | inventory | Productos compuestos | P1 |
| `price_lists` | Listas de precio | catalog | customers, channels | Precios por canal, sucursal y grupo | P1 |
| `fiscal` | Documentos tributarios | sales | customers, channels | Motor fiscal y adaptador DTE | P1★ |
| `channels` | Canales de venta (marco) | catalog | inventory, sales, customers | Canales, publicaciones, mapeos, bandeja de salida, recibos de webhook | P0–P1 |
| `woo_sync` | Adaptador WooCommerce | channels | — | Primer adaptador de canal | P1 |
| `reports_basic` | Reportes básicos | core | (todos) | Ventas, stock, kardex, caja, vendedores | P0 |
| `reports_advanced` | Reportes avanzados | reports_basic | — | Margen, ABC, rotación, exportación | P1 |
| `audit_log` | Visor de auditoría avanzado | core | — | Búsqueda y exportación de auditoría | P2 |
| `customer_credit` | Crédito a clientes | customers, sales | — | Cuenta corriente | P2 |
| `loyalty` | Fidelización | customers, sales | — | Puntos, tarjetas de regalo | P3 |
| `offline` | POS sin conexión | pos | — | PWA y cola local | P3 |
| `lots_serials` | Lotes y series | inventory | — | Vencimientos, trazabilidad | P3 |

Activar `woo_sync` activa `channels` automáticamente. El registro de accesos a datos personales, los consentimientos y los eventos de seguridad forman parte de `core` y `customers`, **no** del módulo `audit_log` (RN-71).

### 4.3 Paquetes comerciales sugeridos (decisión comercial, ajustable sin tocar código)

| Módulo | Demo | Bodega | Starter | Pro | Enterprise |
|---|:-:|:-:|:-:|:-:|:-:|
| core, catalog, inventory, reports_basic | ✓ | ✓ | ✓ | ✓ | ✓ |
| purchasing | ✓ | ✓ | — | ✓ | ✓ |
| sales, pos, sellers, customers | ✓ | — | ✓ | ✓ | ✓ |
| cash_control, promotions, combos, reports_advanced | ✓ | — | — | ✓ | ✓ |
| price_lists | ✓ | — | — | ✓ | ✓ |
| channels + woo_sync | ✓ | complemento | ✓ | ✓ | ✓ |
| audit_log, customer_credit | ✓ | — | — | — | ✓ |
| fiscal | ✓ (pruebas) | — | complemento | complemento | complemento |

| Límite | Demo | Bodega | Starter | Pro | Enterprise |
|---|---|---|---|---|---|
| Sucursales | ∞ | 2 | 1 | 3 | ∞ |
| Estaciones POS | ∞ | 0 | 2 | 4 | ∞ |
| Canales | ∞ | 0–1 | 1 | 3 | ∞ |
| Usuarios | ∞ | 5 | 5 | 15 | ∞ |
| Variantes activas | ∞ | 5.000 | 2.000 | 20.000 | ∞ |

`0` en un límite significa **ilimitado**. "Bodega + web" se vende como Bodega con el complemento `woo_sync`.

### 4.4 Eventos de dominio

| Evento | Emite | Escuchan (si están activos) |
|---|---|---|
| `TicketCreated`, `TicketCancelled`, `ReservationExpired` | sales, inventory | inventory (reservas) |
| `SaleCompleted` | sales | inventory (salida), cash_control, sellers/reports, channels (publicación de stock), customers (última compra) |
| `FiscalTriggerSatisfied` | sales y channels, cuando se cumple el disparo de un documento: venta POS pagada, pedido online pagado (`on_paid`), pedido despachado (`on_dispatch`) o solicitud manual autorizada | fiscal (`FiscalEngine` registra el documento `pending`) |
| `FulfillmentStatusChanged` | sales | channels (estado y nota en el canal), inventory (consumo de la reserva al despachar), fiscal (evalúa `on_dispatch`) |
| `ChannelRefundImported` | channels | sales (crea o actualiza la devolución en revisión) |
| `SaleCancelled` | sales | inventory, fiscal (nota de crédito con aprobación), channels |
| `SaleReturned` | sales | inventory, fiscal, cash_control |
| `GoodsReceived` | purchasing | inventory (entrada y costo), channels |
| `StockChanged` | inventory | channels (publicación), core (alertas de stock crítico) |
| `ProductChanged`, `PriceChanged` | catalog, price_lists | channels |
| `ChannelOrderUpserted` | channels | sales (crea o actualiza la venta), inventory (reserva o consumo) |
| `FiscalDocumentIssued` | fiscal | sales (estado fiscal), channels (referencia, P2) |
| `LicenseChanged` | core | todos (recalcular módulos) |
| `CustomerAnonymized` | customers | channels (limpiar cargas), reports |

### 4.5 Contratos y estructura de código
Contratos: `LicenseValidator`, `FiscalDocumentIssuer`, `SalesChannelAdapter`, `StockPublisher`, `BackupDestination`, `IntegrationHealth`. Estructura sugerida: `app/Domain/<Módulo>/{Models,Actions,Events,Listeners,Contracts,Policies,Http}`, `app/Integrations/<Adaptador>/` y `config/modules.php` (código, dependencias, permisos, menú, tareas programadas y claves de configuración). Servicios transversales: `ModuleManager`, `PlanGuard`, `AuthorizationService`, `PricingEngine`, `DocumentNumberService`, `FiscalEngine`, `OrderIngestionService`.

### 4.6 Cuenta demo
Plan `demo` con todos los módulos, `is_demo=true`, datos de ejemplo coherentes, reinicio programado, canal en modo simulado, adaptador fiscal interno o ambiente de pruebas del proveedor, correo deshabilitado y usuarios de prueba visibles en el login (DEM-01…07).

---

## 5. Arquitectura técnica

| Aspecto | Decisión |
|---|---|
| Stack | Laravel (versión estable vigente al iniciar, fijada en `composer.lock`) + Vue 3 con Inertia. Rutas web con sesión; **sin API pública general de negocio** en el MVP. Sí existen endpoints técnicos dedicados para callbacks/webhooks de integraciones, con autenticación/firma y alcance mínimo |
| PHP / BD | PHP 8.3+ (mínimo 8.2). MariaDB 10.11 LTS+ (mínimo técnico 10.6). InnoDB, `utf8mb4_unicode_ci` |
| Hosting inicial | cPanel compartido: sin procesos persistentes. **Un único cron del sistema por minuto ejecuta `php artisan schedule:run`**. El scheduler lanza workers **acotados** de la cola `database` (`queue:work --stop-when-empty --max-time≈45`) con `withoutOverlapping`, de modo que ningún daemon ni Supervisor sea obligatorio. El detalle operativo está en el Anexo |
| Evolución | Redis, workers persistentes, almacenamiento de objetos o VPS se adoptan **cambiando configuración**, no código de dominio (ARQ-01…11) |
| Zona horaria | Base de datos en UTC; cortes y reportes por día local `America/Santiago` (RNF-09) |
| Instalación | Instalador web (`/install`) y actualizador web con paquete firmado (UPD-01…09) |
| Efectos externos | Cada módulo usa **su propia bandeja transaccional**: canales → `channel_sync_events`; fiscal → `fiscal_documents` en `pending` y su job (ARQ-02) |
| Cifrado | Laravel Crypt para secretos y RUT; HMAC con clave dedicada para el índice ciego |
| Fiscal | `FiscalDocumentIssuer`; el contribuyente es el emisor y el proveedor DTE es su infraestructura tecnológica |
| Licencia | `LicenseValidator` con stub, planes, límites y módulos |

### 5.1 Perfil técnico validado para WooCommerce P1

La integración P1 usa exclusivamente interfaces públicas de WooCommerce/WordPress y queda desacoplada del almacenamiento interno de WordPress:

1. **API base:** `/wp-json/wc/v3/` sobre HTTPS. No se accede directamente a `wp_posts`, `wp_postmeta` ni tablas HPOS.
2. **Onboarding preferido:** flujo oficial `/wc-auth/v1/authorize`, con `callback_url` HTTPS y un identificador de intento de conexión de un solo uso. La carga recibida contiene `consumer_key`/`consumer_secret`; se cifra inmediatamente y nunca se registra. Entrada manual de API keys queda como fallback.
3. **Sin plugin propio en P1:** no se necesita instalar un plugin WordPress para pedidos, productos, stock, precios, refunds o webhooks. Si en el futuro se crea uno, deberá usar APIs/CRUD de WooCommerce y declarar compatibilidad HPOS.
4. **Webhooks gestionados:** el POS crea/verifica/repara por REST sus webhooks administrados. Los webhooks son una señal de cambio; el procesador puede volver a consultar el recurso actual antes de mutar el dominio.
5. **Polling de respaldo:** pedidos y catálogo se consultan por fecha de modificación, con solape, paginación de hasta 100 elementos por página e idempotencia. El cursor solo avanza después de completar todas las páginas.
6. **Capacidades, no suposiciones:** durante el preflight se prueban permisos y endpoints realmente disponibles. Si una función no existe o no está autorizada se desactiva solo esa capacidad y se muestra el motivo.
7. **HTTPS obligatorio:** no se soporta autenticación Woo sobre HTTP. Si el hosting remoto elimina el header `Authorization`, P1 informa el problema de servidor; **no usa credenciales en query string por defecto** porque podrían quedar expuestas en logs.
8. **Cantidades fraccionarias:** la REST API core de WooCommerce declara `stock_quantity` como entero. El POS puede manejar `DECIMAL(12,3)`, pero P1 **no redondea silenciosamente** para Woo. Si una variante tiene stock publicable fraccionario y el preflight no detecta una extensión/adaptador expresamente compatible con cantidades decimales, la publicación de stock de esa variante queda bloqueada con incidencia accionable.
9. **Autoridad de precio:** si `sync_prices_enabled=1`, el POS es la autoridad del precio publicado. El adaptador escribe `regular_price` y elimina/neutraliza `sale_price` y sus fechas de vigencia en el recurso administrado, salvo que una capacidad futura habilite explícitamente promociones remotas. P1 no mantiene dos autoridades de precio simultáneas.
10. **Granularidad de stock en variables:** un mapping a una variación publica stock en la **variación**. Si Woo usa `manage_stock='parent'` o stock agregado del producto padre para una variante que el POS pretende controlar individualmente, el preflight lo marca incompatible y exige alinear la configuración antes de activar `sync_stock_enabled`.

Los detalles ejecutables, endpoints, códigos HTTP y criterios de aceptación están congelados en el Anexo de Integraciones.

---

## 6. Roles y permisos

### 6.1 Roles del sistema

| Rol | Código | Autoridad | Descripción |
|---|---|---:|---|
| Vendedor | `seller` | 10 | Arma tickets y atiende clientes. No cobra |
| Cajero | `cashier` | 20 | Incluye Vendedor. Cobra, abre y cierra caja, vende directo |
| Bodeguero | `warehouse_keeper` | 20 | Recepciones, traspasos, conteos, mermas. No opera el POS |
| Supervisor | `supervisor` | 50 | Incluye Cajero y Bodeguero. Autoriza por PIN, anula, devuelve, ve reportes de su sucursal |
| Admin Central | `central_admin` | 100 | Todo, todas las sucursales, configuración, licencia, respaldos y actualizaciones |

"Acumulativo" se implementa **expandiendo permisos en el seeder** (Supervisor = Cajero ∪ Bodeguero ∪ propios; Admin = todos). La **autoridad** decide quién puede autorizar por PIN (`override_min_authority`, por defecto 50).

### 6.2 RBAC único (sin paquetes externos)
- Tablas propias: `roles`, `permissions`, `role_permissions`, `branch_user`.
- **Usuario global:** `users.global_role_id` no nulo; su rol aplica en todas las sucursales. Es la única forma de ser global.
- **Usuario por sucursal:** una fila `branch_user(user_id, branch_id, role_id)` por sucursal; sin filas no tiene acceso.
- **Permisos efectivos** = permisos del rol que corresponde a la sucursal actual. No existen permisos asignados directamente a usuarios.
- Los módulos registran sus permisos en `config/modules.php` y un seeder los sincroniza. Roles personalizados por cliente: P2.

### 6.3 Matriz de permisos por defecto
Leyenda: V Vendedor · C Cajero · B Bodeguero · S Supervisor · A Admin. ✓ permitido · ◐ con límite o condición · — no.

| Permiso | V | C | B | S | A | Nota |
|---|:-:|:-:|:-:|:-:|:-:|---|
| `catalog.view` | ✓ | ✓ | ✓ | ✓ | ✓ | |
| `catalog.manage` / `catalog.import` | — | — | — | ✓ | ✓ | |
| `catalog.view_cost` | — | — | ◐ | ✓ | ✓ | Costo y margen son sensibles |
| `inventory.view` | ✓ | ✓ | ✓ | ✓ | ✓ | |
| `inventory.adjust` | — | — | ◐ | ✓ | ✓ | Sobre el umbral pide PIN |
| `inventory.transfer`, `.receive`, `.waste`, `.count` | — | — | ✓ | ✓ | ✓ | |
| `inventory.apply_count` | — | — | — | ✓ | ✓ | |
| `purchasing.manage` | — | — | ◐ | ✓ | ✓ | B solo recepciones |
| `sales.create_ticket`, `sales.hold` | ✓ | ✓ | — | ✓ | ✓ | |
| `sales.direct_sale`, `sales.collect` | — | ✓ | — | ✓ | ✓ | |
| `sales.discount_manual` | ◐ | ◐ | — | ✓ | ✓ | Hasta el tope del rol o usuario |
| `sales.price_override` | — | ◐ | — | ✓ | ✓ | Con PIN de autorizador |
| `sales.edit_ticket_at_cash` | — | ◐ | — | ✓ | ✓ | Configurable |
| `sales.change_seller` | — | — | — | ✓ | ✓ | |
| `sales.void` | — | ◐ | — | ✓ | ✓ | Con PIN |
| `sales.return` | — | ◐ | — | ✓ | ✓ | Hasta `max_return_amount` |
| `sales.reprint` | — | ◐ | — | ✓ | ✓ | Solo del propio turno |
| `sales.dispatch` (pedidos online) | — | — | ✓ | ✓ | ✓ | |
| `sales.review_orders` | — | — | — | ✓ | ✓ | Incidencias abiertas de pedidos (`sale_review_issues`) |
| `sales.view_own` | ✓ | ✓ | — | ✓ | ✓ | |
| `sales.view_branch` / `sales.view_all` | — | — | — | ✓ / — | ✓ / ✓ | |
| `cash.open_close` | — | ✓ | — | ✓ | ✓ | |
| `cash.movements` | — | ◐ | — | ✓ | ✓ | Hasta umbral |
| `cash.approve_difference`, `cash.view_all` | — | — | — | ✓ | ✓ | |
| `customers.view`, `customers.manage` | ✓ | ✓ | — | ✓ | ✓ | V y C: alta rápida |
| `customers.view_sensitive` | — | — | — | ✓ | ✓ | RUT completo y contacto; cada acceso se audita |
| `customers.privacy_tools` | — | — | — | — | ✓ | Exportar, bloquear, anonimizar |
| `sellers.manage` / `sellers.view_reports` | — / propio | — / propio | — | ✓ | ✓ | |
| `pricing.manage` | — | — | — | ✓ | ✓ | Promociones, combos, listas |
| `fiscal.view` / `fiscal.request_credit_note` | — | — / — | — | ✓ / ✓ | ✓ / ✓ | La nota de crédito requiere aprobación |
| `integrations.view` | — | — | — | ✓ | ✓ | Panel de estado |
| `integrations.manage` | — | — | — | — | ✓ | Reintentar, reparar, configurar canal y DTE |
| `reports.view_branch` / `.view_all` / `.export` | — | — | — | ✓ / — / ✓ | ✓ | |
| `override.authorize` | — | — | — | ✓ | ✓ | Autoridad ≥ `override_min_authority` |
| `users.manage`, `settings.manage`, `modules.manage` | — | — | — | — | ✓ | |
| `audit.view` / `audit.view_security` | — | — | — | ◐ / ◐ | ✓ / ✓ | S ve lo de su sucursal |
| `license.view` | — | — | — | ◐ | ✓ | |
| `backups.manage`, `system.update` | — | — | — | — | ✓ | Requieren MFA |

Los topes numéricos (`max_discount_percent`, `max_return_amount`) viven en `roles` y pueden sobrescribirse por usuario.

---

## 7. Reglas de negocio

### 7.1 Precios y descuentos

**RN-01 · Cadena de precio de una línea.**
1. `P0` = precio de referencia: el de la **lista de precios** aplicable (RN-09) o, si no hay, `product_variants.price`.
2. Descuento automático `A` = el **mayor** entre promoción aplicable y descuento del cliente (propio o de su grupo). No se acumulan (`discount_stacking_mode=best_of`).
3. `P1 = P0 − A`.
4. Descuento manual opcional `M`, sobre `P1`.
5. Precio manual opcional `X`, que reemplaza el resultado (oferta puntual).
6. Descuento efectivo `D = (P0 − precio_final) / P0`.

**RN-02 · Tope efectivo.** `tope = MIN` de los valores definidos entre: producto (`variants.max_discount_percent`, si no, el de su categoría más cercana con valor), cliente (`customers.max_discount_percent`, si no, el de su grupo) y operador (`users.max_discount_percent`, si no, el de su rol). Si ninguno existe rige `default_max_discount_percent` (por defecto 100).

**RN-03 · Cuándo se evalúa.** Si la línea contiene descuento por cliente, manual o precio manual, se compara `D` total contra el tope. Una línea con **solo promoción** no se evalúa (`discount_caps_apply_to_promotions=false`). Igual para el descuento global.

**RN-04 · Superar el tope.** Exige PIN de un autorizador (`override.authorize`, autoridad ≥ `override_min_authority`). Se registra en `authorization_overrides` y la línea lo referencia.

**RN-05 · Precio manual.** Requiere `sales.price_override`, PIN, motivo y registro. Se bloquea si `allow_price_override=0`. Si el precio final queda bajo el costo (`block_sale_below_cost=true`) o bajo `min_price`, exige autorización adicional.

**RN-06 · Redondeo.** Cada línea se redondea según `currency_decimals`. El redondeo de efectivo (`cash_rounding_step`) se aplica solo al total pagado en efectivo y se registra como `rounding_amount`.

**RN-07 · Impuestos.** `prices_include_tax` define si el precio incluye impuesto. Cada línea guarda tasa, neto e impuesto. Los exentos usan la tasa `EXENTO`.

**RN-08 · Empate entre promociones.** Gana la de mayor `priority`; luego la de menor `id`. Las promociones aplican **solo al POS**; un pedido online conserva los precios y descuentos con que lo vendió el canal.

**RN-09 · Resolución de lista de precios.** Se consideran las listas vigentes que **contienen la variante** y cuyas dimensiones no nulas coinciden con el contexto (sucursal, grupo del cliente, canal). `applies_to`: `all` coincide siempre; `pos` solo en POS; `online` en cualquier canal online; `channel` solo con ese `sales_channel_id`. Orden: (1) `priority` descendente; (2) **especificidad** descendente = sucursal (+1) + grupo (+1) + canal (`pos` u `online` +1, `channel` +2); (3) `id` ascendente. Sin lista aplicable rige `price`. *Ejemplo:* una lista "Mayorista · Sucursal 2" (especificidad 2) gana a "Mayorista" (1) si tienen igual prioridad; para invertirlo se usa `priority`.

**RN-10 · Combos.** Se venden como **una sola línea** con precio propio. Los componentes solo afectan al stock (RN-23).

### 7.2 Vendedores

**RN-11 · Política de código.** `seller_code_policy` = `none`, `optional` o `required`. Se resuelve: valor de la sucursal; si es nulo, el de `system_configs`.
**RN-12 · Atribución.** `sales.seller_id` = vendedor del ticket (dos etapas) o el ingresado o vinculado al usuario (venta directa). El cajero que cobra no lo cambia; cambiarlo requiere `sales.change_seller` y queda auditado.
**RN-13 · Códigos.** Únicos, no reutilizables; se desactivan, no se borran. Existe el vendedor reservado "Sin asignar" para agregados.
**RN-14 · Seguridad.** El código identifica, **no autentica**.

### 7.3 Flujos de venta

**RN-15 · Modo de flujo por sucursal.** `direct`, `two_step` o `both` (por defecto `both`). En `two_step` solo Cajero y superiores cobran.

**RN-16 · Ticket (preventa).** Estado `pending_payment`, `ticket_number` correlativo por sucursal y `ticket_code` corto (escáner o QR). Si `ticket_reserves_stock=true`, crea una **reserva por línea** (RN-28). Vence a los `ticket_expiry_minutes`: un job lo cancela y libera la reserva. Solo el creador o un supervisor lo cancela antes del cobro. Se cobra **una sola vez**: el cobro bloquea la fila y valida el estado.

**RN-17 · Edición en caja.** Solo con `sales.edit_ticket_at_cash`; recalcula precios y vuelve a evaluar topes.

**RN-18 · Venta directa.** Una transacción: validar → calcular precios → aplicar stock → registrar pagos → completar → emitir eventos. El motor fiscal evalúa el disparo (RN-51) y, si se satisface, registra el documento `pending` en la misma transacción del evento que lo satisface, sin llamada externa.

**RN-19 · Integridad e idempotencia.** Se bloquean filas de `current_stock` en orden `(branch_id, variant_id)` ascendente. El envío de una venta es idempotente por `client_uuid`.

**RN-20 · Ventas en espera.** `status=held` con etiqueta; no reservan stock; vencen a las 24 h.

### 7.4 Stock y costo

**RN-21 · Efecto en el kardex** (solo movimientos **físicos**):

| Evento | Tipo de movimiento | Δ existencia |
|---|---|---:|
| Carga inicial | `initial` | + |
| Recepción de compra | `purchase_receipt` | + |
| Ajuste manual / por conteo | `adjustment` / `count_adjustment` | ± |
| Merma | `waste` | − |
| Venta POS | `sale_pos` | − |
| Pedido online despachado | `sale_online` | − |
| Devolución de cliente | `customer_return` | + |
| Devolución a proveedor | `supplier_return` | − |
| Traspaso (envío / recepción) | `transfer_out` / `transfer_in` | − / + |
| Corrección de un movimiento | `reversal` | inverso |

`disponible = existencia − reservas activas`. Los movimientos **no se editan ni se borran**; se corrigen con `reversal`. Las reservas **no** generan movimientos.

**RN-22 · Stock negativo.** `allow_negative_stock` (por defecto `true`) permite forzar una venta **de mostrador** con advertencia y confirmación. Si es `false`, se bloquea. Un pedido online nunca se rechaza por stock (RN-32).
**RN-23 · Combos y stock.** Si `combo_deducts_components=true`, vender el combo genera movimientos sobre cada componente (`cantidad_componente × cantidad_combo`). Si es `false`, el combo maneja stock propio. No hay combos anidados.
**RN-24 · Costo promedio ponderado.** `nuevo = (stock_total × costo_actual + qty × costo_nuevo) / (stock_total + qty)`; si `stock_total ≤ 0`, el costo nuevo es el de la compra. Costo global por variante; el kardex conserva el costo de cada movimiento. `cost_method` admite `average` o `last`.
**RN-25 · Margen.** `margen % = (precio_neto − costo) / precio_neto`; `markup % = (precio_neto − costo) / costo`. Las ventas guardan el costo vigente (`unit_cost_snapshot`).
**RN-26 · Traspasos.** Enviar descuenta del origen; recibir suma al destino; lo no recibido queda como diferencia pendiente. Estados: `draft → requested → in_transit → received | partially_received | cancelled`.
**RN-27 · Conteos.** Se toma una foto (`snapshot_at`). Al aplicar: ajuste = `contado − (foto + movimientos posteriores a la foto)`. Conteo ciego por defecto; diferencias sobre umbral piden PIN.

### 7.5 Reservas y publicación de stock

**RN-28 · Reservas.** Son **documentos** (`stock_reservations`), no movimientos.
- **Identidad:** una fila por línea de documento, única por `(source_type, source_id, source_line_key)`; `source_type` ∈ {`ticket`, `online_order`}, `source_id` = `sales.id`, `source_line_key` = `sale_items.id`.
- **Ciclo:** `active → consumed` (al cobrar el ticket o despachar el pedido) · `released` (cancelación) · `expired` (vencimiento de un **ticket**). `released` y `expired` pueden **reactivarse en la misma fila** si el documento se reactiva; `consumed` es final.
- Un cambio de cantidad actualiza la misma fila. Cada `reservation_sweep_seconds` un job procesa las vencidas: las de **tickets** se liberan; las de **pedidos online** se **reconcilian con el canal** y no se liberan por inferencia local (RN-30).
- `current_stock.reserved` = suma de reservas activas; la reconciliación lo verifica.

**RN-29 · Stock publicable.** Para un canal y una variante:
`publicable = MAX(0, existencia(sucursal de cumplimiento) − reservas activas − stock de seguridad)`,
donde las reservas **excluyen** las que tienen `hold_owner='channel'` y pertenecen a **ese mismo canal** (RN-30). El stock de seguridad aplica solo a canales online, nunca al POS. Se publica **siempre el valor absoluto**, fusionando cambios dentro de `channel_stock_debounce_seconds` y solo si difiere de lo último publicado.

**RN-30 · Hold Stock (política única).** WooCommerce puede reservar stock en etapas de checkout/pago pendiente y, según su configuración de inventario, cancelar pedidos `pending` impagos al vencer el tiempo de retención. El asistente **intenta leer la configuración mediante la Settings API**; si el usuario de integración no tiene permiso para verla, exige confirmación manual y la guarda como capacidad/configuración detectada.
- Mientras el pedido está `pending` y el canal retiene (`holds_pending_stock=1`): el POS crea una reserva `hold_owner='channel'`, que **bloquea la venta física** pero **no se resta de nuevo** del stock publicado a ese canal (Woo ya lo resta). Vence a `hold_stock_minutes + 15`.
- Desde `on-hold` o `processing`, WooCommerce ya descontó su propio stock y deja de retener: la reserva pasa a `hold_owner='internal'` y se publica neta. Como se publican valores absolutos, ambos sistemas coinciden.
- Si el comercio **desactiva** el Hold Stock en WooCommerce, el canal se configura con `holds_pending_stock=0` y todas las reservas son `internal`.
- Se recomienda al comercio mantener el Hold Stock **habilitado**; el asistente de configuración lo pide como confirmación.
- **Vencimiento seguro.** `expires_at` de una reserva de pedido online marca **cuándo reconciliar**, no cuándo liberar. Al vencer se consulta el pedido al canal (evento `reconcile_order` en `channel_sync_events`) y: (1) si el canal lo canceló o falló → se libera; (2) si sigue `pending` → se mantiene y se renueva según el estado remoto; (3) si sigue `on-hold` → se mantiene, se renueva y se abre la incidencia `payment_overdue` para revisión; (4) si **no se puede consultar** → se conserva la reserva, se reintenta con retroceso y se levanta una alerta en el panel de integraciones. Contra entrega: reserva hasta el despacho o la cancelación, sin vencimiento.
- **Regla general:** una reserva online **no se libera por inferencia local** mientras el canal siga afirmando que el pedido compromete stock.
- **Limitación documentada (`checkout-draft`).** WooCommerce puede reservar stock de forma transitoria durante el *checkout*, antes de que exista un pedido `pending`. P1 **no importa carritos ni reservas de *checkout***: la reserva interna comienza cuando existe un pedido importable (`pending` o posterior). Esa ventana previa es responsabilidad del canal; el stock de seguridad la mitiga, pero **no se promete "cero sobreventa"** en un *checkout* remoto aún no importado.

**RN-31 · Sucursal de cumplimiento.** Cada canal usa **una única sucursal de cumplimiento** (`fulfillment_branch_id`). El stock publicado procede de ella, las reservas se hacen ahí y todo pedido online tiene `branch_id`. Elegir sucursal por pedido, enrutar por comuna, sumar varias sucursales o dividir el despacho: P2.

**RN-32 · Stock insuficiente en pedidos online.** El pedido **se importa siempre**. Se reserva la cantidad completa (el disponible puede quedar negativo) y se abre la incidencia `stock_shortage` (RN-76) para revisión de un supervisor. Los *backorders* no están soportados: se recomienda desactivarlos en el canal. Nada falla en silencio.

### 7.6 Caja

**RN-33 · Sesión.** Solo se exige si `branches.requires_cash_control=1`. Una estación y un usuario no pueden tener dos sesiones abiertas (restricción en base de datos). Puede permanecer abierta varios días; alerta tras `cash_session_max_hours`.
**RN-34 · Efectivo esperado.** `apertura + ventas en efectivo − vueltos − reembolsos en efectivo + ingresos − retiros`.
**RN-35 · Cierre.** Cierre **ciego** configurable. Los métodos no efectivo se concilian contra el total del sistema. Una diferencia sobre `cash_difference_tolerance` exige aprobación de supervisor.
**RN-36 · Sin control de caja.** Las ventas se registran con `cash_session_id` nulo; los reportes por estación siguen funcionando con `pos_station_id`.
**RN-37 · Movimientos.** Ingresos y retiros llevan motivo; sobre `cash_movement_pin_threshold` piden PIN. "Abrir cajón sin venta" se registra siempre.

### 7.7 Pedidos online y sincronización

**RN-38 · Un registro por pedido.** Un pedido del canal es **un único** registro en `sales`, identificado por `(channel_id, external_order_id)` (restricción única). Cada evento (webhook o sondeo) **actualiza ese registro**; nunca se crea una segunda venta. Sus líneas se identifican por `(sale_id, external_line_id)` (restricción única), de modo que el mismo pedido recibido por webhook y por sondeo no duplica líneas. Se ignoran solo los estados puramente transitorios (`checkout-draft`, `auto-draft`; limitación en RN-30).

**RN-39 · Estados normalizados.**

| Estado en el canal | `status` | `fulfillment_status` | Reserva | DTE |
|---|---|---|---|---|
| `checkout-draft`, `auto-draft` | se ignora (limitación documentada en RN-30) | — | — | — |
| `pending` | `pending_payment` | `assigned` | Sí; `channel` si hay retención del canal. Al vencer se **reconcilia**, no se libera | No |
| `on-hold` | `pending_payment` | `assigned` | Sí, `internal`. Al vencer se **reconcilia** y se abre `payment_overdue`; no se libera mientras el canal lo mantenga vigente | No, hasta confirmar pago |
| `processing` (prepago) | `completed` | `assigned` | Sí, hasta despacho | Según `fiscal_trigger` (por defecto `on_paid`) |
| `processing` (contra entrega) | `pending_payment` (el pago queda `pending` hasta que el canal marque `completed`) | `assigned` | Sí, hasta despacho | `on_dispatch` |
| `completed` | `completed` | `delivered` | Consumida | Si falta, se solicita |
| `cancelled`, `failed` | `cancelled` | `cancelled` | Liberada | Si hay DTE emitido: nota de crédito con aprobación |
| Reembolsos (total o parcial) | No cambia por sí mismo; `return_status` según lo reembolsado | — | Según la revisión del reembolso | Nota de crédito tras la revisión (RN-75) |

**RN-40 · Orden de eventos.** Cada pedido guarda `external_modified_at` (la fecha de modificación del canal). Un evento con fecha **anterior** no cambia el pedido (queda registrado). Ante igualdad de fecha con estado distinto, o ante cualquier duda, se **consulta de nuevo el pedido actual** al canal y se aplica ese resultado.

**RN-41 · Sondeo de respaldo a prueba de huecos.** Por canal y recurso (pedidos, productos) existe un cursor. En cada corrida: (1) se captura la hora de inicio `T0`; (2) se lee desde `cursor − solape` (`channel_poll_overlap_minutes`, por defecto 10), paginando de forma determinística hasta agotar las páginas; (3) cada registro se procesa de forma idempotente; (4) **solo si todas las páginas terminaron**, el cursor pasa a `T0`. Repetir registros es aceptable; perderlos no.

**RN-42 · Pago online.** **El canal es la autoridad del estado del pago**; el POS lo importa y normaliza. Un pedido de canal admite **un único pago principal originado en el canal** (`sale_payments.source='channel'`; pagos online divididos: P2). El pago existe desde que existe el pedido: en `pending` u `on-hold`, con la pasarela conocida, queda `pending` **sin `paid_at`**; pasa a `confirmed` cuando el canal informa el pago (con su fecha) y a `cancelled` si el pedido se cancela o falla. En contra entrega permanece `pending` hasta que el canal marque el pedido `completed`. Siempre se conservan `external_gateway_id`, `external_gateway_title` y la referencia de la transacción. Si la pasarela **no tiene mapeo** a un método interno, el pedido se conserva, `payment_method_id` queda nulo y se abre la incidencia `payment_mismatch`. Una restricción única garantiza que el mismo pedido recibido por webhook y sondeo nunca crea dos pagos. Confirmar pagos desde el POS y empujarlos al canal: P2.

**RN-43 · Webhooks.** Validan, en este orden: canal activo, **tamaño máximo** (`webhook_max_body_kb`), firma HMAC-SHA256 sobre el **cuerpo crudo** con el secreto del canal e idempotencia por `delivery_id`. Si la firma es válida y el `webhook_receipt` pudo persistirse, se responde **2xx lo antes posible** y el procesamiento queda diferido. Una entrega válida duplicada también responde 2xx. **No se usa 429 para tráfico firmado válido en condiciones normales**, porque respuestas no exitosas repetidas pueden hacer que WooCommerce desactive el webhook; la presión legítima se absorbe con cola/backpressure y polling de respaldo. Firmas inválidas sí se limitan por IP y reciben 401/403; cargas sobre el máximo reciben 413.

**RN-44 · Propiedad de campos.** Nombre, SKU, precio y stock → POS; categorías, etiquetas y marcas → POS. **Contenido** (descripción, descripción corta, imágenes): el **POS es el maestro por defecto**; el administrador puede designar **un único canal como fuente de contenido entrante** (`system_configs.content_source_channel_id`): su contenido actualiza el maestro y los demás canales lo reciben sin sobrescribirlo. Contenido distinto por canal: P2.
**RN-45 · Publicación por canal.** Una variante se publica a un canal solo si existe su fila en `channel_publications` con `is_published=1`. Los combos no se publican por defecto.
**RN-46 · Producto desconocido.** Un pedido con un ítem sin mapeo se importa igual. La línea se guarda **sin variante** (`sale_items.variant_id` nulo, exclusivamente para líneas externas no vinculadas) conservando `external_line_id`, nombre, SKU remoto, cantidad, precio, impuestos y total, y se abre la incidencia `unlinked_item`. **No genera reserva ni movimiento de stock** hasta vincularla. Al vincular se asigna la variante, se crea o ajusta la reserva de la línea (RN-28), se completa la variante de los reembolsos de esa línea aplicando su efecto de stock, y se resuelve la incidencia. La línea no bloquea el documento fiscal, el pago ni los reembolsos.
**RN-47 · Clientes online.** Un pedido online **no crea** un registro en `customers` por defecto: guarda el nombre visible y, mientras se despacha, los datos de despacho. **Asociación a un cliente existente:** (1) un RUT válido que coincida (`fiscal_id_hash`, único) tiene prioridad; (2) sin RUT, el correo asocia automáticamente **solo si existe exactamente un cliente activo** con ese correo; (3) cero coincidencias: no se asocia; (4) varias coincidencias: no se elige ninguno y el pedido queda sin cliente asociado. El correo **no** es único. Se crea un cliente nuevo solo si se pide factura o si el canal tiene `import_customers=1`.
**RN-48 · Capacidades del canal.** Si un canal no expone una función (por ejemplo, marcas), esa sincronización se desactiva sola para ese canal, sin afectar el resto.

**RN-75 · Reembolsos del canal.** **WooCommerce es la autoridad del reembolso monetario** de una venta online (P1); crear reembolsos desde el POS hacia el canal: P2. El adaptador reconcilia los refunds como **recursos propios** mediante el endpoint de reembolsos del pedido: pueden ser totales, parciales, por líneas y múltiples; un reembolso parcial no necesariamente cambia el estado general del pedido. Cada refund externo tiene identidad estable (`external_refund_id`) y se importa idempotentemente (`sale_returns` único por `(channel_id, external_refund_id)`; cada línea es única por `(return_id, sale_item_id)`).

**Normalización de importes:** internamente `refund_amount`, `quantity`, `net_amount`, `tax_amount` y `line_amount` se guardan como **magnitudes positivas**. El adaptador normaliza cualquier signo usado por la API remota. Por línea, `net_amount` y `tax_amount` conservan los importes **realmente reembolsados por el canal**; `line_amount = net_amount + tax_amount`. No se recalculan desde el precio histórico para emitir la nota de crédito.

**Decisión física:** un refund de canal nace `pending_review` y cada línea nace con `restock=NULL`: `NULL` = pendiente de decisión; `1` = el producto reingresa físicamente al stock; `0` = no reingresa/merma. Un refund externo **no puede pasar a `completed` mientras alguna línea vinculada tenga `restock IS NULL`**. Si una línea todavía no tiene variante, el hecho financiero se conserva y el efecto físico queda diferido hasta vincularla (RN-46). Un refund de canal **no se cancela ni se elimina conceptualmente**, porque el reembolso monetario ya ocurrió en el canal; `cancelled` se reserva a devoluciones POS aún no finalizadas.

**Diferencias no asignadas a productos:** WooCommerce puede devolver despacho, fees u otros importes, y el total del refund puede diferir de la suma de líneas de producto. El sistema conserva siempre `sale_returns.refund_amount`. Si `refund_amount != SUM(sale_return_items.line_amount)`, mantiene `refund_review` abierto y **bloquea la emisión automática de la nota de crédito** hasta revisión manual/fiscal. P1 no crea un subsistema completo de refunds de shipping/fees.

Flujo: webhook/sondeo del pedido → consulta de refunds → importación idempotente → `pending_review` + `refund_review` → supervisor decide el efecto físico y resuelve cualquier diferencia → `completed` → stock de líneas `restock=1` → `FiscalEngine` solicita la nota de crédito. `sales.return_status` refleja lo efectivamente reembolsado.

**RN-76 · Incidencias de revisión.** Una venta puede tener **varias incidencias simultáneas** (`sale_review_issues`): `stock_shortage`, `unlinked_item`, `invoice_data_missing`, `payment_mismatch`, `payment_overdue` y `refund_review`. Ninguna reemplaza a otra. Hay a lo más una incidencia **activa** por venta, código y sujeto (línea o devolución), de modo que un hecho repetido no la duplica; al resolverla (`resolved_at`) puede abrirse otra futura. La bandeja de pedidos por revisar consulta las incidencias activas.

**RN-77 · Serialización de efectos externos.** Los efectos sobre una misma entidad de un canal (`channel_id`, `entity_type`, entidad o acción) se **serializan con `Cache::lock`** (funciona con caché de base de datos hoy y con Redis después). Un job **nunca confía** en un valor antiguo del payload para stock o precio: al adquirir el bloqueo **vuelve a leer y calcular el estado actual** que debe publicar. `published_quantity` y `published_price` se actualizan **solo tras la confirmación** del canal. Un sucesor `pending` se ejecuta después y vuelve a leer el estado vigente. Así, aunque dos workers procesen eventos de la misma entidad, el último valor publicado es el vigente.

**RN-78 · Compatibilidad de cantidades con el canal.** El dominio conserva cantidades `DECIMAL(12,3)`. Cada adaptador declara si admite cantidades fraccionarias para stock y pedidos. WooCommerce core P1 se considera **entero** para `stock_quantity`; por tanto, si el valor absoluto a publicar no es entero y `fractional_stock_supported=false`, el job no envía ni redondea: deja `published_quantity` sin modificar, registra error de compatibilidad en la integración y mantiene el stock interno intacto. Una extensión de Woo que cambie esta capacidad requiere soporte explícito del adaptador y prueba de contrato; no se infiere por nombre de plugin.

**RN-79 · Autoridad de precio de canal.** Con `sync_prices_enabled=1`, el precio resuelto por `PricingEngine` es la única autoridad de precio para el recurso mapeado en P1. En WooCommerce se publica como `regular_price`; cualquier `sale_price`/ventana de oferta remota que altere el precio efectivo se limpia o se rechaza durante el preflight/configuración. Permitir promociones administradas directamente en WooCommerce mientras el POS publica precios queda P2 y exigirá una política explícita de precedencia.

**RN-80 · Stock de productos variables.** Para una variante interna mapeada a una variación Woo, stock y precio se escriben sobre `/products/{product_id}/variations/{variation_id}`. P1 no publica stock individual de una variante cuando Woo delega ese stock al padre (`manage_stock='parent'`) o cuando varias variantes internas comparten un único contador remoto sin una correspondencia 1:1. El preflight debe detectar esa condición y bloquear la activación del stock hasta corregirla.

### 7.8 Documentos tributarios (Chile)

**RN-49 · Autoridad fiscal única.** Si este módulo es la autoridad DTE, **toda emisión fiscal paralela** (en WooCommerce u otro sistema) debe estar desactivada. Al configurar el adaptador se exige una **confirmación explícita**, que queda auditada. La detección automática de plugins es una ayuda opcional, no la garantía.
**RN-50 · Emisor.** El **emisor electrónico es el contribuyente** (el comercio). El proveedor DTE es la infraestructura tecnológica que usa; el módulo fiscal es la única autoridad lógica dentro del sistema.
**RN-51 · Solicitud y disparo.** El documento fiscal **no depende exclusivamente de `SaleCompleted`**. Cada documento tiene un **disparo** definido por el origen y el método de pago: venta POS pagada; pedido online prepago confirmado (`on_paid`); pedido contra entrega despachado (`on_dispatch`); o solicitud **manual** autorizada (`manual`). El `FiscalEngine` evalúa el disparo después de los eventos relevantes (`SaleCompleted`, `FulfillmentStatusChanged`, pago confirmado, acción manual) y, cuando se satisface (`FiscalTriggerSatisfied`), registra un `fiscal_documents` en `pending` **en la misma transacción del evento que lo satisface**. Un job lo envía tras confirmar la transacción y un barredor recoge los pendientes. Es idempotente: si ya existe un documento activo, no crea otro. *Validar con el contador del cliente qué disparo corresponde a cada método de pago.*
**RN-52 · Idempotencia en cuatro capas.** (1) Autoridad única; (2) a lo más un documento activo por venta, tipo, propósito y revisión (restricción en base de datos); (3) clave de idempotencia por solicitud, enviada como referencia externa cuando el proveedor lo soporta; (4) resultado incierto (`unknown`): **no se reintenta**, se consulta primero al proveedor.
**RN-53 · Estados y correcciones.** Estados técnicos: `pending → sending → issued`, más `rejected`, `error` y `unknown`. Un documento `issued` **permanece `issued`**. Una anulación o devolución se expresa con una **nota de crédito** que lo referencia; su efecto comercial se **deriva** de esa relación. Emitir de nuevo tras una nota de crédito usa `revision + 1`. Las notas de crédito requieren aprobación.
**RN-54 · Política de falla.** `fiscal_failure_policy` = `allow_pending` (la venta se completa y el documento queda pendiente) o `block_sale`. Falla segura **anterior al envío**: reintento automático. Resultado **incierto**: reconciliar antes de volver a emitir. El estado resumido de la venta pasa a `action_required` cuando hay intervención pendiente.
**RN-55 · Conservación y respaldo.** El **XML** del DTE se conserva al menos 6 años, comprimido y con hash, en un disco configurable. El **PDF** es una caché regenerable desde el XML o el proveedor, se purga a los 90 días (configurable) y **no se respalda**. Recuperar la base **sin** el repositorio de XML no es una recuperación completa: los XML se protegen con **replicación incremental** desde su creación a un destino distinto, con verificación de existencia y hash (BKP-09 a BKP-11). Mientras los XML tengan **una sola copia física**, el panel de integraciones muestra una advertencia permanente. Los tamaños de archivo citados en la documentación son estimaciones orientativas, no un supuesto de arquitectura.
**RN-56 · Sucursal y proveedor.** El motor recibe `branch_id` y el adaptador lo traduce a la configuración que el proveedor necesite (guardada en `fiscal_provider_config`). Ningún campo propio del proveedor vive en `branches`.
**RN-57 · Tipos.** Boleta afecta (39) y exenta (41), factura afecta (33) y exenta (34), nota de crédito (61), nota de débito (56), guía de despacho (52). En el MVP productivo: 39, 41, 33, 34 y 61.

### 7.9 Privacidad técnica (Ley 21.719)

**RN-58 · RUT.** Se normaliza, se valida (módulo 11), se guarda **cifrado** y con **índice ciego** `HMAC-SHA256(rut_normalizado, BLIND_INDEX_KEY)`. El hash simple está prohibido. Aplica a clientes y a proveedores personas naturales.
**RN-59 · Minimización.** Se pide solo lo necesario para cada operación: boleta a consumidor final no exige cliente; descuento por RUT exige RUT; factura exige datos tributarios. La venta guarda únicamente el **nombre visible** del cliente; los datos tributarios pertenecen al documento fiscal y los de despacho a `shipping_snapshot` (se limpia a los `retention_shipping_days` de entregado). Las cargas de sincronización no contienen datos personales salvo los recibos de webhook, que se depuran.
**RN-60 · Consentimiento.** Solo para funciones opcionales que el software ofrece (hoy, comunicaciones comerciales; fidelización cuando exista). Se registra aceptación o revocación con canal, usuario, fecha y **`notice_version`** (versión del texto mostrado). Sin base jurídica ni matriz de finalidades.
**RN-61 · Enmascarado y acceso.** RUT y contacto se muestran enmascarados; revelarlos exige `customers.view_sensitive` y queda registrado. Exportar, modificar, bloquear o anonimizar también.
**RN-62 · Anonimización.** Reemplaza los datos personales por valores neutros en clientes, direcciones, `shipping_snapshot`, `customer_name_snapshot`, usuarios, vendedores y proveedores, conservando los `id`. No se borran ventas, kardex ni documentos tributarios; estos se conservan por obligación legal. *Validar el alcance con asesoría legal.*
**RN-63 · Exportación.** Genera un archivo con los datos que el sistema guarda de una persona (JSON o CSV). El archivo expira (`retention_export_files_days`) y la generación se audita.
**RN-64 · Bloqueo.** `customers.blocked_at` impide su uso comercial (descuentos automáticos, comunicaciones, sincronización) manteniendo el dato almacenado.
**RN-65 · Retención** (categorías técnicas; `retention:prune`):

| Categoría | Regla por defecto |
|---|---|
| Ventas, kardex, caja, documentos tributarios | **No se borran** |
| Datos personales de clientes | No se eliminan solos; se anonimizan con la herramienta (inactivos: P2) |
| Recibos de webhook | Carga depurada a las 72 h si se procesó; hasta 14 días si falló |
| Borradores, ventas en espera y tickets vencidos | 30 días |
| Eventos de canal cerrados (`channel_sync_events`) | 30 días |
| `shipping_snapshot` | 180 días tras la entrega |
| Auditoría y eventos de seguridad | 24 meses |
| Archivos de exportación con datos personales | 7 días |
| PDF fiscales (caché) | 90 días |
| Respaldos | 7 diarios y 4 semanales |

Solo el job `retention:prune` puede eliminar registros vencidos de tablas **AO-P**; las tablas **AO** no se purgan (Parte 2, §2).

**RN-66 · Libro de supresiones y sus límites.** Cada anonimización agrega una línea (entidad, id, fecha; **sin datos personales**) a un archivo *append-only* fuera de la base. Va incluido en cada respaldo y, si hay destino externo, se copia allí. Tras restaurar **solo la base**, `privacy:reapply-ledger` vuelve a anonimizar esas entidades. **Límite:** en una pérdida total del servidor protege hasta el último respaldo disponible; las supresiones posteriores las debe reaplicar el responsable desde su propio registro. El sistema no afirma evitar la reaparición de datos más allá de ese límite.

### 7.10 Módulos, licencia y actualización

**RN-67 · Disponibilidad.** `disponible = licencia_incluye ∧ instalado_activo ∧ dependencias_duras_activas`.
**RN-68 · Desactivar.** Oculta la interfaz y conserva los datos; se bloquea si hay módulos activos que dependen de él.
**RN-69 · Límites.** Cuentan registros **activos**. Si tras un cambio de plan la instalación supera un límite, **no se borra nada**: solo se impide crear más.
**RN-70 · Licencia suspendida.** Tras `grace_until` el sistema pasa a **solo lectura**: no permite nuevas ventas ni documentos, pero mantiene consulta, reportes y **exportación de datos**. Nunca se eliminan datos.
**RN-71 · Cumplimiento técnico siempre activo.** Consentimientos, registro de acceso a datos personales, eventos de seguridad y herramientas de titular no dependen del plan.
**RN-72 · Latido al control central.** Lleva solo campos listados (LIC-02). Nunca incluye clientes, ventas, RUT, documentos, datos de contacto ni información comercial.
**RN-73 · Reversión del actualizador.** La reversión automática (código y base) solo es posible **mientras el sistema siga en mantenimiento**. Después, se corrige hacia adelante.

### 7.11 Numeración

**RN-74 · Numeración por ámbito.** Los números visibles son **únicos por sucursal** (prefijo por defecto: código de la sucursal) y cada tabla receptora tiene la restricción única correspondiente:

| Documento | Ámbito de la secuencia | Restricción única en la tabla |
|---|---|---|
| Ticket y cotización (`ticket_number`) | Sucursal | `sales (branch_id, ticket_number)` |
| Venta (`sale_number`, al cobrar) | Sucursal | `sales (branch_id, sale_number)` |
| Devolución | Sucursal | `sale_returns (branch_id, number)` |
| Traspaso | Sucursal de origen | `stock_transfers (from_branch_id, number)` |
| Conteo | Sucursal | `stock_counts (branch_id, number)` |
| Recepción | Sucursal | `goods_receipts (branch_id, number)` |
| Sesión de caja | Sucursal | `cash_sessions (branch_id, number)` |
| Comprobante interno | Sucursal | `fiscal_documents (branch_id, internal_number)` |
| Orden de compra | Instalación | `purchase_orders (number)` |

---

## 8. Requerimientos funcionales

### 8.1 Núcleo (`core`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| CORE-01 | Inicio de sesión con correo y contraseña; bloqueo temporal tras 5 intentos fallidos | P0 | SEC-02 |
| CORE-02 | Onboarding (`/setup`): comercio, país, moneda, redondeo, responsable, datos tributarios, sucursal inicial y usuario administrador; bloquea el sistema hasta completarse. **Puerta de producción (P1★):** en instalaciones no demo, el paso final exige la descarga del paquete de recuperación de secretos (BKP-08); en la demo (P0) no se exige | P0 | BKP-08 |
| CORE-03 | CRUD de usuarios: rol global o por sucursal, activar/desactivar, PIN, tope de descuento y de devolución propios | P0 | RN-02 |
| CORE-04 | **RBAC único** (sección 6.2): 5 roles de sistema, permisos por módulo, verificación en middleware y políticas | P0 | |
| CORE-05 | CRUD de sucursales con tipo (`store`, `warehouse`, `online`), ajustes por sucursal y control de caja opcional | P0 | |
| CORE-06 | Registro de módulos: activar o desactivar dentro de la licencia, validando dependencias | P0 | RN-67 |
| CORE-07 | `PlanGuard`: límites de sucursales, estaciones, canales, usuarios y variantes, con mensajes de mejora de plan | P0 | RN-69 |
| CORE-08 | Contrato `LicenseValidator` con stub; banner de vencimiento y modo solo lectura tras la gracia | P0 | LIC, RN-70 |
| CORE-09 | Pantalla de configuración por módulo (claves de la Parte 2, sección 3) | P0 | |
| CORE-10 | Auditoría de eventos sensibles: acceso a datos personales, cambios de precio, costo y stock, autorizaciones, configuración, licencia | P0 | PRV-06 |
| CORE-11 | Autorizaciones por PIN reutilizables por todos los módulos (`authorization_overrides`) | P0 | S1, SEC-12 |
| CORE-12 | Catálogo editable de códigos de motivo | P1 | |
| CORE-13 | Numeración por ámbito (RN-74), asignada bajo bloqueo | P0 | |
| CORE-14 | Notificaciones internas: stock crítico, licencia, integración en rojo, sesión de caja larga | P1 | INT-03 |
| CORE-15 | Restablecimiento manual de contraseña por el Admin | P0 | SEC-04 |
| CORE-16 | Roles personalizados por cliente | P2 | |
| CORE-17 | **Instalador web** (`/install`): revisa requisitos, prueba la BD, genera claves, migra, siembra, bloquea la reinstalación y muestra las líneas de cron | P0 | |
| CORE-18 | Modo demo (`is_demo`) con banner y salvaguardas | P0 | DEM |

### 8.2 Catálogo (`catalog`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| CAT-01 | Productos simples, variables y combos; variantes por atributos | P0 | |
| CAT-02 | Categorías jerárquicas, marcas y etiquetas | P0 | |
| CAT-03 | Varios códigos de barra por variante; búsqueda por escáner | P0 | |
| CAT-04 | Costo neto; calculadora de precio por margen o markup; margen visible solo con `catalog.view_cost` | P0 | S7 |
| CAT-05 | Tasa de impuesto por producto (IVA, exento) y `prices_include_tax` | P0 | RN-07 |
| CAT-06 | Tope de descuento por variante y por categoría | P0 | S3 |
| CAT-07 | `allow_price_override` y `min_price` por variante | P0 | RN-05 |
| CAT-08 | Productos sin control de stock (servicios) y no vendibles (insumos) | P0 | |
| CAT-09 | Búsqueda por nombre, SKU, código de barra, marca y etiqueta en menos de 300 ms | P0 | RNF-03 |
| CAT-10 | Importación y exportación CSV asíncrona, con validación previa (*dry-run*), actualización por SKU y reporte de errores por fila | P1 | |
| CAT-11 | Unidades de medida y venta por peso | P1 | |
| CAT-12 | Actualización masiva de precios por porcentaje o margen | P2 | |
| CAT-13 | Impresión de etiquetas de precio y códigos de barra | P2 | |
| CAT-14 | EAN-13 con peso o precio embebido (balanza) | P2 | |
| CAT-15 | Imágenes por URL, con carga diferida | P1 | |

### 8.3 Inventario (`inventory`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| INV-01 | Kardex inmutable de movimientos **físicos**, con `balance_after`, costo y origen polimórfico | P0 | RN-21 |
| INV-02 | `current_stock` actualizado bajo bloqueo de fila en la misma transacción; job de reconciliación | P0 | RN-19 |
| INV-03 | Consulta de stock por sucursal y variante: existencia, reservado, disponible | P0 | |
| INV-04 | Ajustes manuales con código de motivo; sobre umbral piden PIN | P0 | |
| INV-05 | Carga inicial de stock manual y por CSV | P0 | |
| INV-06 | Traspasos entre sucursales con estados y recepción parcial | P0 | RN-26 |
| INV-07 | Punto de reorden, cantidad de reposición y máximo por sucursal y variante; panel de stock crítico | P0 | |
| INV-08 | Reporte Kardex por variante, sucursal y fechas | P0 | |
| INV-09 | Costo promedio ponderado y valorización del inventario | P1 | RN-24 |
| INV-10 | Conteos de inventario (ciego, cíclico) con aplicación de diferencias | P1 | RN-27 |
| INV-11 | Mermas con motivo | P1 | |
| INV-12 | Política de stock negativo | P0 | RN-22 |
| INV-13 | Descuento de stock de componentes de combos | P1 | RN-23 |
| INV-14 | Etiqueta de ubicación por sucursal y variante | P2 | |
| INV-15 | Productos sin movimiento y análisis ABC | P2 | |
| INV-16 | Lotes, vencimientos y series | P3 | |
| INV-17 | **Reservas como documentos** (`stock_reservations`), idempotentes por línea de origen; `current_stock.reserved` es su caché | P0 | RN-28 |
| INV-18 | Vencimiento de reservas: los tickets vencidos se liberan; las reservas de pedidos online se **reconcilian con el canal** y no se liberan por inferencia local | P0 | RN-28, RN-30 |
| INV-19 | Stock de seguridad por variante, para canales online | P1 | RN-29 |
| INV-20 | Contrato `StockPublisher` que calcula el stock publicable por canal | P1 | RN-29 |
| INV-21 | Stock de seguridad por canal | P2 | |

### 8.4 Compras (`purchasing`)

| ID | Requerimiento | Prio |
|---|---|:-:|
| PUR-01 | CRUD de proveedores (RUT con cifrado e índice ciego; contacto opcional) | P1 |
| PUR-02 | Recepción de mercadería con o sin orden de compra, con documento del proveedor; actualiza stock y costo | P1 |
| PUR-03 | Órdenes de compra con estados y recepción parcial | P2 |
| PUR-04 | Devolución a proveedor | P2 |
| PUR-05 | Sugerencia de compra desde el punto de reorden | P2 |
| PUR-06 | Costos por proveedor y comparación | P3 |

### 8.5 Ventas (`sales`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| SAL-01 | Documento de venta con `status`, `return_status` y `fulfillment_status`, y numeración por ámbito | P0 | RN-74 |
| SAL-02 | Venta directa en una etapa | P0 | S5 |
| SAL-03 | **Dos etapas:** el vendedor genera el ticket (número, código corto y QR impreso) y el cajero lo busca, escanea y cobra | P0 | S5 |
| SAL-04 | Cola de tickets pendientes por sucursal; reserva, vencimiento, cancelación y cobro único | P0 | RN-16 |
| SAL-05 | Pagos mixtos, vuelto, redondeo de efectivo y referencia por método | P0 | |
| SAL-06 | Cadena de precios y descuentos con topes y autorizaciones | P0 | RN-01…10 |
| SAL-07 | Vista previa de venta sin efectos secundarios | P0 | |
| SAL-08 | Ventas en espera | P0 | RN-20 |
| SAL-09 | Anulación con motivo y autorización; revierte stock y pagos | P0 | |
| SAL-10 | Devoluciones parciales y totales: restock o merma, método de reembolso, tope por rol | P0 | |
| SAL-11 | Comprobante interno (sección 10.5); reimpresión con permiso | P0 | |
| SAL-12 | Historial con búsqueda por número, ticket, cliente, vendedor, cajero, fecha y método de pago | P0 | |
| SAL-13 | Notas en la venta y en líneas | P1 | |
| SAL-14 | Anulación de línea antes del pago, con registro | P1 | |
| SAL-15 | Consulta de precio sin agregar al carrito | P1 | |
| SAL-16 | **Pedidos online como ventas:** un registro por `(canal, pedido)` y líneas idempotentes por `external_line_id`, actualizado por cada evento, con estados normalizados, líneas no vinculadas sin variante y sucursal de cumplimiento | P1 | RN-38, RN-39, RN-46 |
| SAL-17 | Cotizaciones convertibles en ticket o venta | P2 | |
| SAL-18 | Cambios de mercadería | P2 | |
| SAL-19 | Apartados, tarjetas de regalo y saldo a favor | P3 | |
| SAL-20 | **Pago online importado:** el canal manda el estado; un único pago principal por pedido (`source='channel'`), `pending` y sin fecha hasta confirmarse, con la pasarela conservada aunque no tenga mapeo | P1 | RN-42 |
| SAL-21 | Bandeja de **pedidos por revisar**: incidencias activas y simultáneas (`stock_shortage`, `unlinked_item`, `invoice_data_missing`, `payment_mismatch`, `payment_overdue`, `refund_review`) | P1 | RN-76 |
| SAL-22 | **Reembolsos del canal como devoluciones** (`source='channel'`): importación idempotente, revisión del supervisor, efecto de stock y nota de crédito | P1 | RN-75 |

### 8.6 Punto de venta (`pos`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| POS-01 | Terminal: búsqueda y escaneo, carrito, atajos de teclado, apta para táctil | P0 | |
| POS-02 | Identificación de la estación por token de dispositivo | P0 | |
| POS-03 | Alta rápida y búsqueda de cliente por RUT sin salir de la venta | P0 | S4 |
| POS-04 | Código de vendedor según política | P0 | S2, S9 |
| POS-05 | Ventana de autorización por PIN: se elige al autorizador y se ingresa su PIN | P0 | SEC-12 |
| POS-06 | Advertencia o bloqueo por stock insuficiente en venta de mostrador | P0 | RN-22 |
| POS-07 | Persistencia del carrito ante recargas y reautenticación sin cerrar la caja | P1 | RNF-07 |
| POS-08 | Impresión por navegador (58 mm, 80 mm, A4) | P0 | |
| POS-09 | Teclas rápidas y favoritos | P2 | |
| POS-10 | Modo sin conexión | P3 | |

### 8.7 Control de caja (`cash_control`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| CSH-01 | Control de caja activable por sucursal | P0 | RN-36 |
| CSH-02 | CRUD de estaciones POS | P0 | |
| CSH-03 | Apertura de sesión por estación con monto inicial | P0 | RN-33 |
| CSH-04 | Ingresos y retiros con motivo y PIN sobre umbral; "abrir cajón sin venta" registrado | P0 | RN-37 |
| CSH-05 | Cierre con declaración por método (ciego opcional), diferencias y aprobación | P0 | RN-35 |
| CSH-06 | Detalle de sesión: totales por método y **listado de todas las ventas** | P0 | |
| CSH-07 | Reportes X (parcial) y Z (cierre) imprimibles | P0 | |
| CSH-08 | Historial de sesiones con filtros y exportación | P1 | |
| CSH-09 | Alerta de sesión abierta por demasiado tiempo | P1 | |
| CSH-10 | Arqueo por denominaciones | P2 | |

### 8.8 Clientes (`customers`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| CUS-01 | CRUD de clientes (persona o empresa) con RUT validado, cifrado e índice ciego | P0 | S4, RN-58 |
| CUS-02 | Grupos de clientes con descuento por defecto y tope | P0 | |
| CUS-03 | Descuento asignado y tope propio por cliente | P0 | S3 |
| CUS-04 | Opt-in de funciones opcionales (comunicaciones comerciales), con canal, usuario, fecha y `notice_version` | P1 | RN-60 |
| CUS-05 | Historial de compras y estadísticas por cliente | P1 | |
| CUS-06 | Detección de duplicados y fusión por RUT o correo | P1 | |
| CUS-07 | Crédito a clientes (fiado) | P2 | |
| CUS-08 | Fidelización | P3 | |
| CUS-09 | Las exportaciones para comunicaciones excluyen a quien no tiene opt-in vigente | P1 | |

### 8.9 Vendedores (`sellers`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| SEL-01 | CRUD de vendedores con código único, vínculo opcional a usuario y sucursales | P0 | S2 |
| SEL-02 | Atribución de ventas según RN-11 y RN-12 | P0 | S9 |
| SEL-03 | **Reporte mensual por vendedor**: ventas, tickets, ticket promedio, descuentos, margen, devoluciones y detalle hasta la venta | P0 | S8 |
| SEL-04 | Comisión por porcentaje y reporte de comisiones | P1 | |
| SEL-05 | Metas de venta | P3 | |

### 8.10 Precios y promociones (`promotions`, `combos`, `price_lists`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| PRC-01 | Promociones **de POS**: % de descuento, monto, precio fijo, N×M; por producto, categoría, marca, etiqueta, sucursal, grupo de clientes y fechas | P1 | RN-08 |
| PRC-02 | Regla de no acumulación: gana el mayor descuento | P1 | RN-01 |
| PRC-03 | Combos con componentes, precio propio y regla de descuento de stock | P1 | RN-10, RN-23 |
| PRC-04 | Cupones por código | P2 | |
| PRC-05 | **Listas de precio** generales, de POS, online genéricas o de un canal específico; por sucursal y por grupo de clientes (mayorista, especial), con prioridad y vigencia | P1 | RN-09 |
| PRC-06 | Servicio de resolución de precio con la regla determinística de RN-09 | P1 | |
| PRC-07 | Precios por volumen | P2 | |

### 8.11 Documentos tributarios (`fiscal`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| FIS-01 | Contrato `FiscalDocumentIssuer` y adaptador `internal_receipt` | P0 | |
| FIS-02 | `fiscal_documents` y `fiscal_document_attempts` con restricciones de unicidad | P0 | RN-52 |
| FIS-03 | `FiscalEngine`: evalúa el disparo de cada documento (RN-51), decide si corresponde emitir y qué tipo, y registra el `pending` en la misma transacción del evento que lo satisface | P1★ | RN-51 |
| FIS-04 | Adaptador del proveedor #1: boleta 39 y 41, factura 33 y 34, nota de crédito 61 | P1★ | RN-57 |
| FIS-05 | **Configuración desde la interfaz:** proveedor, credenciales cifradas, ambiente, prueba de conexión, tipo por defecto, política de falla, **confirmación de autoridad fiscal** y configuración por sucursal | P1★ | RN-49, RN-56 |
| FIS-06 | Idempotencia y estado `unknown` con reconciliación contra el proveedor; nunca reintento a ciegas | P1★ | RN-52 |
| FIS-07 | Nota de crédito por anulación o devolución, **con aprobación**; el documento original permanece `issued` | P1★ | RN-53 |
| FIS-08 | Datos tributarios del receptor para factura; si faltan, el pedido queda en revisión | P1★ | |
| FIS-09 | Conservación del XML por 6 años con replicación incremental y verificación (BKP-09 a BKP-11); PDF como caché regenerable que no se respalda | P1★ | RN-55 |
| FIS-10 | Detección opcional de plugins fiscales activos en el canal (ayuda, no garantía) | P2 | RN-49 |
| FIS-11 | Referencia del DTE de vuelta al pedido del canal | P2 | |
| FIS-12 | Nota de débito 56 y guía de despacho 52 | P2 | |
| FIS-13 | Libro de ventas | P2 | |
| FIS-14 | Agente de impresión directa (ESC/POS) y puente para equipos físicos | P3 | S6 |

### 8.12 Canales de venta (`channels`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| CHN-01 | Contrato `SalesChannelAdapter` y registro de adaptadores | P0 | |
| CHN-02 | `sales_channels` con **sucursal de cumplimiento única** | P0 | RN-31 |
| CHN-03 | `ChannelOrder` y `OrderIngestionService` independientes del canal; **upsert** por `(canal, pedido)` | P1 | RN-38 |
| CHN-04 | Mapa de pasarelas del canal a métodos internos, con momento de pago, reserva y disparo fiscal | P1 | |
| CHN-05 | Estados normalizados de pedido que cada adaptador traduce | P1 | RN-39 |
| CHN-06 | `FakeChannelAdapter` en la suite: demuestra que ventas, stock y fiscal funcionan sin WooCommerce | P1 | |
| CHN-07 | Ningún archivo de `app/Domain` importa clases de un canal concreto (verificación en el checklist de release) | P0 | |
| CHN-08 | **Publicación por canal** (`channel_publications`) | P1 | RN-45 |
| CHN-09 | Orden de eventos con `external_modified_at` y consulta ante duda | P1 | RN-40 |
| CHN-10 | Política única de Hold Stock (`holds_pending_stock`, `hold_stock_minutes`), vencimiento con reconciliación y limitación de *checkout* documentada | P1 | RN-30 |
| CHN-11 | Stock insuficiente en pedidos online: importar, reservar la cantidad completa y abrir la incidencia `stock_shortage` | P1 | RN-32 |
| CHN-12 | Sondeo de respaldo con cursor y solape a prueba de huecos | P1★ | RN-41 |
| CHN-13 | Pago online: el canal es la autoridad; un pago principal por pedido; `pending` sin `paid_at`; pasarela sin mapeo = `payment_mismatch` | P1 | RN-42 |
| CHN-14 | Clientes online: sin creación automática; asociación por RUT o por correo solo ante una coincidencia única | P1 | RN-47 |
| CHN-15 | **Reembolsos del canal como recursos propios**, importados de forma idempotente (`external_refund_id`) | P1 | RN-75 |
| CHN-16 | Serialización de efectos externos por entidad y relectura del estado vigente | P1 | RN-77 |
| CHN-17 | Crear reembolsos desde el POS hacia el canal | P2 | RN-75 |

### 8.13 Adaptador WooCommerce (`woo_sync`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| WOO-01 | **Asistente de conexión:** por defecto usa el flujo oficial `wc-auth` (`/wc-auth/v1/authorize`) con intento de un solo uso y callback HTTPS; entrada manual de API keys como fallback. Credenciales cifradas, prueba de conexión, versión y capacidades | P1 | §5.1 |
| WOO-02 | Webhooks entrantes: firma HMAC-SHA256 (base64 sobre cuerpo crudo), tamaño máximo, idempotencia por `X-WC-Webhook-Delivery-ID`, **2xx inmediato tras persistir recepción** y procesamiento diferido | P1 | RN-43 |
| WOO-03 | Publicación de stock y precios con valores absolutos, fusionados, serializados y releídos al ejecutar; solo ante cambios | P1 | RN-29, RN-77 |
| WOO-04 | Sincronización de productos, variaciones, categorías, etiquetas y marcas con mapeos; para stock/precio se escribe el producto simple o la variación concreta según el mapping | P1 | RN-44 |
| WOO-05 | Importación del catálogo remoto como "no vinculado" y pantalla de vinculación | P1 | |
| WOO-06 | Traducción de pedidos y estados de WooCommerce a los normalizados; webhook como señal y relectura del pedido actual ante duda; actualización de estado y nota privada en el canal | P1 | RN-39, RN-40 |
| WOO-07 | Propiedad de campos por canal; contenido con el POS como maestro y a lo más un canal fuente de contenido | P1 | RN-44 |
| WOO-08 | Reconciliación nocturna de stock | P1 | |
| WOO-09 | **Verificar y reparar webhooks** administrados: consultar estado (WooCommerce puede desactivarlos tras entregas fallidas repetidas), reactivar/recrear y conservar IDs/tópicos gestionados | P1★ | INT-01 |
| WOO-10 | Sondeo de respaldo de pedidos y productos con ventana solapada, `modified_after`/`modified_before`, paginación y cursor seguro (usa CHN-12) | P1★ | RN-41 |
| WOO-11 | Cumplimiento desde varias sucursales y enrutamiento por comuna | P2 | RN-31 |
| WOO-12 | Reconciliar refunds del pedido mediante `/orders/{id}/refunds`, preservando importes reales, líneas, impuestos e identidad externa | P1 | RN-75 |
| WOO-13 | **Preflight de capacidades/permisos:** comprobar `wc/v3`, lectura/escritura requerida en pedidos/productos/variaciones, refunds, webhooks y Settings cuando esté autorizada; guardar capacidades detectadas y fallar con explicación accionable | P1★ | §5.1 |
| WOO-14 | **Compatibilidad HPOS:** prohibido acceder directamente a tablas de WordPress/WooCommerce. P1 funciona solo por REST; cualquier plugin futuro debe usar CRUD/API Woo y declarar compatibilidad HPOS | P1 | §5.1 |
| WOO-15 | **Contrato de compatibilidad:** el soporte se decide por preflight/capacidades, no por asumir una estructura interna. Cambios de Woo se incorporan primero al adaptador y su suite de contratos | P1 | CHN-06 |
| WOO-16 | `order.updated`/polling detecta IDs de refunds y ejecuta `reconcile_refunds`; no se depende de que el estado global del pedido pase a `refunded` | P1 | RN-75 |
| WOO-17 | P1 **no necesita plugin WordPress propio**. Un plugin complementario solo se evaluará si una capacidad futura no puede resolverse de forma razonable por REST/webhooks | P1 | §5.1 |
| WOO-18 | **Cantidades fraccionarias:** Woo core usa `stock_quantity` entero; si el stock publicable tiene fracción y el adaptador no declara soporte decimal probado, bloquear esa publicación sin redondear | P1 | RN-78 |
| WOO-19 | **Precio efectivo único:** cuando el POS publica precio en P1, escribe `regular_price` y evita que `sale_price`/fechas remotas mantengan una segunda autoridad de precio | P1 | RN-79 |
| WOO-20 | **Stock de variaciones:** mapping de variante → variación usa stock a nivel variación; `manage_stock='parent'` o stock remoto compartido incompatible bloquea la sincronización hasta alinear configuración | P1 | RN-80 |

### 8.14 Estado de integraciones (`core`)

| ID | Requerimiento | Prio |
|---|---|:-:|
| INT-01 | **Panel de estado:** semáforo por integración (canal, DTE, licencia, respaldo); conexión, estado y último webhook recibido, última sincronización correcta por tipo, última reconciliación, trabajos pendientes y antigüedad del más antiguo, fallidos en 24 h y último error legible. Acciones: probar conexión, reintentar fallidos, sincronizar stock ahora, reconciliar ahora, verificar y reparar webhooks. Muestra además la advertencia de **copia física única de los XML fiscales** y las reservas online que no se pudieron reconciliar | P1★ |
| INT-02 | Contrato `IntegrationHealth` que cada adaptador implementa; alimenta `integration_health` | P1★ |
| INT-03 | Reglas de semáforo configurables (rojo: webhook desactivado, conexión caída más de 15 min, pendiente de más de 30 min, reconciliación sin ejecutar en 36 h) y aviso interno al pasar a rojo | P1 |
| INT-04 | Cada adaptador declara las categorías de datos que envía, visibles en solo lectura | P2 |

### 8.15 Reportes (`reports_basic`, `reports_advanced`)

| ID | Requerimiento | Prio |
|---|---|:-:|
| REP-01 | Resúmenes diarios calculados por cron, con sello "datos actualizados a HH:MM" | P0 |
| REP-02 | Reportes básicos: ventas, stock actual y valorizado, kardex, caja, vendedores | P0 |
| REP-03 | Margen por producto, categoría, marca, vendedor y sucursal | P1 |
| REP-04 | Exportación CSV y XLSX con permiso `reports.export`; las exportaciones masivas se alertan (SEC-11) | P1 |
| REP-05 | Panel de inicio por rol | P1 |
| REP-06 | ABC, rotación, quiebres, tendencias y valorización histórica por variante (instantáneas mensuales dispersas) | P2 |

### 8.16 Privacidad técnica (`core`, `customers`)

| ID | Requerimiento | Prio | Ref |
|---|---|:-:|---|
| PRV-01 | **Mapa de datos personales del producto** (documentación, Parte 2 §14) | P1 | |
| PRV-02 | RUT y contacto enmascarados por defecto; revelar exige `customers.view_sensitive` y se audita | P0 | RN-61 |
| PRV-03 | RUT cifrado con índice ciego; secretos cifrados | P0 | RN-58 |
| PRV-04 | Minimización: campos mínimos por operación, instantáneas reducidas y opcionales separados | P0 | RN-59 |
| PRV-05 | Herramientas de titular: buscar, exportar, rectificar, bloquear y anonimizar | P1★ | RN-62…64 |
| PRV-06 | Registro de acceso y cambio de datos personales en `audit_logs` | P0 | RN-61 |
| PRV-07 | Anonimización que limpia instantáneas, direcciones y cargas de sincronización | P1★ | RN-62 |
| PRV-08 | Archivos de exportación con datos personales: expiran y se auditan | P1 | RN-63 |
| PRV-09 | Anonimización de usuarios, vendedores y proveedores | P1 | RN-62 |
| PRV-10 | Comando `retention:prune` con las categorías de RN-65 | P1 | RN-65 |
| PRV-11 | Protección contra borrado de datos transaccionales y fiscales (modelos y permisos) | P0 | |
| PRV-12 | Anonimización de clientes inactivos, opcional y con simulación previa | P2 | |

### 8.17 Cuenta demo

| ID | Requerimiento | Prio |
|---|---|:-:|
| DEM-01 | Plan `demo` con todos los módulos y límites ilimitados | P0 |
| DEM-02 | `DemoDataSeeder` coherente (Parte 2, sección 13) | P0 |
| DEM-03 | Comando `demo:reset` y reinicio programado configurable | P0 |
| DEM-04 | Salvaguardas: canal en *dry-run*, emisor fiscal interno o de pruebas, correo deshabilitado, sin datos personales reales | P0 |
| DEM-05 | Usuarios de prueba por rol visibles en el login, solo en modo demo | P1 |
| DEM-06 | Interruptor de módulos "modo vitrina" para simular paquetes | P0 |
| DEM-07 | Datos de ejemplo por rubro | P2 |

---

## 9. Requerimientos no funcionales

### 9.1 Generales

| ID | Categoría | Requerimiento |
|---|---|---|
| RNF-01 | Rendimiento | Completar una venta en menos de 15 s de interacción; el guardado en servidor responde en menos de 1 s con hasta 6 estaciones por sucursal |
| RNF-02 | Rendimiento | Carga inicial del POS en menos de 3 s; consultas al kardex en menos de 500 ms |
| RNF-03 | Rendimiento | Búsqueda de producto en menos de 300 ms con 50.000 variantes |
| RNF-04 | Disponibilidad | Vender no depende de canales, del emisor fiscal ni del correo; toda dependencia externa se atiende por bandeja transaccional con reintentos |
| RNF-05 | Integridad | Toda operación que mueva stock o dinero es transaccional; kardex inmutable; sin borrado físico de datos transaccionales |
| RNF-06 | Concurrencia | Bloqueos de fila ordenados, idempotencia de envíos y sin bloqueos mutuos entre estaciones |
| RNF-07 | Resiliencia | Ante errores de servidor, el carrito no se pierde; al expirar la sesión se pide reautenticación en ventana modal sin cerrar la caja |
| RNF-08 | Compatibilidad | Chrome, Edge y Firefox 110+, Safari 16+; tablets de 10"; POS operable solo con teclado y escáner; objetivos táctiles de al menos 44 px |
| RNF-09 | Fechas | Base en UTC; cortes y reportes por día local `America/Santiago`, considerando el cambio de horario |
| RNF-10 | Observabilidad | Logs diarios con retención de 14 días; vista de últimos errores para el Admin sin *stack traces* en producción |
| RNF-11 | Volumen | Diseñado para 500 ventas/día/sucursal durante 5 años; resúmenes dispersos y purgas por categoría; índices definidos en la Parte 2 |
| RNF-12 | Internacionalización | Interfaz en es-CL; código y base de datos en inglés; moneda CLP y RUT chileno; sin multi-moneda |
| RNF-13 | Instalación | Instalación completa en cPanel compartido sin SSH en menos de 15 minutos |
| RNF-14 | Modularidad | Un módulo nuevo = registro en `config/modules.php` + carpeta de dominio, sin editar módulos existentes |
| RNF-15 | Demo | La cuenta demo no puede afectar sistemas externos reales ni contener datos personales reales |

### 9.2 Arquitectura desacoplada de la infraestructura (`ARQ`)

| ID | Requisito | Prio |
|---|---|:-:|
| ARQ-01 | Toda ejecución diferida usa jobs de Laravel con reintentos y *backoff* declarados; los jobs son **idempotentes** y no dependen de `--stop-when-empty` ni de un worker persistente | P0 |
| ARQ-02 | **Bandeja de salida transaccional por módulo:** los efectos externos se registran en la misma transacción que el cambio de negocio, en la tabla de su módulo. Canales → `channel_sync_events`; fiscal → `fiscal_documents` en `pending` más su job y un barredor. No existe una bandeja genérica | P0 |
| ARQ-03 | Caché solo vía `Cache`, sin *tags* ni estructuras propias de Redis; exclusión mutua con `Cache::lock` | P0 |
| ARQ-04 | Todo archivo de negocio (XML, PDF, exportes, respaldos) pasa por `Storage` con disco configurable; sin rutas absolutas; descargas por URL temporal firmada | P0 |
| ARQ-05 | Todas las tareas periódicas viven en el *scheduler* de Laravel (un único cron), con `withoutOverlapping` | P0 |
| ARQ-06 | Aplicación **sin estado local**: nada en memoria ni en archivos locales fuera de `Storage` | P0 |
| ARQ-07 | Sin SQL propio de MariaDB en el dominio; el SQL crudo se aísla y se prueba; bloqueos con `lockForUpdate` (no `GET_LOCK`); las columnas generadas son optimizaciones reemplazables | P0 |
| ARQ-08 | El dominio depende de **contratos**, enlazados en un único *service provider* | P0 |
| ARQ-09 | Cambiar `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` o `FILESYSTEM_DISK` no exige tocar código de dominio; la suite corre con `sync` y con `database` | P1 |
| ARQ-10 | Perfil de capacidad del hosting compartido y **umbrales de migración** (cola atrasada, pendientes crecientes, reconciliación lenta) alimentados desde `integration_health` | P1 |
| ARQ-11 | El perfil de hosting compartido se expresa solo en variables de entorno y en el *scheduler*; ningún módulo de dominio importa clases ni rutas propias de cPanel | P0 |
| ARQ-12 | **Serialización de efectos externos** por entidad con `Cache::lock` y relectura del estado vigente al ejecutar (RN-77); válida con caché de base de datos y con Redis | P1 |
| ARQ-13 | **Prueba de esquema en la versión mínima:** la integración continua crea el esquema completo en **MariaDB 10.6** (y en la versión LTS recomendada) y verifica los índices únicos sobre columnas generadas: `fiscal_documents.active_key`, `cash_sessions.open_station_key`, `cash_sessions.open_user_key`, `channel_sync_events.pending_dedupe_key`, `channel_mappings.external_key`, `channel_remote_items.external_key`, `sale_payments.channel_payment_key` y `sale_review_issues.active_key`. La prueba inserta filas que **deben colisionar** y filas que **no**. Si una expresión falla, esa columna se declara `PERSISTENT`; si aún falla, se valida en la aplicación bajo bloqueo. Resultado exigido antes del primer despliegue | P0 |

### 9.3 Seguridad base (`SEC`)

Referencia: OWASP ASVS nivel 1 como lista de verificación general y nivel 2 en autenticación, sesiones, control de acceso, criptografía y protección de datos.

| ID | Requisito | Prio |
|---|---|:-:|
| SEC-01 | **Contraseñas:** mínimo **15 caracteres** para las cuentas de aplicación, máximo admitido **≥64**, sin reglas artificiales de composición ni cambios periódicos forzados; lista local de contraseñas comunes/comprometidas bloqueadas. La comprobación online contra corpus de filtraciones es opcional (P2). El PIN de autorización es un secreto distinto y sigue SEC-12 | P0 |
| SEC-02 | **Limitación de intentos** en login, PIN y recuperación: bloqueo temporal tras 5 fallos. **No** aplica a webhooks (SEC-18) | P0 |
| SEC-03 | **MFA (TOTP + códigos de recuperación de un solo uso) obligatorio para Admin Central**; opcional para Supervisor | P1★ |
| SEC-04 | **Recuperación de cuenta:** restablecimiento por Admin (P0); procedimiento documentado para recuperar al último Admin (P1); por correo con token de un uso, hasheado y de 60 min, que invalida sesiones (P2, requiere SMTP) | P0–P2 |
| SEC-05 | **Sesiones:** cookie `Secure`, `HttpOnly`, `SameSite=Lax`; regenerar al iniciar sesión; inactividad 30 min (POS) y 60 min (administración); duración absoluta 12 h; invalidar al cambiar contraseña o rol | P0 |
| SEC-06 | **Autorización en backend:** política en cada acción, alcance de sucursal por *scope* global y **pruebas automáticas de la matriz rol × permiso × ruta** (403 e IDOR) | P0 |
| SEC-07 | **Protección web:** CSRF, escape por defecto, prohibido `v-html` con datos de usuario, cabeceras de seguridad y CSP (primero en modo informe) | P1 |
| SEC-08 | **Entradas:** solo Eloquent o consultas con parámetros; validación con `FormRequest`; asignación masiva controlada; subidas (CSV, logo) validadas y fuera de `public` | P0 |
| SEC-09 | **Eventos de seguridad** en `audit_logs` (`is_security_event`): fallos de acceso y bloqueos, fallos de PIN, cambios de rol o permisos, alta y baja de usuarios, exportaciones masivas, cambios de credenciales de integraciones, cambios de licencia, actualizaciones y descargas de respaldos. Vista filtrada para el Admin | P1★ |
| SEC-10 | Aviso interno al Admin ante eventos críticos | P1 |
| SEC-11 | Alerta ante exportaciones sobre `bulk_export_alert_threshold` registros | P1 |
| SEC-12 | **PIN de autorización:** hash, el autorizador se identifica y luego ingresa su PIN, bloqueo por intentos, nunca en registros | P0 |
| SEC-13 | Checklist ASVS completado antes del primer despliegue productivo | P1★ |
| SEC-14 | `/install` se bloquea al terminar; `/system/update` exige Admin con MFA y reconfirmación de contraseña | P0 |
| SEC-15 | Registros de error y de auditoría sin contraseñas, PIN, tokens ni RUT completo | P0 |
| SEC-16 | `.env` fuera de `public`; `APP_DEBUG=false` forzado por el instalador; rotación de `APP_KEY` con claves previas documentada | P0 |
| SEC-17 | `composer audit` y `npm audit` en el checklist de release; versiones fijadas | P1 |
| SEC-18 | **Protección de webhooks:** HMAC sobre cuerpo crudo, tamaño máximo, canal activo, comparación constante, idempotencia y límite de firmas inválidas por IP. Una entrega firmada válida que pudo persistirse responde 2xx; **no se aplica 429 por volumen normal a webhooks válidos**. Cola/backpressure + polling cubren ráfagas y fallos | P1 |

El producto **no reemplaza** el monitoreo de infraestructura: no detecta intrusiones a nivel de servidor o de red.

### 9.4 Respaldos (`BKP`)

| ID | Requisito | Prio |
|---|---|:-:|
| BKP-01 | `backup:run` diario con **cifrado AES-256** y clave propia (`BACKUP_ENCRYPTION_KEY`, distinta de `APP_KEY`). **Contenido:** base de datos completa (incluye ruta y hash de cada XML fiscal), libro de supresiones y configuración. **No incluye** los PDF (regenerables) ni el repositorio completo de XML, que se protege por replicación incremental (BKP-09) | P1★ |
| BKP-02 | Retención de 7 diarios y 4 semanales; el respaldo previo a una actualización se conserva hasta confirmar la salud | P1 |
| BKP-03 | Destinos externos (SFTP o compatible S3) para el respaldo de la base, el libro de supresiones y la réplica de XML | P2 |
| BKP-04 | Verificación automática de integridad tras crear el respaldo y alerta si falla | P1★ |
| BKP-05 | Procedimiento y comando de restauración documentados (base + paquete de secretos + libro de supresiones + **repositorio de XML fiscales desde su réplica**); simulacro trimestral recomendado. Objetivos: RPO ≤ 24 h (configurable a 12 h) y RTO ≤ 4 h | P1 |
| BKP-06 | **Libro de supresiones** incluido en cada respaldo y copiado al destino externo cuando exista; `privacy:reapply-ledger` tras restaurar solo la base. Sus límites se documentan (RN-66) | P1★ |
| BKP-07 | Descarga de respaldos solo para Admin con MFA, auditada y con URL temporal | P1 |
| BKP-08 | **Paquete de recuperación de secretos:** archivo cifrado con una contraseña elegida por el Admin que contiene `APP_KEY`, la clave del índice ciego y la clave de respaldo. Se genera al instalar y **debe guardarse fuera del servidor**; el panel muestra si se descargó y cuándo. No se incluye en los respaldos. Sin él, los campos cifrados no son recuperables. **Es una puerta de producción:** una instalación no demo no completa el onboarding sin descargarlo (en la demo, P0, no se exige) | P1★ |
| BKP-09 | **Replicación incremental de XML fiscales:** cada XML se copia a un destino configurable (`fiscal_backup_destination`) desde su creación, verificando el hash, y se registra `xml_replicated_at`. No es una copia nocturna del repositorio completo. En P1 se soporta un destino de ruta local distinta; SFTP y S3 llegan con BKP-03 | P1★ |
| BKP-10 | **Aviso de copia física única:** mientras `fiscal_backup_destination` sea `none` o no sea externo, o existan XML sin replicar por más de `fiscal_replica_max_lag_hours`, el panel de integraciones muestra una advertencia permanente | P1★ |
| BKP-11 | **Verificación periódica y progresiva** de existencia y hash de los XML (`xml_verified_at`); alerta ante un archivo faltante o con hash distinto | P1 |

**Restauración en otro servidor:** instalar la misma versión → importar el paquete de secretos → restaurar la base (con la clave de respaldo) → **restaurar el repositorio de XML fiscales desde su réplica y verificar hashes contra la base** → reaplicar el libro de supresiones si está disponible → verificación de salud. Recuperar solo la base **no** es una recuperación completa. Lo creado entre el último respaldo y la pérdida **se pierde**; es inherente y debe decirse al cliente.

### 9.5 Actualizador (`UPD`)

| ID | Requisito | Prio |
|---|---|:-:|
| UPD-01 | Paquete con `manifest.json`: versión, versión mínima de origen, PHP y MariaDB mínimas, hash SHA-256 de cada archivo, lista de migraciones y notas | P1 |
| UPD-02 | Firma Ed25519 del manifiesto (libsodium) verificada con una clave pública de la aplicación; un paquete sin firma válida se rechaza | P1 |
| UPD-03 | Verificación previa: versión, PHP, MariaDB, extensiones, permisos y espacio libre | P1 |
| UPD-04 | Modo mantenimiento durante la actualización | P1 |
| UPD-05 | Respaldo previo automático (base de datos y copia del código actual, sin `storage/` ni `.env`) | P1 |
| UPD-06 | Migraciones aditivas y compatibles hacia atrás (expandir y contraer en versiones distintas) | P1 |
| UPD-07 | Verificación de salud posterior; si falla, reversión automática de código y base **solo antes de salir de mantenimiento** (RN-73) | P1 |
| UPD-08 | Historial en `system_updates`; acceso con Admin, MFA y reconfirmación de contraseña; todo auditado | P1 |
| UPD-09 | Descarga del paquete desde el servicio central con la misma verificación | P3 |

### 9.6 Licencia y control central (`LIC`)

| ID | Requisito | Prio |
|---|---|:-:|
| LIC-01 | `installation_id` (UUID v4) generado al instalar; no deriva de datos personales | P1 |
| LIC-02 | **Latido diario saliente** con carga estrictamente listada: `installation_id`, `app_version`, `schema_version`, canal de versión, módulos activos, estado y fecha de la última actualización, marca de tiempo. **Nunca** clientes, ventas, RUT, documentos, contactos ni información comercial. Una prueba automática verifica que no hay campos adicionales | P1 |
| LIC-03 | Respuesta: estado de licencia, plan, límites, módulos, versión más reciente y mínima compatible | P1 |
| LIC-04 | Tolerancia sin conexión: se usa el último estado válido, se avisa tras 7 días sin verificar y no se bloquea la operación | P1 |
| LIC-05 | Respuesta firmada (Ed25519). Evita suplantar la respuesta; no impide modificar el código, algo imposible de garantizar en PHP instalable | P2 |
| LIC-06 | Conteos agregados de uso para verificar límites del plan (requiere aprobación previa: es información comercial) | P2 |

Contrato `LicenseValidator`: `isValid`, `getLicenseInfo`, `getLimits`, `hasModule`, `getDaysRemaining` y `reportStatus(InstallationStatusReport)`; el stub devuelve todo ilimitado. Etapa 2 (P3): salud, versiones de PHP y MariaDB, avisos de actualización y de vulnerabilidades, con un campo `extensions` reservado y vacío en el latido.

---

## 10. Flujos clave

### 10.1 Venta directa
1. El operador escanea o busca productos y arma el carrito.
2. Si hay política de vendedor, ingresa su código; si el cliente entrega su RUT, se aplica su descuento.
3. La vista previa calcula precios, promociones, topes y avisos.
4. Si algo supera un tope o requiere precio manual, se abre la ventana de PIN.
5. Se registran uno o más pagos; se calculan vuelto y redondeo.
6. Se confirma: una transacción → venta `completed` → registro fiscal `pending` → eventos (stock, caja, agregados, publicación a canales).
7. Se imprime el comprobante; el documento tributario se emite por el motor fiscal.

### 10.2 Ticket → caja
1. El vendedor arma el carrito y genera el **ticket** (`pending_payment`); se reserva stock por línea; se imprime con código corto y QR.
2. El cajero escanea o digita el código en "Cobrar ticket"; el sistema bloquea el ticket y revalida stock y precios.
3. El cajero cobra; la venta pasa a `completed` y la reserva se **consume** (salida de stock).
4. Si el ticket vence o se cancela, la reserva se libera.

### 10.3 Pedido online (ejemplo con WooCommerce)
1. El canal envía un webhook (o lo recupera el sondeo): se valida canal, tamaño, firma e idempotencia.
2. `OrderIngestionService` hace *upsert* por `(canal, pedido)`; descarta eventos más antiguos que `external_modified_at`.
3. Según el estado normalizado (RN-39): crea o actualiza la venta (con líneas idempotentes por `external_line_id`), la reserva (`channel` o `internal`) y el pago (`pending` o `confirmed`; un único pago principal).
4. Si falta stock se abre `stock_shortage`; si hay un ítem sin vincular, `unlinked_item` (la línea se guarda sin variante y sin reserva); si la pasarela no tiene mapeo, `payment_mismatch`. Las incidencias pueden coexistir.
5. Un supervisor o bodeguero prepara y despacha (`fulfillment_status`); al despachar se consume la reserva (`sale_online`).
6. El estado vuelve al canal con una nota privada. El DTE se solicita cuando se satisface el disparo del método de pago (RN-51). Los reembolsos del pedido se reconcilian como recursos propios (10.9).

### 10.4 Emisión fiscal
El `FiscalEngine` evalúa el disparo tras los eventos relevantes (venta POS pagada, pedido online pagado, pedido despachado o solicitud manual) → al satisfacerse, registra `fiscal_documents(pending)` en la **misma transacción de ese evento** → job tras confirmar → adaptador → `issued`. Si hay resultado incierto: `unknown` → consulta al proveedor por la referencia → resolver (nunca reemitir a ciegas). Si algo exige intervención, la venta queda `action_required`. Anular o devolver: nota de crédito **con aprobación**; el original permanece `issued`.

### 10.5 Devolución
1. Se busca la venta (número, ticket, RUT o escaneo).
2. Se eligen líneas y cantidades, motivo, restock o merma y método de reembolso.
3. Se validan el tope por rol y el plazo; si se exceden, PIN.
4. Se crean movimientos, se descuenta de la caja si el reembolso es en efectivo y se solicita la nota de crédito.

### 10.6 Cierre de caja
1. El cajero pide el cierre; si es ciego, declara el efectivo sin ver el esperado.
2. El sistema calcula totales por método y diferencias (RN-34, RN-35).
3. Si la diferencia supera la tolerancia, un supervisor aprueba con PIN.
4. Se imprime el reporte Z con totales por método y **listado de todas las ventas**.

### 10.7 Contenido del comprobante interno
Logo y nombre del comercio · RUT y dirección de la sucursal · cajero y estación · vendedor · número por sucursal · fecha y hora · líneas (cantidad, descripción, precio unitario, subtotal) · descuentos · neto, impuesto y tasa · total · medios de pago, vuelto y redondeo · leyenda "Este documento no es válido como boleta electrónica" (solo adaptador interno) · código de barras del comprobante para devoluciones.

### 10.8 Activación de un módulo
El Admin abre Configuración → Módulos; solo se listan los incluidos en la licencia; al activar se validan dependencias, se crean permisos y menús y se registran tareas programadas; al desactivar se valida que no haya dependientes y los datos se conservan.


### 10.9 Reembolso de un pedido online
1. El comercio reembolsa en WooCommerce (total, parcial, por productos y eventualmente con shipping/fees); un refund parcial puede no cambiar el estado general del pedido.
2. Un `order.updated` o el sondeo funciona como señal. El adaptador consulta `/wp-json/wc/v3/orders/{order_id}/refunds` y compara IDs externos con los ya importados.
3. Cada refund nuevo se importa idempotentemente por `external_refund_id` → `sale_returns` (`source='channel'`, `pending_review`). Las cantidades e importes de producto se normalizan a magnitudes positivas y se conservan como `net_amount`, `tax_amount` y `line_amount`; una misma línea aparece a lo más una vez dentro de ese refund.
4. Toda línea importada comienza con `restock=NULL`. El supervisor decide `1` (reingresa físicamente) o `0` (no reingresa/merma). Un refund del canal no puede cancelarse como si no hubiese ocurrido.
5. Si `refund_amount` difiere de la suma de `line_amount` —por ejemplo, por despacho o fees— la incidencia `refund_review` permanece abierta y la nota de crédito automática queda bloqueada. P1 conserva la diferencia y exige resolución manual/fiscal; no inventa líneas de producto para cuadrarla.
6. Cuando todas las líneas vinculadas tienen decisión física y no quedan diferencias fiscales pendientes, el retorno pasa a `completed`; se aplica stock solo a `restock=1` y el `FiscalEngine` solicita la nota de crédito. Las líneas aún no vinculadas conservan el hecho financiero y difieren únicamente el efecto físico.
---

## 11. Catálogo de reportes

| Grupo | Reporte | Módulo | Prio |
|---|---|---|:-:|
| Ventas | Por día, sucursal, canal y hora | reports_basic | P0 |
| Ventas | Por medio de pago, cajero y estación | reports_basic | P0 |
| Vendedores | **Ventas mensuales por vendedor** con detalle por venta | reports_basic | P0 |
| Vendedores | Comisiones | reports_advanced | P1 |
| Control | Descuentos otorgados (por vendedor, autorizador, sobre tope) | reports_advanced | P1 |
| Control | Anulaciones, devoluciones y precios manuales | reports_advanced | P1 |
| Control | Autorizaciones (`authorization_overrides`) | reports_basic | P0 |
| Caja | Sesiones, diferencias y movimientos | reports_basic | P0 |
| Margen | Por producto, categoría, marca, vendedor y sucursal | reports_advanced | P1 |
| Productos | Más vendidos | reports_advanced | P1 |
| Productos | Sin movimiento y ABC | reports_advanced | P2 |
| Inventario | Stock actual, crítico y valorizado (actual y por fecha agregada) | reports_basic | P0 / P1 |
| Inventario | Kardex valorizado | reports_basic | P0 |
| Inventario | Traspasos en tránsito, conteos y ajustes | reports_basic | P1 |
| Inventario | Valorización histórica por variante | reports_advanced | P2 |
| Compras | Recepciones y compras por proveedor | reports_basic | P1 / P2 |
| Clientes | Compras por cliente y clientes inactivos | reports_advanced | P1 |
| Online | Pedidos por revisar y estado de sincronización | channels | P1 |
| Fiscal | Documentos por estado y libro de ventas | fiscal | P1 / P2 |

---

## 12. Privacidad y seguridad por diseño

### 12.1 Privacidad: qué hace el sistema con los datos que realmente maneja

| El POS hace… | Procesa… | Por eso necesita… |
|---|---|---|
| Identificar al cliente para aplicar su descuento | RUT, nombre, contacto | Buscar por RUT sin exponerlo: cifrado e índice ciego; enmascarado por defecto (PRV-02, PRV-03) |
| Mostrar datos a varios usuarios | Datos personales | Permiso específico y registro de quién los vio (PRV-02, PRV-06) |
| Capturar datos al vender | Campos de cliente | Pedir solo lo necesario; instantáneas reducidas (PRV-04) |
| Conservar ventas y documentos tributarios | Datos transaccionales | Impedir su borrado y anonimizar solo la parte personal (PRV-07, PRV-11) |
| Atender a una persona que pide sus datos | Datos personales | Buscar, exportar, rectificar, bloquear y anonimizar (PRV-05) |
| Guardar respaldos | Toda la base | Cifrado, retención limitada y libro de supresiones (BKP) |
| Enviar datos a integraciones | Datos hacia terceros | Mostrar qué integración envía qué categorías de datos (INT-04) |

**Queda fuera del producto** (es responsabilidad del cliente como responsable del tratamiento): bases jurídicas, registro de tratamientos, contratos con encargados, transferencias internacionales, plazos y flujos de solicitudes, gestión de incidentes, evaluaciones de impacto y rol de DPO.

### 12.2 Contexto normativo
- **Ley 21.719:** a la fecha de congelación de este documento (**04-10-2026**), el texto oficial de LeyChile mantiene la entrada en vigencia el **1 de diciembre de 2026**. El diseño se construye contra ese texto vigente y **no depende de una eventual postergación legislativa**. El POS aporta herramientas técnicas sobre los datos que trata; las obligaciones organizacionales y sus plazos las administra el responsable, con su asesor jurídico.
- **DTE/boleta electrónica:** documento de venta a consumidor final (tipos 39 afecta y 41 exenta), con representación impresa o digital. Factura (33 y 34), nota de crédito (61), nota de débito (56) y guía de despacho (52) completan el conjunto. El XML fiscal y los respaldos exigibles se conservan según la normativa aplicable; el proyecto fija **6 años** como retención fiscal. **El emisor es el contribuyente**. Para P1 se integra un **proveedor DTE por API detrás de `FiscalDocumentIssuer`**; una integración directa con SII queda fuera hasta que exista un proyecto específico de certificación/operación. El SII no certifica el software comercial de los proveedores de su nómina informativa. El sistema tampoco pretende reemplazar ni integrar directamente el RCV oficial del SII.
- Todo lo marcado P0 y P1★ en `PRV`, `SEC`, `BKP` y `FIS` debe estar completo **antes de la primera instalación productiva**.

### 12.3 Seguridad
Baseline SEC-01…18 y ASVS (nivel 1 general; nivel 2 selectivo). Limitación honesta: es una aplicación web instalable en hosting compartido; no detecta intrusiones de servidor ni reemplaza el monitoreo de infraestructura.

---

## 13. Hoja de ruta y advertencia de alcance

**Advertencia.** P0 + P1 es sustancialmente mayor que "un MVP en 2 meses" con un solo desarrollador. Las duraciones son estimaciones gruesas para discutir, no compromisos.

| Hito | Contenido | Semanas (aprox.) | Demostración |
|---|---|:-:|---|
| H0 · Cimientos | Instalador, núcleo, módulos, licencia y planes, RBAC, PIN, auditoría, secuencias, contratos ARQ, esqueleto de demo | 1–3 | Login, módulos activables, plan demo |
| H1 · Catálogo e inventario | Catálogo, kardex, reservas, traspasos, ajustes, alertas, recepciones, reportes base | 4–7 | **Demo "Bodega"** |
| H2 · Ventas y POS | Venta directa y ticket, POS, precios y descuentos con topes, caja, vendedores, clientes y privacidad técnica | 8–13 | **Demo "POS"** |
| H3 · Canales y WooCommerce | Canal, publicación, webhooks, sondeo de respaldo, pedidos online, panel de integraciones | 14–18 | Demo "Omnicanal" |
| H4 · DTE y cierre comercial | Motor fiscal y proveedor, listas de precio, promociones, combos, reportes avanzados, CSV, respaldos, actualizador, seguridad base | 19–24 | **MVP productivo** |

**Ruta recomendada para 2 meses:** H0 + H1 + venta directa y caja de H2, mostradas en la cuenta demo. El resto se libera por incrementos sobre la misma demo.

**Fase 2 (P2):** órdenes de compra, crédito a clientes, fulfillment desde varias sucursales y enrutamiento por comuna, cotizaciones, etiquetas, ABC, cupones, roles personalizados, respaldo externo, nota de débito y guía. **Fase 3 (P3):** modo sin conexión, lotes y series, fidelización, apartados, agente de impresión.

| Riesgo | Mitigación |
|---|---|
| Alcance superior al plazo | Ruta recomendada y entregas por hito en la demo |
| Ley 21.719 vigente desde el 01-12-2026 | Priorizar PRV, SEC y BKP en P0 y P1★ antes de instalar |
| Un solo desarrollador | Módulos independientes, eventos y pruebas automáticas del motor de precios, kardex, reservas y fiscal |
| cPanel sin SSH | Instalador y actualizador web; `vendor/` incluido en el paquete |
| Emisor fiscal o canal caídos | `allow_pending`, reintentos y reconciliación |
| Pérdida de claves de cifrado | Paquete de recuperación de secretos fuera del servidor |
| Espacio en disco y copia única de los XML fiscales | Compresión, disco configurable, réplica incremental a un destino distinto y aviso permanente de copia física única; medir el tamaño real con XML de muestra del proveedor |

---

## 14. Decisiones pendientes (⚑)

1. **Primer proveedor DTE** (SuperFactura, SimpleFactura u otro): precio por documento, soporte, límites y consulta por referencia externa.
2. **Momento de emisión por método de pago** (`on_paid`, `on_dispatch`, `manual`): validar con un contador.
3. **`LIC-06`:** ¿se envían conteos agregados de uso al control central?
4. **MFA** para Supervisor: ¿obligatorio u opcional?
5. **SMTP:** ¿los clientes suelen tener correo saliente? Afecta avisos por correo y recuperación por correo.
6. **Respaldo externo** (SFTP o S3): ¿entra al MVP o queda en P2?
7. **Topes de descuento:** ¿aplican también a promociones? (por defecto no)
8. **TTL de reservas** de pedidos `on-hold`: propuesta de 72 h para transferencia; hasta el despacho para contra entrega.
9. **Retención por defecto:** auditoría 24 meses, `shipping_snapshot` 180 días, PDF fiscales 90 días.
10. **Plazos legales** (derechos de titulares y vulneraciones): los gestiona el responsable con su asesor jurídico; el producto no los controla.
11. **Código para cambiar precios (S1)** y **código de vendedor (S2):** confirmar interpretación.
12. **Plazo:** ¿se acepta el hito de 2 meses redefinido como "Demo Bodega + POS básico"?
13. **Destino externo de los XML fiscales:** ¿es obligatorio para clientes productivos? Mientras no exista, el panel advierte copia física única (BKP-10).

---

## 15. Trazabilidad de los aportes del experto de ventas

| # | Aporte | Requerimientos | Reglas | Tablas (Parte 2) |
|---|---|---|---|---|
| 1 | Código por vendedor | SEL-01, SEL-02, POS-04 | RN-11…14 | `sellers`, `branch_seller`, `sales.seller_id` |
| 2 | Crear clientes | CUS-01, POS-03 | RN-58, RN-59 | `customers` |
| 3 | Descuento según RUT | CUS-01, CUS-03 | RN-01, RN-58 | `customers.fiscal_id_hash`, `customers.default_discount_percent` |
| 4 | % máximo de descuento a productos | CAT-06 | RN-02 | `product_variants.max_discount_percent`, `categories.max_discount_percent` |
| 5 | Cambiar precios mediante un código | CORE-11, POS-05, SAL-06 | RN-05 | `authorization_overrides`, `users.pin_hash` |
| 6 | Ofertas de precio único fuera del sistema | SAL-06 | RN-05 | `sale_items.is_price_overridden` |
| 7 | Descuento máximo según cliente | CUS-03 | RN-02 | `customers.max_discount_percent`, `customer_groups` |
| 8 | Ticket que luego cobra la caja | SAL-03, SAL-04 | RN-15…17 | `sales.status`, `sales.ticket_number`, `stock_reservations` |
| 9 | Locales chicos: caja directa | SAL-02 | RN-15, RN-18 | `branches.sales_flow_mode` |
| 10 | Impresora fiscal / boleta o factura | FIS-01…14 | RN-49…57 | `fiscal_documents` |
| 11 | Productos por costo y % de ganancia | CAT-04, INV-09, REP-03 | RN-24, RN-25 | `product_variants.cost_price`, `sale_items.unit_cost_snapshot` |
| 12 | "Cuánto el descuento máximo" | CORE-03, CORE-04 | RN-02 | `roles.max_discount_percent`, `users.max_discount_percent` |
| 13 | Informe mensual por vendedor | SEL-03, REP-02 | — | `daily_seller_sales_summaries` |
| 14 | Vendedor que cobra directo con su código | SEL-02, POS-04 | RN-11, RN-12 | `sales.seller_id` |

**Aportes añadidos tras investigar POS de referencia:** topes de descuento por rol y monto, autorización por PIN, códigos de motivo, protección de venta bajo costo, cierre ciego, ventas en espera, anulación de línea, consulta de precio, movimientos de caja y "abrir cajón sin venta", límites de devolución por rol, cotizaciones, apartados, listas de precio por grupo, etiquetas de precio y códigos de balanza.

---

## 16. Fuentes técnicas y normativas verificadas para congelar v2.1

**Fecha de verificación:** 04-10-2026. Estas fuentes validan decisiones de interfaz/compatibilidad; no sustituyen los tests de contrato contra la tienda y proveedor concretos.

- WooCommerce Developer — REST API v3 (current/recommended): `https://developer.woocommerce.com/docs/apis/rest-api/v3/`
- WooCommerce Developer — REST API Authentication: `https://developer.woocommerce.com/docs/apis/rest-api/authentication`
- WooCommerce Developer — REST API v3 / Orders: `https://developer.woocommerce.com/docs/apis/rest-api/v3/orders/`
- WooCommerce Developer — REST API v3 / Products: `https://developer.woocommerce.com/docs/apis/rest-api/v3/products/`
- WooCommerce Developer — REST API v3 / Product Variations: `https://developer.woocommerce.com/docs/apis/rest-api/v3/product-variations/`
- WooCommerce Developer — REST API v3 / Order Refunds: `https://developer.woocommerce.com/docs/apis/rest-api/v3/order-refunds/`
- WooCommerce Developer — REST API v3 / Webhooks: `https://developer.woocommerce.com/docs/apis/rest-api/v3/webhooks/`
- WooCommerce Developer — HPOS recipe book: `https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/`
- WordPress Developer — REST API pagination: `https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/`
- Laravel — Task Scheduling / locks: `https://laravel.com/docs/12.x/scheduling`
- Laravel — Cache / atomic locks: `https://laravel.com/docs/cache`
- SII — Proveedores de soluciones de facturación electrónica: `https://www.sii.cl/factura_electronica/prov/emp_prov_fe.htm`
- SII — FAQ Registro de Compras y Ventas e integración: `https://www.sii.cl/preguntas_frecuentes/factura_electronica/001_003_7003.htm`
- BCN LeyChile — Ley 21.719: `https://www.bcn.cl/leychile/Navegar?idNorma=1209272`

**Regla de mantenimiento:** si WooCommerce, Laravel, SII o la normativa cambia, primero se actualiza el adaptador/anexo y su suite de contratos. El dominio solo cambia si el cambio externo altera una regla de negocio real.

