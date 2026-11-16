# Estado operativo — StockFlash

## Feature activa
- **CFG-001 — configuración de comprobante interno (CORE-09, alcance acotado): APPROVED y ACTIVA**, `docs/work/features/CFG-001.md`. ChatGPT la priorizó a partir de contratos v2.2 ya aprobados, sin decisión comercial nueva; `AUTO_GIT`. Desarrollo local, sin instalador, migraciones ni despliegue.

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

## Pendientes y bloqueos reales
- **STAGING-001: APPROVED**, sin deploy demostrado ni VERIFIED. FTPS solo hacia `/home/grupoint/stockflash-dev-app`; raíz pública y smoke pendientes de comprobar. No habilita despliegue por sí sola.
- Restricción SSH vigente: no usar la credencial `grupoint`; vía FTPS separada sin publicar secretos.
- R2 staging seguro sigue pendiente. R3 CFG-001 activo; instalador CORE-17/onboarding CORE-02 y R4 Bodega seguirán por dependencias técnicas. ADR de negocio abiertos no se implementan por intuición.
- VERIFIED exige dictamen técnico externo registrado; concurrencia MariaDB real y UX visual de USERS-001 NO ejecutadas (pendientes antes de despliegue).

## Reglas operativas vigentes
- Sincronización `main`/`origin/main` solo con `scripts/safe_git_pull.py`: fetch + comparar SHA; `pull --ff-only` únicamente si el remoto está adelantado y el árbol local está limpio; ante `SYNC_BLOCKED` no descartar trabajo local.
- Política autónoma: Feature Packet `APPROVED`+activa (aprobación propietario o derivación ChatGPT de contrato ya aprobado, sin nuevas decisiones) habilita implementación → gates PASS → QA independiente APROBADO → publicación Git por publisher sin nueva autorización (`AUTO_GIT` o campo ausente; `OWNER_GATE` solo como excepción expresa). GitHub Actions por SHA exacto.
- Permisos `allow`/`deny` sin prompts interactivos. No force/reset/clean ni sobrescribir código ajeno.
- `VERIFIED` lo determina ChatGPT tras aceptación+gates+QA+CI, con la aprobación original suficiente; deploy y BD remota requieren autorización expresa aparte.
- Prohibido leer/publicar secretos, tocar contratos protegidos o desplegar sin autorización. Desarrollo local-first con BD DEV remota; migraciones remotas solo con instrucción expresa.
- Harness: `AGENTS.md`, `docs/harness/WORKFLOW-SDD-HIBRIDO.md`, `docs/harness/MAPA-DE-LECTURA.md`, `docs/harness/GITHUB-HANDOFF-COMPACT.md`; gates con `scripts/sf_verify.py <PACKET>`.
