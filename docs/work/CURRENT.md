# Estado operativo — StockFlash

## Feature activa
- **INSTALL-001 — Instalador web CORE-17 P0: APPROVED / ACTIVO**, packet `docs/work/features/INSTALL-001.md`, derivado de contrato v2.2 existente sin decisión comercial nueva. Código/pruebas solamente; NUNCA ejecutar instalador real ni tocar `.env`/BD remota/cron/deploy. Antes de empezar se debe confirmar CI GitHub 2/2 SUCCESS en HEAD exacto después de este packet y de la documentación. CORE-02 SETUP-001 VERIFIED; BKP-08 sigue bloqueando producción no demo.

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
- CATPROD-001: VERIFIED técnico (2026-11-17), `c8e1338`; 476/4192; QA R2 N-01 pendiente antes de POS/Fiscal.
- **SETUP-001: VERIFIED técnico** (2026-11-17), código `13cdd6a`, gates 19/19, QA independiente APROBADO, 529 pruebas / 4665 aserciones y CI 2/2 SUCCESS. Guía 26 temas y roadmap actualizados por ChatGPT; cierre `docs/work/SETUP-001-CLOSURE-20261117.md`. Producción no demo bloqueada por BKP-08 P1★; instalador CORE-17 y MariaDB/browser real pendientes.

## Pendientes y bloqueos reales
- STAGING-001: APPROVED, sin deploy demostrado ni VERIFIED; FTPS separado solo hacia `stockflash-dev-app`. No habilita despliegue por sí sola.
- Restricción SSH vigente: no usar la credencial `grupoint`; no publicar secretos.
- INSTALL-001: packet APPROVED pero **implementación prohibida hasta verificar CI base 2/2 SUCCESS**; scope local de código/test sin ejecución real de instalación, secreto bootstrap obligatorio, protección contra reinstalación, integración con /setup. BKP-08 P1★ sigue pendiente; no habilitar producción no demo.
- MariaDB real/índices, concurrencia y UX visual no probados; no migrar BD remota ni desplegar sin autorización.

## Reglas operativas vigentes
- Sincronización `main`/`origin/main` solo con `scripts/safe_git_pull.py`; ante `SYNC_BLOCKED` no descartar trabajo local.
- Packet `APPROVED` activo autoriza implementación → gates → QA → publicación (`AUTO_GIT`; `OWNER_GATE` solo excepción). Sin force/reset/clean.
- `VERIFIED` lo decide ChatGPT con gates + QA independiente + CI; deploy y BD remota requieren autorización expresa aparte.
- Prohibido leer secretos o tocar contratos protegidos. Gates: `scripts/sf_verify.py <PACKET>`.
