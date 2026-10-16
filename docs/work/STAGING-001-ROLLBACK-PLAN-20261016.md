# STAGING-001 — Plan de inventario, manifest y rollback (ciclo 2, PLAN, no ejecutado)

Estado: PLAN listo, ejecucion PENDIENTE de sesion FTPS autenticada. No es evidencia de servidor.
Destino unico: `/home/grupoint/stockflash-dev-app` (raiz virtual `/` si asi la presenta FTP; no concatenar).
Estado ROLLBACK_READY del procedimiento: plan redactado y reproducible; el punto de restauracion real
solo existira tras inventario remoto previo. Sin ese paso, el despliegue queda BLOQUEADO.

## 1. Inventario previo (operador, con sesion)
1. `py -3 scripts/staging_ftps_one_shot.py --list` — registra LIST bajo raiz autorizada (truncado en pantalla).
2. Guardar salida completa sin secretos en `docs/work/STAGING-001-MANIFEST-<fecha>.md` como `inventario` fechado.
3. Confirmar presencia/ausencia de `public/`, `app/`, `artisan`, `.env` remoto y `storage/` remoto.

## 2. Manifest explicito (antes de sobrescribir)
- Lista cerrada de rutas locales a subir (paquete SHA `54073d0447e60ad876ff7134d73ad28acd21e50b`):
  `artisan`, `composer.json`, `composer.lock`, `app/`, `bootstrap/`, `config/`, `routes/`,
  `resources/`, `public/` (incluye `public/build/` compilado), `vendor/` (prod desde lockfiles).
- Exclusiones duras: `.env` de cualquier origen, claves, `storage/` con datos, `node_modules/`,
  `.git/`, `tmp-boot-001/`, dumps, secretos. El `.env` remoto y `storage/` remoto JAMAS se sobrescriben
  ni se descargan a Git/evidencias.
- Cada entrada del manifest lleva SHA12 local; el despliegue es unico y reversible por lista.

## 3. Rollback reproducible
1. Antes de subir, respaldar bajo la misma raiz autorizada: `backup-<fecha>/` con los archivos que
   seran sobrescritos (copia FTP GET, sin `.env` ni `storage/` con datos fuera del servidor).
2. Despliegue por lista manifest en orden: primero no-`public/`, luego `public/` al final.
3. Si el smoke falla: re-subir `backup-<fecha>/` sobre las mismas rutas, re-smoke, y reportar.
4. Solo con inventario + manifest + backup verificados se declara ROLLBACK_READY ejecutado
   (documentado entonces en `STAGING-001-MANIFEST-<fecha>.md` con token de gate + inventario + `.env` preservado).

## 4. Prohibiciones
- Sin `rm` recursivo, sin sync con borrado, sin explorar fuera de la raiz asignada.
- Sin migraciones/seeds/cron/DTE, sin SSH `grupoint`, sin deploy automatico.
- Secreto solo via `STAGING_FTPS_PASSWORD` en tiempo de ejecucion o prompt getpass; nunca en archivos/logs.
