# StockFlash — Manual de usuario

Este índice crece de forma acumulativa: cada funcionalidad aprobada e implementada aporta su propia guía aquí.

## Público objetivo

Propietarios de negocios, administradores, cajeros, vendedores y otros usuarios del sistema StockFlash.

## Estructura

| Carpeta | Contenido |
|---------|-----------|
| `general/` | Introducción, navegación y funciones transversales (acceso, contraseña) |
| `modulos/<modulo>/` | Guías por producto o módulo (POS, Bodega, SyncWP, Fiscal, Compras, Analítica, Control) |

## Módulos comerciales de StockFlash

- **POS** — Punto de venta, cobro, caja y venta en mostrador.
- **Bodega** — Gestión de inventario, recepciones, traspasos y conteos.
- **SyncWP** — Sincronización de contenido y pedidos con canales de venta.
- **Fiscal** — Emisión y control de documentos tributarios (DTE).
- **Compras** — Gestión de compras a proveedores y recepción de mercadería.
- **Analítica** — Reportes y tableros de ventas, stock y caja.
- **Control** — Plataforma privada de administración: clientes, licencias y suscripciones (no es un módulo vendido por separado).

## Guías disponibles

| Funcionalidad | Guía | Módulo | Estado |
|---------------|------|--------|--------|
| Inicio y cierre de sesión | `general/acceso.md` | Transversal | Implementada (AUTH-001 APPROVED) |
| Cambio de contraseña | `general/cambio-contrasena.md` | Transversal | Implementada (AUTH-001 APPROVED) |
| Administración de sucursales | `modulos/sucursales/administracion-sucursales.md` | Administración interna (Control) | Implementada (BRANCH-001 APPROVED) |
| Administración de usuarios | `modulos/usuarios/administracion-usuarios.md` | Administración interna (Control) | Implementada (USERS-001 APPROVED) |

## Estado de la documentación

Este manual comienza vacío de módulos y crece a medida que se aprueban e implementan nuevas funcionalidades. Una funcionalidad se considera completamente revisada solo cuando cuenta con su guía de usuario correspondiente.

> La documentación no constituye evidencia de publicación en staging o producción. Distingue siempre entre funcionalidades implementadas localmente y disponibles comercialmente.
