# Estado operativo — StockFlash

## Feature activa
- **HCTX-001 — contexto selectivo sin sobreingeniería** (`docs/work/features/HCTX-001.md`): `status: APPROVED`, `publication_mode: AUTO_GIT`, `execution_mode: ONE_SMALL_WAVE`. Mantenimiento acotado del harness: mapa de lectura selectivo, CURRENT compacto, correcciones puntuales y una entrada de gates en `scripts/sf_verify.py`. Sin producto, esquema, contratos ni infraestructura nueva.

## Estados por feature (una línea por hito)
- **BOOT-001: VERIFIED** (2026-10-14). Laravel 13.35.0 + Vue 3 + Inertia 3.5.1 + TypeScript/Vite; scaffold publicado en `bcbf16f`; gates PASS y QA APROBADO. Sin deploy ni migraciones.
- HARNESS-001: VERIFIED del entregable (2026-10-14); comandos `/sf-*` y segregación implementer/reviewer/publisher.
- BRAND-001: VERIFIED del prototipo SVG provisional (2026-10-14); PNG oficiales prevalecen y el fallback textual se mantiene.
- CI-001: VERIFIED (2026-10-14); GitHub Actions PHP/Node/smoke y contratos SUCCESS sobre `218de3a`.
- AUTH-001: VERIFIED (2026-11-09), publicado en `55b7f16`; cierre `docs/work/AUTH-001-CLOSURE-20261109.md`.
- BRANCH-001: VERIFIED (2026-11-10), publicado en `4f81457`; cierre `docs/work/BRANCH-001-CLOSURE-20261110.md`.
- DOCS-001: VERIFIED (2026-11-10), publicado en `90254e5`; cierre `docs/work/DOCS-001-CLOSURE-20261110.md`.
- ROADMAP-001: VERIFIED (2026-11-10), publicado en `a9c19d4`; cierre `docs/work/ROADMAP-001-CLOSURE-20261110.md`.
- **USERS-001: APPROVED**, publicada, pendiente decisión externa **VERIFIED**. SHA `2aec03893c78bc7180544930e0b6f741599df9ed`; gates 11/11 PASS, QA APROBADO y CI del SHA SUCCESS. Evidencia: `docs/work/USERS-001-REVIEW-20261113-173533.md`, `docs/work/USERS-001-VERIFY-20261113-175931.md` y `docs/work/USERS-001-VERIFY-20261113-180331.md`. Sin nuevas modificaciones funcionales autorizadas.

## Pendientes y bloqueos reales
- **STAGING-001: APPROVED**, sin deploy demostrado ni VERIFIED. FTPS solo hacia `/home/grupoint/stockflash-dev-app`; raíz pública y smoke pendientes de comprobar. No habilita despliegue por sí sola.
- Restricción SSH vigente: no usar la credencial `grupoint`; vía FTPS separada sin publicar secretos.
- R2 requiere staging seguro; R3 (usuarios/RBAC, sucursales, licencias técnicas, catálogo y BD) no inicia sin solicitud del propietario. ADR de dominio abiertos.
- No hay VERIFIED nuevo sin decisión externa documentada. Concurrencia MariaDB real y UX visual de USERS-001 no probadas.

## Reglas operativas vigentes
- Sincronización `main`/`origin/main` solo con `scripts/safe_git_pull.py`: fetch + comparar SHA; `pull --ff-only` únicamente si el remoto está adelantado y el árbol local está limpio; ante `SYNC_BLOCKED` no descartar trabajo local.
- Política autónoma: Feature Packet `APPROVED`+activa habilita implementación → gates PASS → QA independiente APROBADO → publicación Git por publisher sin nueva autorización (`AUTO_GIT` o campo ausente; `OWNER_GATE` solo como excepción expresa). GitHub Actions por SHA exacto.
- Permisos `allow`/`deny` sin prompts interactivos. No force/reset/clean ni sobrescribir código ajeno.
- `VERIFIED` requiere decisión externa; staging, producción y migraciones remotas requieren autorización separada.
- Prohibido leer/publicar secretos, tocar contratos protegidos o desplegar sin autorización. Desarrollo local-first con BD DEV remota; migraciones remotas solo con instrucción expresa.
- Harness: `AGENTS.md`, `docs/harness/WORKFLOW-SDD-HIBRIDO.md`, `docs/harness/MAPA-DE-LECTURA.md`, `docs/harness/GITHUB-HANDOFF-COMPACT.md`; gates con `scripts/sf_verify.py <PACKET>`.
