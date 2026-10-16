---
description: Despliegue automatizado a staging via cPanel API con un solo ZIP (sin operaciones manuales)
agent: build
---

Ejecutar el pipeline de despliegue a staging de punta a punta con `scripts/sf_deploy_staging.py`. Este comando NO implementa features, NO revisa como QA y NO publica en Git: solo despliega la versión ya aprobada y registra evidencia. No declarar VERIFIED jamás (owner+ChatGPT fuera de OpenCode).

**Precondiciones (una sola vez, NO repetir si ya están):**

- Credenciales configuradas fuera de Git: `py -3 scripts/sf_credstore.py list` debe mostrar `cpanel_user` y `cpanel_token` (si faltan, entregar al propietario la instrucción única de `docs/work/STAGING-001-DEPLOY-AUTO.md` y detenerse).
- `STAGING-001: APPROVED` y activa en `docs/work/CURRENT.md`.

**Secuencia automática (detenerse ante el primer BLOCKED, sin eludir):**

1. `py -3 scripts/sf_deploy_staging.py --check` — versión del repo (HEAD debe igualar `origin/main`), pre-gates S1/S2/S4/S5, manifest 8911 entradas, artefacto Laravel/Vue (`composer validate`, `vendor/`, `public/build/`), autotests de validadores.
2. `py -3 scripts/sf_deploy_staging.py --probe` — valida el mecanismo real con archivos efímeros (auth, mkdir, upload, extract, list, unlink). Obligatorio una vez por versión antes del primer `--deploy`; si falla, no se toca la aplicación.
3. `py -3 scripts/sf_deploy_staging.py --deploy` — backup remoto, subida del ZIP único, extracción en `/home/grupoint/stockflash-dev-app`, reconciliación a cuarentena (sin borrar), requisitos de arranque Laravel (`.env`/`storage/` preservados, se crean solo si ausentes), probe PHP, smoke HTTPS y reporte `docs/work/STAGING-001-DEPLOY-<ts>.md`. Ante fallo con backup recuperable, rollback automático; sin backup, se deja lo nuevo y se reporta.
4. `py -3 scripts/sf_verify.py STAGING-001` — gates del harness vigente (S6 exige `STAGING-001-SMOKE-*.md` generado por el smoke).
5. Cierre: `/sf-review STAGING-001` (QA independiente) y publicación Git ordinaria via `/sf-publish` (solo código y reportes; jamás credenciales ni el ZIP).

**Prohibiciones:** sin FTPS por archivo, sin SFTP, sin cuentas nuevas, sin cambios a cPanel global, sin migraciones/seeders, sin leer ni registrar `.env`/secretos, sin deploy si HEAD != `origin/main`, sin modificar `opencode.json` ni permisos para hacer pasar el despliegue. Ante `deny` administrativo, bloqueo + parche, nunca elusión.
