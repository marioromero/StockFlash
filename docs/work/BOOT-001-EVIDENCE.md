# BOOT-001 — Evidencia de ejecución local (2026-10-12)

Feature: `docs/work/features/BOOT-001.md` (APPROVED). Sin commit/push/deploy por agentes.

## 1. Versión fijada
- Scaffold: `laravel/laravel 13.0.0` vía `composer create-project laravel/laravel:"^13.0" tmp-boot-001 --no-scripts --no-progress --prefer-dist`.
- Framework instalado: `laravel/framework 13.35.0`, `inertiajs/inertia-laravel 3.5.1`.
- Entorno: PHP 8.3.4, Composer 2.8.9, Node 24.8.0, npm 11.6.0. Fijado en `composer.lock` + `package-lock.json`.

## 2. Fusión controlada (colisiones revisadas)
- Copiado a raíz: `app/ bootstrap/ config/ public/ resources/ routes/ storage/ tests/ artisan composer.json/lock phpunit.xml .editorconfig .env.example` + `database/factories database/seeders database/.gitignore` (rutas corregidas, sin aplanar).
- Preservados sin sobrescribir: `.gitattributes` (reglas LF de contratos), `.gitignore` (merge: se agregó `/tmp-boot-001/` y `.phpunit.result.cache`), `AGENTS.md`, `opencode.json`, `docs/`, `.github/`.
- NO copiados: `vendor/`, `package.json` y `vite.config.js` del scaffold (recreados limpios sin Tailwind), `README` genérico, `.env`, `database/migrations/*`.
- `tmp-boot-001/` se conserva ignorado para revisión; eliminar solo con consentimiento destructivo tras `/sf-review`.

## 3. Exclusiones documentadas (exigencia del Packet)
- Migraciones ejemplo `0001_*_create_users/cache/jobs_table.php`: NO copiadas a `database/`; `Test-Path database/migrations` = False; `database/database.sqlite` no existe. No se ejecutó `migrate` (scaffold creado con `--no-scripts`).
- `composer.json`: se neutralizaron scripts con migración automática (`setup` y `post-create-project-cmd` ahora imprimen aviso BOOT-001 en vez de `migrate --force/--graceful`).
- `database/seeders/DatabaseSeeder.php`: cuerpo vaciado con comentario BOOT-001; `User` de ejemplo no es esquema aprobado. `UserFactory.php` permanece como remanente de scaffold sin uso de dominio.
- Tailwind (`@tailwindcss/vite`, `tailwindcss`): excluido por falta de autorización; `resources/css/app.css` usa tokens `--brand-graphite:#303846 --brand-blue:#0856FF` sin UI kits. `vite.config.ts` sin plugin tailwind.
- `.env` local (ignorado): `key:generate` ejecutado; `SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync` para cumplir «sin MariaDB para pantalla inicial».

## 4. Comandos y resultados
- `composer require inertiajs/inertia-laravel --no-scripts`: instala v3.5.1, `composer validate` = válido.
- `npm install`: 144 paquetes. `npm run build` (`vue-tsc --noEmit && vite build`): OK — `public/build/manifest.json` (331 B) + `assets/app-BVfdHwKA.js` (255 kB) + `assets/app-BZvL8UBI.css`.
- `php artisan test`: 2 passed, 4 assertions (Feature assert 200 + `StockFlash` + `"component":"Home"`).
- `php artisan route:list --path=/`: `GET / → routes/web.php:6` (Inertia Home).
- `php artisan serve 127.0.0.1:18733 + GET /`: `200`, 941 B, `<title>StockFlash</title>`, `data-page {"component":"Home","props":{"version":"Laravel 13.35.0"}}`.
- Hallazgo intermedio: 500 por `SESSION_DRIVER=database` del scaffold → corregido a `file` (ver §3). Sin tocar DB.
- `git status --short`: `M .gitignore` + untracked explicables (`app/ artisan bootstrap/ composer.json/lock config/ database/ package.json package-lock.json phpunit.xml public/ resources/ routes/ storage/ tests/ tsconfig.json vite.config.ts .editorconfig .env.example`). Sin `vendor/ node_modules/ .env public/build tmp-boot-001/` (ignorados), sin specs alteradas.

## 5. Bloqueos
Ninguno para BOOT-001. ADR-01..06 siguen abiertos y no se inventaron. Staging intacto, sin deploy.

## 7. Corrección es-CL + revalidación pre-publish (2026-10-13)
- `resources/views/app.blade.php:2`: `<html lang="{{ ...getLocale() }}">` → `<html lang="es-CL">` fijo (contrato es-CL, AGENTS.md regla 1). Sin `.env`, sin dependencias nuevas. `welcome.blade.php` no se toca (fuera de ruta `/`).
- Re-verify `docs/work/BOOT-001-VERIFY-20261013-232459.md` = GATES_PASS (G1 validate 0, G2 test 2/4, G3 build 759 módulos, G4 `GET /` 200 body `lang="es-CL"` + StockFlash). Servidor temporal cerrado.
- Re-review independiente = APROBADO, 0 bloqueantes, estado estático exacto incl. fix.
- Exclusiones publish: `docs/work/features/BRAND-001.md` (DRAFT), `tmp-boot-001/`, secretos, `vendor/`, `node_modules/`, `public/build/`, runtime/logs.

## 6. Correcciones tras QA-2 (2026-10-12)
- `composer.json post-create-project-cmd`: eliminada línea `touch database.sqlite`; solo aviso BOOT-001. `Test-Path database.sqlite` = False tras re-ejecución.
- `composer.json minimum-stability`: `dev` → `stable` (bootstrap exige versión estable); `composer update --lock --no-scripts` + `composer validate` = válido.
- `app/Models/User.php` y `database/factories/UserFactory.php`: marcados con cabecera BOOT-001 como remanentes de scaffold excluidos del dominio (sin tabla aprobada, fuera del hito login/RBAC).
- `resources/js/app.js`: marcado como entrypoint huérfano sin uso; entrada real `resources/js/app.ts` (única en `vite.config.ts` y `app.blade.php`).
- Re-ejecución en limpio: `composer validate` válido, `php artisan test` 2 passed/4 assertions, `npm run build` OK (manifest + 255 kB JS), `serve GET /` 200/941 B, sin sqlite.
