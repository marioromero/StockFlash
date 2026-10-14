# CI-001 — Operación de la CI PHP/Node (R2, sin deploy)

## Disparadores
- `push` a `main`, `pull_request` y `workflow_dispatch` (manual).
- Archivo: `.github/workflows/ci.yml`. No tocar `staging-ssh-check.yml` (diagnóstico manual, no pipeline).

## Permisos y concurrencia
- `permissions: contents: read` a nivel de workflow. Sin `write`, sin claves SSH, sin secretos.
- Concurrencia por workflow+ref con `cancel-in-progress: false`: agrupa ejecuciones redundantes sin cancelar publicaciones en curso.

## Señales
- Verde: jobs `php` (composer validate + install + `php artisan test`), `node` (`npm ci` + typecheck + build) y `smoke` (GET `/` → 200 + `lang="es-CL"`) en verde.
- Roja: cualquier job en FAIL. El job `smoke` depende de `php` y `node`; si alguno falla, `smoke` queda pendiente y la causa está en el job fallido.

## Logs y artefactos
- Logs en la pestaña Actions de cada corrida (por job y paso). Sin artefactos publicados: el build solo valida, no despliega.
- Verificación local determinista: `py -3 scripts/sf_verify.py CI-001` (gates C1 estructura/política YAML, C2 lockfiles+scripts, C3 config sqlite memory + APP_KEY efímera). No duplica la CI remota.

## Fallas comunes
- `composer validate` FAIL → `composer.json`/`composer.lock` inconsistentes: actualizar lock y reintentar.
- `npm ci` FAIL → `package-lock.json` desincronizado: regenerar lock y reintentar.
- `php artisan test` FAIL → ver test fallido en el log; sin migrate/seed en CI.
- `smoke` FAIL → revisar extracto del servidor en el log del paso; causas típicas: build roto o ruta `/` caída.
- Nunca agregar secretos ni `.env` para "arreglar" la CI: la APP_KEY es efímera de test (variable del job).

## Separación respecto de staging
- Esta CI no despliega, no usa SSH ni MariaDB, no tiene ambiente `deploy`.
- Staging (SSH, menor privilegio, rollback) corresponde a un packet R2 posterior e independiente.
