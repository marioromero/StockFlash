# STAGING-001 — Paquete determinista (G2)

SHA codigo esperado: `54073d0447e60ad876ff7134d73ad28acd21e50b` (HEAD sincronizado, sin pull/reset).
Locks: `composer.lock` (311245 B) + `package-lock.json` (84467 B), SHA12 conjunto `8b1c700f0dad`.
Dependencias prod: `vendor/autoload.php` presente (Composer produccion desde lockfiles, PHP 8.3).
Frontend: `public/build/manifest.json` con entradas `resources/js/app.ts` y `resources/css/app.css`
(`assets/app-BVfdHwKA.js` 255332 B, `assets/app-BZvL8UBI.css` 1092 B), compilado desde lockfiles.
Exclusiones verificadas: sin secretos ni contenido de usuario en el paquete; `.env` real,
claves, `storage/` con datos y credenciales jamas van al paquete FTPS ni al repositorio.
Artefactos compilados (`public/build/`) viajan solo en el paquete FTPS, no al repo fuente
(`.gitignore` los excluye). Sin migraciones/seeds/cron/workers/correos/DTE.
Destino unico: `/home/grupoint/stockflash-dev-app` (raiz virtual `/` si asi la presenta FTP;
no concatenar). Carpeta antigua `stockflash-dev.tusitioexpress.cl` solo antecedente.
Revalidacion ciclo 2 (20261016, sin sesion): HEAD `54073d0447e60ad876ff7134d73ad28acd21e50b`,
locks 311245/84467 B, `vendor/autoload.php` + `public/build/manifest.json` (app.ts/app.css) OK,
sin secretos; preflight TLS `234 AUTH TLS OK` sin auth; smoke HTTPS 200 autoindex sin StockFlash.
