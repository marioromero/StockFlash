# Estado operativo — StockFlash

## Feature activa
- **POSSTATION-001 — APPROVED**, R6 P0 CSH-02 + POS-02: administración de estaciones POS y vinculación de navegador por token aleatorio, solo con permisos existentes, sin crear ventas ni caja. Implementación técnica completada sobre esquema `pos_stations` existente; pendientes gates deterministas, QA independiente y CI exact-SHA. Sin BD remota ni despliegue; no VERIFIED. Notas: `docs/work/POSSTATION-001-NOTES-20261124.md`.

## Estados por feature
- BOOT-001: VERIFIED (2026-10-14), Laravel 13.35.0 + Vue 3/Inertia/TS; `bcbf16f`.
- HARNESS-001: VERIFIED; comandos `/sf-*`.
- HCTX-001: VERIFIED (2026-11-16), `9beb834`; mapa de lectura y contexto.
- BRAND-001: VERIFIED prototipo SVG; PNG oficiales prevalecen.
- CI-001: VERIFIED (2026-10-14), GitHub Actions y contratos; `218de3a`.
- AUTH-001: VERIFIED (2026-11-09), `55b7f16`; BRANCH-001: VERIFIED (2026-11-10), `4f81457`.
- DOCS-001: VERIFIED (2026-11-10), `90254e5`; ROADMAP-001: VERIFIED, `a9c19d4`.
- USERS-001: VERIFIED (2026-11-16), `2aec038`; MariaDB concurrente y visual real diferidos.
- CFG-001/002/003: VERIFIED técnico (2026-11-16), `fd12693`/`81ba734`/`045b8c8`; 220/239/260 pruebas; sin navegador real.
- CORESEQ/CORELIC/COREMOD/COREPIN: VERIFIED técnico (2026-11-16), `ceb50e0`/`a2cc899`/`cd6d39d`/`5ea1b3b`; concurrencia MariaDB diferida.
- CATDB-001: VERIFIED técnico (2026-11-17), `93fe5de`; 383 pruebas; sin MariaDB real.
- CATMAST-001: VERIFIED técnico (2026-11-17), `9279d57`; 414/3423; UI real y MariaDB pendientes.
- INVCORE-001: VERIFIED técnico (2026-11-18), `966b685`; 17/17 gates, QA APROBADO, CI 2/2 SUCCESS. Kardex físico y consulta de existencias por sucursal; MariaDB/browser pendientes.
- INVWRITE-001: VERIFIED técnico (2026-11-19), `816fdc6`; 18/18 gates, QA APROBADO, 710 PHP tests / 6502 assertions, CI 2/2 SUCCESS. Carga inicial y ajustes manuales con PIN según umbral; CSV y MariaDB/browser pendientes.
- INVKARDEX-001: VERIFIED técnico (2026-11-19), `317a61d`; 15/15 gates, QA APROBADO (K-01 corregido), 722 PHP tests / 6887 assertions, CI 2/2 SUCCESS. Informe Kardex por sucursal/variante/período; MariaDB/browser pendientes.
- INVREORDER-001: VERIFIED técnico (2026-11-19), `679e858`; 16/16 gates, QA independiente APROBADO, 752 PHP tests / 7415 assertions, CI 2/2 SUCCESS. Umbrales y panel de stock crítico; MariaDB/browser pendientes.
- INVCSV-001: VERIFIED técnico (2026-11-20), `f856592`; 17/17 gates HQAEFF, QA independiente APROBADO, 779 PHP tests / 7805 assertions, CI 2/2 SUCCESS. Importación inicial CSV all-or-nothing; parser F1/F2 e Inertia preview F6 backlog pre-release, MariaDB/browser diferidos.
- INVTRANSFER-001: VERIFIED técnico (2026-11-20), corregido `3072f54`; 18/18 gates, QA independiente APROBADO, 819 PHP tests / 8610 assertions, CI 2/2 SUCCESS. Solicitud, envío, recepción completa/parcial y diferencias pendientes; MariaDB/browser y valorización diferidos.
- CUSCORE-001: VERIFIED técnico (2026-11-23), `b89ed47` (feature `fee3be1`), 15/15 gates, QA independiente APROBADO (P1/P3 cerrados), 883 PHP tests / 9694 assertions; CI exact-SHA 2/2 SUCCESS (45241808376 + 45241808659). Clientes persona/empresa, RUT HMAC y cifrado, grupos y descuentos, revelado auditado. MariaDB/browser diferidos; guía/roadmap pendientes de integración editorial.
- SELLERS-001: VERIFIED_TECH (2026-11-23), código `e46f25f`; 15/15 gates GATES_PASS, QA independiente APROBADO (`SELLERS-001-REVIEW-20261123-1630.md`; 57/636 independientes), PHP 941 tests / 10347 assertions; CI exact-SHA 2/2 SUCCESS (45249487071 / 45249487082). SEL-01 registro de vendedores, sucursales, código inmutable y seeder NONE; SEL-02/03/04/05 y MariaDB/browser/deploy pendientes. Guía/roadmap pendientes editoriales.
- POSSTATION-001: IMPLEMENTADO_TECH (2026-11-24), pendiente gates/QA/CI; `pos_stations` existente reutilizada sin migraciones, `settings.manage` global/por sucursal, módulo `pos` fail-closed, licencia solo lectura y token de dispositivo SHA-256 en cookie HttpOnly/SameSite=Strict. Pruebas dirigidas 46/565 (PosStations) y 382/5234 (PosStations+Sellers+Customers+Inventory); typecheck PASS; `maria=NOT_RUN`, `remote_db=NOT_TOUCHED`, sin navegador real. No VERIFIED.
- CATPROD-001: VERIFIED técnico (2026-11-17), `c8e1338`; 476/4192; QA R2 N-01 pendiente antes de POS/Fiscal.
- **SETUP-001: VERIFIED técnico** (2026-11-17), código `13cdd6a`, gates 19/19, QA independiente APROBADO, 529 pruebas / 4665 aserciones y CI 2/2 SUCCESS. Guía 26 temas y roadmap actualizados por ChatGPT; cierre `docs/work/SETUP-001-CLOSURE-20261117.md`. Producción no demo bloqueada por BKP-08 P1★; instalador CORE-17 implementado/verificado solo en código; ejecución real, MariaDB/browser y BKP-08 pendientes.

## Trabajo documental del harness
- Auditoría estática y refactor de todo el harness integrado en `main` por [PR #28](https://github.com/marioromero/StockFlash/pull/28), squash SHA `3fecc0a303a939c87814664c22ac4a06fb2189db`; CI en ese SHA **2/2 SUCCESS** (PHP/Node/smoke `44216504674`, contratos `44216504605`). Instrucciones internas en inglés, interfaz y documentos de usuario en español. Ver `docs/harness/HARNESS-AUDIT-20261117.md`, `WORKFLOW-EFFICIENCY.md`, `AUDITORIA-TOKENS-IA.md`, `CHATGPT-ONLY-INSTRUCTIONS.md`. HOBS-001 fase 0 solo recopila métricas históricas nativas de lectura; la instrumentación de eventos futura requiere fase 1/2 aprobadas. Los tokens no se infieren.

## Pendientes y bloqueos reales
- La Fase 0 de HOBS-001 no exige CI del SHA base ni sincronización Git: no modifica código. Las comprobaciones de CI 2/2 SUCCESS siguen obligatorias en los cambios documentales del harness y cualquier nueva feature con código.
- STAGING-001: APPROVED, sin deploy demostrado ni VERIFIED; FTPS separado solo hacia `stockflash-dev-app`. No habilita despliegue por sí sola.
- Restricción SSH vigente: no usar la credencial `grupoint`; no publicar secretos.
- **INSTALL-001: VERIFIED técnico**, cierre `docs/work/INSTALL-001-CLOSURE-20261117.md`, código `cbdfacab`, 32/32 gates, QA R3 APROBADO y CI 2/2 SUCCESS. Manual `/docs/` y roadmap editorial actualizados por ChatGPT; metadatos técnicos de guía/roadmap deben ser reconciliados por OpenCode. Instalador deshabilitado por defecto; **NO se ejecutó en entorno real** ni tocó .env/BD remota/cron/deploy. BKP-08 P1★ aún bloquea producción no demo.
- MariaDB real/índices, concurrencia y UX visual no probados; no migrar BD remota ni desplegar sin autorización.

## Reglas operativas vigentes
- Sincronización `main`/`origin/main` solo con `scripts/safe_git_pull.py`; ante `SYNC_BLOCKED` no descartar trabajo local.
- Packet `APPROVED` activo autoriza implementación → gates → QA → publicación (`AUTO_GIT`; `OWNER_GATE` solo excepción). Sin force/reset/clean.
- `VERIFIED` lo decide ChatGPT con gates + QA independiente + CI; deploy y BD remota requieren autorización expresa aparte.
- Prohibido leer secretos o tocar contratos protegidos. Gates: `scripts/sf_verify.py <PACKET>`.
