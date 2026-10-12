# DOC-001 — Matriz de auditoría y pendientes (2026-10-12)
**Alcance auditado:** 3 specs v2.2, modelo, OpenCode/skills, CI/CD, cPanel y contrato StockFlash Control. El cumplimiento de DOC-001 es DOCUMENTAL: no certifica una aplicación que todavía no existe.

| Hallazgo | Estado tras DOC-001 | Gate siguiente |
|---|---|---|
| A01 Bootstrap bloqueado por ausencia de Laravel y microautorizaciones | Corregido en AGENTS, sf-feature, implementer, workflow y BOOT | Prueba real BOOT-001 |
| A02 External vs FiscalEngine y comprobante | Contrato reconciliado: recibo desde sales; fiscal_documents solo integrado | Prueba transaccional POS e inspección tributaria R6 |
| A03 Migración sale_external_tax_records ausente/incongruente | Corregido lote 09 con FK a sales/users | Test de migraciones R3 |
| A04 Cuota 500 y capacidades Gratis | **Abierto**; no inventar | ADR-01 + ADR-02 antes de cobro/free comercial |
| A05 StockFlash Control modelo físico/API/firma/gracia | **Abierto**, solo contrato inicial | Modelo físico y API + ADR-05 antes de R5 |
| A06 GitHub SSH usa cuenta cPanel general grupoint | **Bloqueo de despliegue** documentado; solo SSH_OK seguro | Aislar usuario/comandos/rutas, rotar credenciales, R2 |
| A07 Stack/versiones y TypeScript | Compatibilidad registrada, selección Laravel en BOOT | Composer/lockfiles BOOT-001 |
| A08 Node 20 del servidor | CI Node24 propuesto; servidor no compila assets | Build CI R2 |
| A09 Los permisos OpenCode no son sandbox; autonomía por subtarea | Límites explicitados; autonomía por Feature Packet sin eludir ask | Probar herramientas/agentes en BOOT |
| A10 Guías declaran CI inexistente | Corregidas: existen workflow SSH y chequeo documental, NO CI Laravel ni deploy | Pipeline CI R2 |
| A11 Pausas P0 vs carrito reload P1 vs favoritos P2 | Alcance delimitado, no modificadas prioridades | Feature UX POS R6 |
| A12 Conflicto gracia/offline/suspensión | **Abierto**; no se resuelve en DOC-001 | ADR-05 antes de venta real |
| A13 Guías obsoletas | LEEME original estaba corregido; guía Warp/CPANEL, release y skill requirements actualizadas | Comprobación periódica documental |
| A14 Índice de requisitos | Verificado y corregido según Parte1 actualizada: 362 referencias | Test de consistencia documental |

**Distinción clave:** 'Corregido' en esta tabla significa que se reparó el contrato o la guía. No demuestra que Laravel, pagos, licencias o CI se hayan probado.
**No tocado:** bases de datos, servidor, hosting, claves, secretos, workflow SSH operativo y archivos históricos v2.1.
**Roadmap:** `docs/harness/ROADMAP-STOCKFLASH.md`. **ADR de esta corrección:** `docs/adr/ADR-DOC-001.md`.
