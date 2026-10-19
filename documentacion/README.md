# StockFlash — Manual de usuario (en construcción)

Este documento es el índice del manual de StockFlash, el software POS modular para el comercio chileno.

Este manual se construye de forma **acumulativa**: cada nueva funcionalidad aprobada e implementada aporta su propia guía de usuario aquí. No se genera toda la documentación de golpe, ni se documentan funciones internas de implementación (clases, migraciones, comandos técnicos).

## Público objetivo

Propietarios de negocios, administradores, cajeros, vendedores y otros usuarios del sistema StockFlash.

## Estructura

| Carpeta | Contenido |
|---------|-----------|
| `general/` | Introducción, navegación y funciones transversales |
| `modulos/<modulo>/` | Guías por producto o módulo (POS, Bodega, SyncWP, Fiscal, Compras, Analítica, Control) |

## Módulos comerciales de StockFlash

- **POS** — Punto de venta, cobro, caja y venta en mostrador.
- **Bodega** — Gestión de inventario, recepciones, traspasos y conteos.
- **SyncWP** — Sincronización de contenido y pedidos con canales de venta.
- **Fiscal** — Emisión y control de documentos tributarios (DTE).
- **Compras** — Gestión de compras a proveedores y recepción de mercadería.
- **Analítica** — Reportes y tableros de ventas, stock y caja.
- **Control** — Plataforma privada de administración: clientes, licencias y suscripciones (no es un módulo vendido por separado).

## Estado de la documentación

Este manual comienza vacío de módulos y crece a medida que se aprueban e implementan nuevas funcionalidades. Una funcionalidad se considera completamente revisada solo cuando cuenta con su guía de usuario correspondiente.

> La documentación no constituye evidencia de publicación en staging o producción. Distingue siempre entre funcionalidades implementadas localmente y disponibles comercialmente.
