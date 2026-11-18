# Estado operativo — StockFlash

## Feature activa
- **HOBS-001 — Auditoría de tokens, FASE 0 (APPROVED / ACTIVA)**. Packet `docs/work/features/HOBS-001.md`; `audit=ON;phase=0`. Únicamente diagnóstico local **de lectura**: estadísticas nativas OpenCode y hasta 2–3 sesiones anteriores comparables. Resultado: comentario SFH1 en issue #29. **Sin parser nuevo, JSONL, implementación, QA de producto, publisher, commits ni despliegue.** Las fases 1 y 2 requieren un nuevo packet técnico aprobado después de revisar los resultados. Comprobar primero CI 2/2 SUCCESS del HEAD base exacto; no acceder a secretos ni BD remota.

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
- **SETUP-001: VERIFIED técnico** (2026-11-17), código `13cdd6a`, gates 19/19, QA independiente APROBADO, 529 pruebas / 4665 aserciones y CI 2/2 SUCCESS. Guía 26 temas y roadmap actualizados por ChatGPT; cierre `docs/work/SETUP-001-CLOSURE-20261117.md`. Producción no demo bloqueada por BKP-08 P1★; instalador CORE-17 implementado/verificado solo en código; ejecución real, MariaDB/browser y BKP-08 pendientes.

## Trabajo documental del harness
- Auditoría estática y refactor de todo el harness integrado en `main` por [PR #28](https://github.com/marioromero/StockFlash/pull/28), squash SHA `3fecc0a303a939c87814664c22ac4a06fb2189db`; CI en ese SHA **2/2 SUCCESS** (PHP/Node/smoke `44216504674`, contratos `44216504605`). Instrucciones internas en inglés, interfaz y documentos de usuario en español. Ver `docs/harness/HARNESS-AUDIT-20261117.md`, `WORKFLOW-EFFICIENCY.md`, `AUDITORIA-TOKENS-IA.md`, `CHATGPT-ONLY-INSTRUCTIONS.md`. Auditoría real de eventos pendiente para HOBS-001: los tokens no se infieren.

## Pendientes y bloqueos reales
- HOBS-001 solo puede arrancar si **la CI PHP/Node/smoke + contratos del HEAD final posterior a estos cambios** está 2/2 SUCCESS; de lo contrario STOP y diagnóstico. Captura de uso real de tokens solo cuando OpenCode/proveedor la ofrece.
- STAGING-001: APPROVED, sin deploy demostrado ni VERIFIED; FTPS separado solo hacia `stockflash-dev-app`. No habilita despliegue por sí sola.
- Restricción SSH vigente: no usar la credencial `grupoint`; no publicar secretos.
- **INSTALL-001: VERIFIED técnico**, cierre `docs/work/INSTALL-001-CLOSURE-20261117.md`, código `cbdfacab`, 32/32 gates, QA R3 APROBADO y CI 2/2 SUCCESS. Manual `/docs/` y roadmap editorial actualizados por ChatGPT; metadatos técnicos de guía/roadmap deben ser reconciliados por OpenCode. Instalador deshabilitado por defecto; **NO se ejecutó en entorno real** ni tocó .env/BD remota/cron/deploy. BKP-08 P1★ aún bloquea producción no demo.
- MariaDB real/índices, concurrencia y UX visual no probados; no migrar BD remota ni desplegar sin autorización.

## Reglas operativas vigentes
- Sincronización `main`/`origin/main` solo con `scripts/safe_git_pull.py`; ante `SYNC_BLOCKED` no descartar trabajo local.
- Packet `APPROVED` activo autoriza implementación → gates → QA → publicación (`AUTO_GIT`; `OWNER_GATE` solo excepción). Sin force/reset/clean.
- `VERIFIED` lo decide ChatGPT con gates + QA independiente + CI; deploy y BD remota requieren autorización expresa aparte.
- Prohibido leer secretos o tocar contratos protegidos. Gates: `scripts/sf_verify.py <PACKET>`.
