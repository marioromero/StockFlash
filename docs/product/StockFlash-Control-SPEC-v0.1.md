# StockFlash Control — contrato inicial del servicio central (v0.1 EN REVISIÓN)

## 1. Propósito
Aplicación **privada del fabricante** para administrar clientes comerciales, productos contratados, suscripciones, licencias, instalaciones y registro **manual** de cobros. No es SaaS multitenant con datos operacionales compartidos: cada comercio tiene su propia instancia de StockFlash con BD separada. `Control` no se vende como módulo del cliente.

## 2. Funciones aprobadas
- CRUD seguro de clientes comerciales/organizaciones y sus contactos administrativos, con permisos/auditoría y retención aplicable.
- Definir `plans` comerciales y combinaciones por producto (`pos`, `bodega`, `syncwp`, `fiscal`, `compras`, `analitica`), sin obligar a comprar un producto técnico auxiliar.
- Registrar solicitudes y activaciones de licencias por `installation_id` UUID v4, versiones, derechos comerciales y limites cuantitativos.
- Registrar contratos/periodos, fechas de inicio/fin/gracia, renovaciones y cambio de plan.
- Registrar manualmente pago esperado/recibido, medio/referencia administrativa, estado pendiente/conciliado/vencido, sin cobrar automáticamente ni integrar pasarelas en la versión inicial.
- Administrar `demo` Vitrina (instalación sintética, reiniciable) y `free` Persistente (instalación real, limitada) como modalidades separadas.
- Mostrar salud de sincronización de licencia exclusivamente con los campos permitidos por LIC-02; ningún contacto, venta, RUT o dato comercial operacional circula en heartbeat.

## 3. Limitaciones y protección
- Control tiene **BD separada**; nunca hace consultas SQL sobre bases de comercio ni almacena ventas por ticket o cliente final.
- Sus datos de facturación comercial pertenecen a la empresa que presta StockFlash y tienen controles de acceso/cifrado/retención específicos. No confundirlos con heartbeat.
- Clave PRIVADA de firma de licencias reside solo en Control/servicio seguro; cada cliente valida offline con clave PÚBLICA.
- `products_enabled` y `max_monthly_sales` se distribuyen en payload de **respuesta** de licencia, no como reporte periódico de operaciones al Control. Validar firma, expiración y versión.
- Estados y límites locales se administran incluso si el servidor central está temporalmente inaccesible, manteniendo garantías LIC-04 y política de gracia aprobada.

## 4. Propuesta de modelo independiente (sujeto a revisión de migraciones)
`commercial_accounts`, `account_contacts`, `commercial_products`, `commercial_plans`, `plan_entitlements`, `subscriptions`, `subscription_periods`, `subscription_payments`, `licenses`, `licensed_installations`, `license_events`, `control_users`, `control_audit_logs`.
Esta lista es diseño inicial: las columnas, integridad FK, índices y permisos requieren una especificación de modelo separada antes de migrar. No inventar relaciones a tablas `sales` locales.

## 5. Gate de feature
Antes de iniciar `StockFlash Control` real, aprobar su Feature Packet con navegación, RBAC central, modelo de licencias, firma, verificación offline y modelo físico. No habilitar cobro automático.
