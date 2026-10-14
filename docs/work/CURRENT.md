# Estado operativo — StockFlash (2026-10-13)

## Hito activo
- **BOOT-001: APPROVED; publicado en GitHub (`bcbf16f`, 2026-10-13, 69 archivos), QA independiente APROBADO (estático, 0 bloqueantes, 2026-10-14). NO VERIFIED y NO release.** Ver commit: `https://github.com/marioromero/StockFlash/commit/bcbf16fa32ba56e21e4696b8950b60ae6cc067c2`.
- OpenCode reportó scaffold Laravel 13.0.0, framework `laravel/framework 13.35.0`, Vue 3, Inertia 3.5.1, TypeScript/Vite, `composer.lock` y `package-lock.json`. `php artisan test` = 2 passed/4 assertions; `npm run build` OK; ruta `/` HTTP 200 con Home/StockFlash. **Son resultados reportados por OpenCode; el reviewer debe comprobarlos, no darlos por verificados.**
- El scaffold, código y `docs/work/BOOT-001-EVIDENCE.md` están actualmente **solo en el equipo local, mayoritariamente archivos Git untracked**; no están en GitHub. `.gitignore` tiene cambios locales. Al revisar, consultar `git ls-files --others --exclude-standard` y leer archivos nuevos directamente; `git diff HEAD` no basta.
- Riesgos/controles BOOT: scripts de Composer con `migrate` desactivados según OpenCode, ausencia de `database/migrations/` y `database.sqlite` reportada, `.env` local nunca debe leerse ni incorporarse. No ejecutar migraciones, ni desplegar.
- **Siguiente acción del owner:** re-ejecutar gates en limpio (`composer validate`, `php artisan test`, `npm run build`, `serve + GET /`), revisar evidencias y declarar VERIFIED fuera de OpenCode si procede. `tmp-boot-001/` se conserva ignorado hasta consentimiento destructivo. No implementar otra feature antes de este gate.

## Flujo Git/OpenCode
- `/sf-feature` y `/sf-review` incorporan un preflight Git automático una sola vez al inicio: `py -3 scripts/safe_git_pull.py` (permiso específico `allow`). Solo rama `main`, `git pull --ff-only origin main`; ante `SYNC_BLOCKED` detener sin reset, stash ni clean. No sincronizar en medio de la tarea. Es necesario **un último git pull manual** para instalar esta versión del propio mecanismo.
- OpenCode mantiene `edit` en `ask`, permisos restringidos, y prohíbe `git commit/push`, despliegue, DB destructiva y operaciones remotas de staging. El owner conserva aprobaciones de cambios de alcance y release.

## Contratos y decisiones
- DOC-001: control documental local validado por el propietario. Ver `docs/adr/ADR-DOC-001.md`, `docs/governance/AUDITORIA-Y-TRAZABILIDAD-DOC-001.md`; el workflow de contratos existe, no asumir verificación de su corrida remota.
- Fuente normativa: las tres especificaciones `docs/product/especificacion-v2.2-*CONSOLIDADO-EN-REVISION.md`, Parte 1 > Parte 2 > Anexo; v2.1 es histórico. Para StockFlash Control aplicar `StockFlash-Control-SPEC-v0.1.md` en su alcance.
- ADR-01..06 permanecen abiertos; no decidir comercial, fiscal, permisos ni schema fuera del contrato. No bloquean BOOT-001.

## Infraestructura y próximos hitos
- Stack local reportado: PHP 8.3.4, Composer 2.8.9, Node 24.8.0, npm 11.6.0; cPanel PHP web/CLI 8.3/8.3.35, Composer 2.8.10, Node 20.19.3. Staging HTTPS y DB DEV existen, **no usados en BOOT**. Versión MariaDB exacta pendiente de verificar antes de schema.
- GitHub Actions: `staging-ssh-check.yml` manual de diagnóstico y `contract-check.yml`. No existen aún CI de Laravel/Vue ni pipeline de deploy.
- **Bloqueo de deploy:** SSH de Actions como usuario general de cPanel `grupoint`. No usar para subir/ejecutar código hasta credenciales de privilegio mínimo, rotación y hardening; también rotar otras credenciales compartidas antes de explotar entornos reales.
- Roadmap vigente: `docs/harness/ROADMAP-STOCKFLASH.md`. No inventar una fase nueva sin decisión ni prometer despliegue antes de gates.
