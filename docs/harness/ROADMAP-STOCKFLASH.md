# Roadmap StockFlash — entregables y puertas (2026-10-12)
**No son fechas prometidas.** Etapas basadas en dependencias y criterios verificables; algunas podrán avanzar en paralelo.

| Etapa | Entregable | Gate antes de avanzar |
|---|---|---|
| R0 DOC-001 | Contratos externo/fiscal coherentes, lote 09 corregido, harness y matriz de auditoría | Índice/hash verificados por `scripts/check_contracts.py` y CI `contract-check.yml`; ADR abiertos señalados |
| R1 BOOT-001 | Laravel compatible PHP8.3 + Vue3/Inertia/TypeScript + marca textual local | `composer validate`, pruebas, `npm run build`, ruta Vue en navegador, cero DB/migración |
| R2 CI / staging seguro | CI PHP+Node24, artefactos, smoke; despliegue aislado | Pruebas CI, clave/usuario de privilegio mínimo, rollback; NO usar clave SSH general actual para deploy |
| R3 Core | Instalador, usuarios/RBAC, sucursales, licencias técnicas, catálogo base y BD | Migraciones reproducibles en MariaDB >=10.6, autorización server-side y contratos de dominio probados |
| R4 Bodega | Productos/variantes, kardex, existencias, ajustes, transferencias y reportes | Pruebas de stock, permisos, concurrencia, UX y trazabilidad |
| R5 Control | App independiente: clientes comerciales, planes, licencias Ed25519, cobros manuales y free/demo | ADR-01/02/05, modelo físico/API Control, firma, gracia/offline/entitlements probados |
| R6 POS sin DTE | Ventas, tickets, caja, split pay, devoluciones, pausas por estación, recibo interno | ADR-03, caja/stock transaccionales, NO fiscal_documents en modo external, control de concurrencia |
| R7 Piloto y primer lanzamiento | POS+Bodega comercializables con control de suscripción | Respaldos+restore, privacidad/seguridad, permisos, emisión externa validada legal/tributariamente |
| R8 SyncWP | WooCommerce REST/webhooks/polling, catálogo/stock/pedidos/refunds | Replay, reconciliación y fallos idempotentes probados |
| R9 Fiscal | Adaptador DTE autorizado, XML, NC, documentos por emisor | Integración legal/tributaria, idempotencia, retención/backup y ADR-04 para entradas autónomas |
| R10 Compras/Analítica | Productos comerciales independientes de evolución | ADR-06, alcance independiente y QA |

**Paralelismo:** R5 puede progresar junto a R4/R6 después de fijar contratos del servicio central. CI local puede prepararse antes de aislar SSH, pero no el deploy. No entregar R7 solo porque una demo funcione.
**Autonomía OpenCode:** una Feature Packet aprobada autoriza la implementación y pruebas del alcance, con checkpoints, reviewer independiente y permisos `ask` intactos. Humano y ChatGPT deciden cambios de contrato, nuevas dependencias no aprobadas o releases productivos.
