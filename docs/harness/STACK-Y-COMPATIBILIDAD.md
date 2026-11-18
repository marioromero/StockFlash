# StockFlash stack and host compatibility — verified scope

App: Laravel 13 (PHP 8.3+), Vue 3, Inertia 3.5.x, TypeScript, Vite. Exact locked versions come from composer.lock/package-lock.json at current SHA; do not trust October historical guesses. Local Windows PHP/Node versions and cPanel runtime can differ; Node 24 CI build does not imply Node24 on cPanel.

MariaDB target >=10.6, InnoDB and predeployment ARQ-13 checks for special generated/unique columns; isolated SQLite :memory: PHP tests do **not** prove real MariaDB compatibility or concurrency. Hosting uses one cron/minute scheduler with bounded queue workers; no Supervisor requirement. Deploy Laravel application outside public webroot, exposing only public/. Never expose .env, vendor, backups or private keys.

Feature tests, typecheck/build, es-CL HTTP smoke, independent QA, exact-SHA CI and security restrictions apply to each packet as approved. No implicit remote DB migrations or deployment from a local test.
