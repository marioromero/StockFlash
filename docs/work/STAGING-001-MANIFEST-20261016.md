# STAGING-001 — Manifest y rollback primera instalación (G5)

Estado: **ROLLBACK_READY** (procedimiento + manifest listos; punto de restauración remoto lo crea `--deploy` antes de sobrescribir).

SHA código esperado: `54073d0447e60ad876ff7134d73ad28acd21e50b` (HEAD = `origin/main`, sincronizado 2026-10-16).
Manifest subida: `scripts/staging-manifest-20261016.txt` — 8911 entradas, validado con `--manifest-check` (PASS local, sin FTPS).
Destino único: `/home/grupoint/stockflash-dev-app` presentado como raíz virtual `/`; no concatenar ruta física. Carpeta antigua `stockflash-dev.tusitioexpress.cl` solo antecedente, nunca destino.

## 1. inventario inicial (owner-reported 2026-10-16 + baseline ciclo 2)

- Sesión FTPS autenticada OK (`admin@stockflash-dev.tusitioexpress.cl`, FTPS explícito `ftp.tusitioexpress.cl:21`).
- Raíz virtual `/` confirmada como `stockflash-dev-app` (no se concatena `/home/grupoint/stockflash-dev-app` cuando PWD ya está confinado; el script lo detecta en `resolve_root()`).
- `public/` existe bajo la raíz (el marcador se publicó en `public/` y sirvió por HTTPS).
- `.ftpquota` presente (archivo de cuota cPanel): se **conserva**, jamás se sube, sobrescribe, descarga a Git ni se borra. El transportador lo rechaza en `guarded()`.
- Cualquier archivo preexistente fuera del manifest se conserva: `--deploy` nunca hace `rm` recursivo, sync con borrado ni DELE masivo; solo STOR de rutas del manifest y DELE explícito por lista en rollback.
- `.env` remoto (si existe) y `storage/` remoto con datos **jamás** se sobrescriben ni se descargan a Git/evidencias; el manifest los excluye y `guarded()` los bloquea.
- Scaffold Laravel aún no desplegado (baseline ciclo 2: `GET /` 200 autoindex `Index of /`, sin StockFlash): primera instalación = casi todo sin previo remoto; el backup remoto quedará mayormente vacío salvo sobrescrituras puntuales.

## 2. Manifest (lista cerrada, rutas relativas a la raíz virtual)

- Orígenes locales: `artisan`, `composer.json`, `composer.lock`, `app/`, `bootstrap/`, `config/`, `routes/`, `resources/`, `public/` (incluye `public/build/` compilado), `vendor/` (prod desde lockfiles), `database/`.
- Exclusiones duras verificadas (`--manifest-check` PASS, grep sin hits): `.env` de cualquier origen, claves, `storage/` con datos, `node_modules/`, `.git/`, `tmp-boot-001/`, dumps, secretos, `.ftpquota`. Artefactos `public/build/` viajan solo por FTPS, no al repo fuente.
- Cada despliegue es único y reversible por lista; orden: primero no-`public/`, luego `public/` al final (evita ventana con `index.php` parcial).

## 3. Reversión primera instalación (reproducible, dentro de la raíz autorizada)

1. `--deploy` crea `backup-<AAAA-MM-DD-HHMMSS>/` remoto (MKD) y, por cada ruta del manifest con previo remoto, hace `RETR` + `STOR backup-<fecha>/<ruta>` antes de sobrescribir (sin `.env` ni `storage/` con datos fuera del servidor; sin `.ftpquota`).
2. Si el smoke HTTPS falla: re-subir `backup-<fecha>/` sobre las mismas rutas (STOR por lista), re-smoke `GET https://stockflash-dev.tusitioexpress.cl/` -> 200 StockFlash.
3. Archivos nuevos sin previo (mayoría en primera instalación) se retiran por lista explícita con `DELE <ruta>` en orden inverso; nunca `rm -r`, nunca borrar `.ftpquota`, `.env` remoto, `storage/` remoto ni archivos ajenos al manifest.
4. Verificación de reversión: `LIST` bajo raíz autorizada + smoke HTTPS; reportar SHA restaurado y backup utilizado.
5. Solo con inventario + manifest + backup verificados se mantiene ROLLBACK_READY ejecutado (este archivo + log de `--deploy` con `subidos/respaldados/errores/backup=`).

## 4. Prohibiciones

- Sin `rm` recursivo, sin sincronizadores con borrado, sin explorar fuera de la raíz asignada.
- Sin migraciones/seeds/cron/DTE, sin SSH `grupoint`, sin deploy automático.
- Secreto solo en tiempo de ejecución (prompt `getpass`, nunca en archivos/logs/argumentos visibles).
