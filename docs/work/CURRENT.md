# Estado operativo — StockFlash

## Feature activa
- **CATDB-001 — Esquema base de Catálogo P0 (CAT-01–03), APPROVED y ACTIVA**, packet `docs/work/features/CATDB-001.md`. Primera ola R3 Catálogo; solo migraciones locales/tests, sin edición de BD remota ni despliegue.

## Estados por feature (una línea por hito)
- **BOOT-001: VERIFIED** (2026-10-14). Laravel 13.35.0 + Vue 3 + Inertia 3.5.1 + TypeScript/Vite; scaffold publicado en `bcbf16f`; gates PASS y QA APROBADO. Sin deploy ni migraciones.
- HARNESS-001: VERIFIED del entregable (2026-10-14); comandos `/sf-*`.
- **HCTX-001: VERIFIED** (2026-11-16), SHA `9beb834`, gates 11/11, QA/CI verde, cierre `docs/work/HCTX-001-CLOSURE-20261116.md`; mapa de lectura y contexto compacto.
- BRAND-001: VERIFIED del prototipo SVG provisional (2026-10-14); PNG oficiales prevalecen y el fallback textual se mantiene.
- CI-001: VERIFIED (2026-10-14); GitHub Actions PHP/Node/smoke y contratos SUCCESS sobre `218de3a`.
- AUTH-001: VERIFIED (2026-11-09), publicado en `55b7f16`; cierre `docs/work/AUTH-001-CLOSURE-20261109.md`.
- BRANCH-001: VERIFIED (2026-11-10), publicado en `4f81457`; cierre `docs/work/BRANCH-001-CLOSURE-20261110.md`.
- DOCS-001: VERIFIED (2026-11-10), publicado en `90254e5`; cierre `docs/work/DOCS-001-CLOSURE-20261110.md`.
- ROADMAP-001: VERIFIED (2026-11-10), publicado en `a9c19d4`; cierre `docs/work/ROADMAP-001-CLOSURE-20261110.md`.
- **USERS-001: VERIFIED** (2026-11-16, revisión ChatGPT), SHA `2aec038`, gates 11/11, QA/CI verde, manual/roadmap integrados. Cierre `docs/work/USERS-001-CLOSURE-20261116.md`; pruebas MariaDB concurrente y visual real diferidas para predespliegue.

- **CFG-001: VERIFIED** (2026-11-16), SHA `fd12693`, 15 gates PASS, 220 pruebas PHP, QA APROBADO, CI 2/2 SUCCESS; guía de 20 temas. Cierre `docs/work/CFG-001-CLOSURE-20261116.md`.
- **CFG-002: VERIFIED técnico** (2026-11-16), SHA `81ba734`, 16 gates PASS, 239 pruebas PHP, QA APROBADO, CI 2/2 SUCCESS, guía 21 temas. Cierre `docs/work/CFG-002-CLOSURE-20261116.md`; sin navegador real ni despliegue.
- **CFG-003: VERIFIED técnico** (2026-11-16), SHA `045b8c8`, gates 16/16, 260 pruebas PHP, QA/CI verde, guía 22 temas. Cierre `docs/work/CFG-003-CLOSURE-20261116.md`; sin navegador real.
- **CORESEQ-001: VERIFIED técnico** (2026-11-16), SHA `ceb50e0`, 16 gates PASS, 282 pruebas, QA/CI 2/2 verde; concurrencia real MariaDB diferida. Cierre `docs/work/CORESEQ-001-CLOSURE-20261116.md`.
- **CORELIC-001: VERIFIED técnico** (2026-11-16), SHA `a2cc899`, gates 17/17, 308 pruebas, QA/CI 2/2 verde; guía 23 temas, sin conexión central. Cierre `docs/work/CORELIC-001-CLOSURE-20261116.md`.
- **COREMOD-001: VERIFIED técnico** (2026-11-16), SHA `cd6d39d`, 18/18 gates, 328 pruebas PHP, QA/CI 2/2 verde. Servicio interno, sin interfaz; concurrencia MariaDB real pendiente. Cierre `docs/work/COREMOD-001-CLOSURE-20261116.md`.
- **COREPIN-001: VERIFIED técnico** (2026-11-16), SHA `5ea1b3b`, gates 17/17, QA independiente APROBADO y CI 2/2 verde, 359 pruebas PHP. Corrección AUTH_TOCTOU validada con revocaciones durante transacción; MariaDB real diferida. Cierre `docs/work/COREPIN-001-CLOSURE-20261116.md`.

## Pendientes y bloqueos reales
- **STAGING-001: APPROVED**, sin deploy demostrado ni VERIFIED. FTPS solo hacia `/home/grupoint/stockflash-dev-app`; raíz pública y smoke pendientes de comprobar. No habilita despliegue por sí sola.
- Restricción SSH vigente: no usar la credencial `grupoint`; vía FTPS separada sin publicar secretos.
- R2 staging seguro pendiente. CATDB-001 activa: migraciones del esquema P0 de Catálogo y pruebas en SQLite local; NO ejecutar contra BD remota sin nueva autorización. Después, CRUD funcional de Catálogo; CORE-02/CORE-17 instalador pendientes con dependencias de seguridad.
- VERIFIED exige dictamen técnico externo registrado; concurrencia MariaDB real y UX visual de USERS-001 NO ejecutadas (pendientes antes de despliegue).

## Reglas operativas vigentes
- Sincronización `main`/`origin/main` solo con `scripts/safe_git_pull.py`: fetch + comparar SHA; `pull --ff-only` únicamente si el remoto está adelantado y el árbol local está limpio; ante `SYNC_BLOCKED` no descartar trabajo local.
- Política autónoma: Feature Packet `APPROVED`+activa (aprobación propietario o derivación ChatGPT de contrato ya aprobado, sin nuevas decisiones) habilita implementación → gates PASS → QA independiente APROBADO → publicación Git por publisher sin nueva autorización (`AUTO_GIT` o campo ausente; `OWNER_GATE` solo como excepción expresa). GitHub Actions por SHA exacto.
- Permisos `allow`/`deny` sin prompts interactivos. No force/reset/clean ni sobrescribir código ajeno.
- `VERIFIED` lo determina ChatGPT tras aceptación+gates+QA+CI, con la aprobación original suficiente; deploy y BD remota requieren autorización expresa aparte.
- Prohibido leer/publicar secretos, tocar contratos protegidos o desplegar sin autorización. Desarrollo local-first con BD DEV remota; migraciones remotas solo con instrucción expresa.
- Harness: `AGENTS.md`, `docs/harness/WORKFLOW-SDD-HIBRIDO.md`, `docs/harness/MAPA-DE-LECTURA.md`, `docs/harness/GITHUB-HANDOFF-COMPACT.md`; gates con `scripts/sf_verify.py <PACKET>`.
