# Estado operativo — StockFlash (2026-10-14)

## Hito activo
- **BOOT-001: VERIFIED (2026-10-14, decisión arquitecto ChatGPT + propietario, fuera de OpenCode). R1 del roadmap completada. Publicado en GitHub (`bcbf16f` implementación + `14ef7a4` registro, 69 archivos), QA independiente APROBADO (0 bloqueantes). NO release.** Ver: `https://github.com/marioromero/StockFlash/commit/bcbf16fa32ba56e21e4696b8950b60ae6cc067c2`.
- Scaffold verificado en GitHub: Laravel 13.0.0, framework `laravel/framework 13.35.0`, Vue 3, Inertia 3.5.1, TypeScript/Vite, `composer.lock` y `package-lock.json`. `php artisan test` = 2 passed/4 assertions; `npm run build` OK; ruta `/` HTTP 200 con Home/StockFlash, HTML `es-CL`.
- Riesgos/controles BOOT: scripts de Composer con `migrate` desactivados, ausencia de `database/migrations/` y `database.sqlite`, `.env` local nunca debe leerse ni incorporarse. No ejecutar migraciones, ni desplegar.
- **Siguiente hito formal:** R2 (CI / staging seguro) según `docs/harness/ROADMAP-STOCKFLASH.md`. BRAND-001 recibe aprobación externa de alcance (2026-10-14) para ocho SVG **provisionales, únicamente para revisión visual**, sin distribuirlos como logos oficiales ni activarlos en la aplicación. La copia local de `docs/work/features/BRAND-001.md` debe pasar de DRAFT a APPROVED después de contrastar su contenido con el alcance autorizado. `tmp-boot-001/` se conserva ignorado hasta consentimiento destructivo.
- **Feature operativa activa: BRAND-001 — alcance aprobado para propuestas SVG, pendiente de reflejar `status: APPROVED` en su packet local.** Su implementación comienza solo después de registrar esa aprobación en `docs/work/features/BRAND-001.md`. HARNESS-001 se cierra como VERIFIED de entregable publicado, con prueba E2E completa aún no ejecutada; se registrará su resultado durante BRAND-001. R2 continúa como siguiente hito formal, sin nueva fase de roadmap.

## Flujo Git/OpenCode
- `/sf-feature` y `/sf-review` incorporan un preflight Git automático una sola vez al inicio: `py -3 scripts/safe_git_pull.py` (permiso específico `allow`). Solo rama `main`, `git pull --ff-only origin main`; ante `SYNC_BLOCKED` detener sin reset, stash ni clean. No sincronizar en medio de la tarea. Es necesario **un último git pull manual** para instalar esta versión del propio mecanismo.
- OpenCode mantiene `edit` en `ask` y permisos restringidos. Publicación Git solo vía `stockflash-publisher` (`/sf-publish`) tras `APROBADO` + `GATES_PASS` vigente, sin force; prohibidos despliegue, DB destructiva y operaciones remotas de staging. El owner conserva aprobaciones de cambios de alcance, VERIFIED y release.

## Contratos y decisiones
- DOC-001: control documental local validado por el propietario. Ver `docs/adr/ADR-DOC-001.md`, `docs/governance/AUDITORIA-Y-TRAZABILIDAD-DOC-001.md`; el workflow de contratos existe, no asumir verificación de su corrida remota.
- Fuente normativa: las tres especificaciones `docs/product/especificacion-v2.2-*CONSOLIDADO-EN-REVISION.md`, Parte 1 > Parte 2 > Anexo; v2.1 es histórico. Para StockFlash Control aplicar `StockFlash-Control-SPEC-v0.1.md` en su alcance.
- ADR-01..06 permanecen abiertos; no decidir comercial, fiscal, permisos ni schema fuera del contrato. No bloquean BOOT-001.

## Infraestructura y próximos hitos
- Stack local reportado: PHP 8.3.4, Composer 2.8.9, Node 24.8.0, npm 11.6.0; cPanel PHP web/CLI 8.3/8.3.35, Composer 2.8.10, Node 20.19.3. Staging HTTPS y DB DEV existen, **no usados en BOOT**. Versión MariaDB exacta pendiente de verificar antes de schema.
- GitHub Actions: `staging-ssh-check.yml` manual de diagnóstico y `contract-check.yml`. No existen aún CI de Laravel/Vue ni pipeline de deploy.
- **Bloqueo de deploy:** SSH de Actions como usuario general de cPanel `grupoint`. No usar para subir/ejecutar código hasta credenciales de privilegio mínimo, rotación y hardening; también rotar otras credenciales compartidas antes de explotar entornos reales.
- Roadmap vigente: `docs/harness/ROADMAP-STOCKFLASH.md`. No inventar una fase nueva sin decisión ni prometer despliegue antes de gates.
