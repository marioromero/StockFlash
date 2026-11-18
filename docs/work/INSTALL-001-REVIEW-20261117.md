# INSTALL-001 — Revisión QA independiente (2026-11-17)

**Feature:** `docs/work/features/INSTALL-001.md` — Instalador web CORE-17 P0.
**Revisor:** QA independiente (solo lectura; sin commit, push, deploy ni migraciones reales).
**Veredicto:** `RECHAZADO` (3 BLOQUEANTES, 3 MAYORES, 4 MENORES, 5 OBSERVACIONES).

## 1. Estado exacto revisado

- Repositorio: `C:\Users\Mario\PhpstormProjects\stockflash`
- Rama: `main`
- HEAD: `66b057bce6a936ae4e140e1edb9bbc3651e10a29`
- Estado: cambios en working tree **sin commit**; 6 archivos rastreados modificados y 41 archivos nuevos (untracked).
- No se ejecutó `scripts/safe_git_pull.py`: HEAD ya coincide con el estado informado y existen cambios locales; no se descartó nada.
- No se ejecutó el instalador real, ni se tocó `.env`, MariaDB/BD remota, cron, cPanel ni despliegue. Las simulaciones HTTP usaron scripts temporales fuera del repo y SQLite `:memory:`/archivo inexistente.
- `npm run build` regeneró `public/build` (ignorado por Git); no alteró el estado versionado.

### Hashes SHA-256 (16 hex) de archivos relevantes

```text
7f64abb14c9a350b  app/Http/Controllers/InstallController.php
b8278dc745cc269d  app/Http/Middleware/EnsureInstallerEnabled.php
142bf1302c314309  app/Http/Middleware/EnsureSetupCompleted.php
c29a618a916c73c5  app/Http/Requests/Install/InstallBootstrapRequest.php
937a8cb3022ad7e0  app/Http/Requests/Install/InstallConnectionRequest.php
cf1edd9780b3930a  app/Http/Requests/Install/InstallRunRequest.php
6ef90654ccc67ec6  app/Support/Install/ArtisanInstallMigrator.php
e517dbf91f98b84d  app/Support/Install/AtomicEnvSecretWriter.php
ec2f20084be58777  app/Support/Install/Contracts/InstallConnectionTester.php
62f0cd76737a129a  app/Support/Install/Contracts/InstallDatabaseConfigurator.php
f20654dd37142e27  app/Support/Install/Contracts/InstallMigrator.php
f2af10bb390aa396  app/Support/Install/Contracts/InstallPreflightChecker.php
9a9b4e3aa615338f  app/Support/Install/Contracts/InstallSecretWriter.php
44bf60c1241b1e92  app/Support/Install/DefaultInstallPreflightChecker.php
7bfd17a690d9943f  app/Support/Install/InstallDatabaseConfig.php
ed0a63ae5430370d  app/Support/Install/InstallSecretGenerator.php
5286c0e0dca073a7  app/Support/Install/InstallService.php
631e4b8272f4e174  app/Support/Install/PdoInstallConnectionTester.php
473233f8650542b9  app/Support/Install/RuntimeDatabaseConfigurator.php
e3b37ff7f9885712  bootstrap/app.php
3e7bacec70b61671  config/install.php
ea166e460ed1149c  routes/web.php
ebb63ebda22a6ecd  scripts/sf_verify.py
975a48b27b2a463a  resources/js/Pages/Install/Wizard.vue
20fb4da4c526a14c  resources/js/Pages/Install/Complete.vue
6f6b9e3991702b09  resources/js/Pages/Install/Unavailable.vue
1f52bb5771a0596d  tests/Feature/Install/InstallBootstrapSecurityTest.php
8c8f7825ac56c81b  tests/Feature/Install/InstallConnectionTest.php
f05185725d90b6af  tests/Feature/Install/InstallGuardRegressionTest.php
6ee2dd6a336024df  tests/Feature/Install/InstallNoDestructiveTest.php
f0737fdce57ad579  tests/Feature/Install/InstallPreflightTest.php
7c09269258d79d38  tests/Feature/Install/InstallRunTest.php
37af7a28eeb14717  tests/Feature/Install/InstallSecretsTest.php
05d085ff33f92a29  tests/Feature/Install/InstallStateTest.php
eaced7598fc85ca6  tests/Feature/Install/InstallTestCase.php
d7bd409fda4f1507  tests/Feature/Install/Support/FakeConnectionTester.php
43b835ceaa8a4b25  tests/Feature/Install/Support/FakeDatabaseConfigurator.php
678e1eea86b129af  tests/Feature/Install/Support/FakeMigrator.php
e94bf92c8c5d5aa2  tests/Feature/Install/Support/FakePreflightChecker.php
9d0ac28e661ee11c  tests/Feature/Install/Support/FakeSecretWriter.php
8c9049814edb7487  docs/work/GUIA-HTML-ESTADO.md
91434a7bed5de8e7  docs/work/ROADMAP-HTML-ESTADO.md
7a19b382477d7402  docs/work/INSTALL-001-NOTES-20261117.md
b08bd62385a935d1  docs/work/INSTALL-001-VERIFY-20261117-200115.md
```

## 2. Criterios de aceptación, uno a uno

| # | Criterio | Resultado | Evidencia / motivo |
|---|---|---|---|
| 1 | `/install` Inertia/Vue es-CL, preflight, prueba BD, claves, confirmación, migraciones/seed simulables, estado final con cron, redirección a `/setup`, sin secretos en HTML/props | **FAIL** | B-01: todas las rutas `/install` fallan con 500 por DI no resuelta. B-02: no carga con los drivers por defecto del repo. M-02: tras éxito redirige a `/install/complete`, no a `/setup`. La UI sí es es-CL, responsive y sin secretos en props. |
| 2 | Fail-closed: bootstrap fuera de la app, `enabled=false`, sesión/CSRF/token, validación por paso, rate limit, lock, ningún GET migra, ningún GET público migra | **FAIL** | El diseño fail-closed existe (`config/install.php`, middleware, POST-only, `hash_equals`), pero B-02 y M-01 impiden operarlo con BD/esquema ausente: `/install` responde 500 antes de `EnsureInstallerEnabled`; incluso deshabilitado devuelve 500 en vez de 404. CSRF no se prueba de forma conductual (solo se comprueba que el grupo `web` está en la ruta). No hay endpoint GET que migre. |
| 3 | Nunca reinstalar: `installation_info`/usuarios/sucursales/esquema incompatible bloquean sin borrar datos ni `.env` ni sobrescribir claves; sin `fresh`/`wipe`/reset | **FAIL** | B-03: `state()`/`assertVirgin()` se evalúan contra la conexión por defecto de `.env`, no contra la BD elegida; la revalidación dentro del lock también ocurre antes de `RuntimeDatabaseConfigurator::configure()`. Una BD destino ya instalada puede recibir `migrate --force`/`db:seed` antes de detectarse; y el escritor puede sobrescribir `APP_KEY` del `.env` existente. No hay `fresh`/`wipe`/reset. |
| 4 | Secretos fuera de HTML/props/logs/flash/auditoría/QA/commits/old input; CSPRNG; únicas; validación host/puerto/nombres; sin shell; escritura atómica; nunca leer `.env` real | **PARCIAL** | `random_bytes` (`InstallSecretGenerator.php:22-40`), `hash_equals`, `dontFlash`, props sin secretos, `SensitiveParameter`, `__debugInfo`, `tempnam`+`rename`, `chmod 0600`. Fallos: M-03 (`APP_KEY`/claves existentes se sobrescriben), m-02 (`$`/`${}` no escapados en `.env`), y la escritura Windows tiene ventana no atómica al borrar el destino. |
| 5 | Orquestación preflight → conexión → confirmación → migraciones + seeds §13.1 → `installation_info` UUID v4 → cron → `/setup`; fallo intermedio no marca éxito ni revierte; estado fallido/recoverable | **FAIL** | Orden implementado, pero B-03 y B-02 impiden que la orquestación funcione en el escenario real; el `installation_info` se inserta solo al final (bien), pero la comprobación de estado se hace contra la conexión equivocada. No hay transacción filesystem+BD (correcto), pero el `.env` se escribe antes de migrar y un fallo deja configuración nueva sin instalación marcada. |
| 6 | Guards/rutas: `/install` solo con bootstrap autorizado y sin esquema; exenciones por nombre exacto; `/roadmap` y privadas protegidas; `/docs` intacto; auth no afectada; sin datos comerciales | **PARCIAL** | Exenciones exactas correctas (`EnsureSetupCompleted.php:29-47`), rutas POST-only, `/roadmap` y `/docs` con regresiones PASS, sin usuarios/sucursales. Pero `/install` no es alcanzable sin esquema con la configuración por defecto (B-02/M-01), que es justo el caso previsto. |
| 7 | Seeders idempotentes sin demo/licencias; defaults contractuales; sin framework/DSL externo ni dependencias nuevas | **PASS** | `DatabaseSeeder` sin cambios; seeders existentes; `composer.json`/`package.json` sin cambios; sin shell ni framework instalador. |
| 8 | Tests requeridos: preflight, errores PHP/BD, CSRF/bootstrap, virgin/instalada/preexistente, UUID, no fuga, claves, migración/seed simuladas, fallo a mitad, `/setup`, guard roadmap, doble confirmación, no borrado; regresiones; suite PHP/TS/build/contratos; pruebas reales no tautológicas ni con BD real | **PARCIAL** | Existen y pasan 58/639 del instalador y 587/5304 de suite; TS/build/contratos PASS. Faltan pruebas conductuales de: DI real, drivers por defecto, BD caída, conexión destino distinta de `.env`, CSRF real y "token de un solo uso". Varias pruebas son estáticas (`InstallNoDestructiveTest`, `test_dont_flash...`) o usan fakes que ocultan B-01/B-03. |
| 9 | Protected files intactos; sin cambios en `database/migrations` ni `database/seeders`; sin editar HTML editorial ni `CONTEXTO-CHATGPT.md` | **PASS** | `git diff --quiet HEAD -- <protegidos>` exit 0; `git status --porcelain -- database public/docs/index.html resources/roadmap/index.html docs/harness/CONTEXTO-CHATGPT.md` vacío. |
| 10 | Metadatos `GUIA_PENDIENTE_CHATGPT` / `ROADMAP_PENDIENTE_CHATGPT` correctos | **PASS** | `GUIA-HTML-ESTADO.md` y `ROADMAP-HTML-ESTADO.md` registran `pending` distinto de `NINGUNO`, `next_feature`/`in_progress` INSTALL-001 y los handoffs; gate I20 PASS. |

## 3. Hallazgos

### BLOQUEANTES

**B-01 — El instalador no se puede resolver fuera de los tests: no hay bindings de las interfaces.**
- `app/Providers/AppServiceProvider.php:12-15` no registra ningún binding.
- `app/Support/Install/InstallService.php:40-45` exige `InstallSecretGenerator`, `InstallDatabaseConfigurator`, `InstallSecretWriter` y `InstallMigrator`; estos cuatro son interfaces (`app/Support/Install/Contracts/*`).
- No existe `bind()`/`singleton()` para `InstallPreflightChecker`, `InstallConnectionTester`, `InstallMigrator`, `InstallSecretWriter` ni `InstallDatabaseConfigurator` en `app/`, `bootstrap/` ni `config/`.
- Evidencia ejecutada (script temporal, bootstrap real):
  `BindingResolutionException :: Target [App\Support\Install\Contracts\InstallDatabaseConfigurator] is not instantiable while building [App\Support\Install\InstallService].`
- Los tests no lo detectan porque `tests/Feature/Install/InstallTestCase.php:64-68` inyecta fakes en el contenedor.
- Impacto: cualquier request a `/install*` en un entorno real termina en 500; criterios 1, 2 y 5 no se cumplen.

**B-02 — Con la configuración por defecto del repo, el instalador no opera antes del esquema.**
- `config/session.php:21` → `SESSION_DRIVER` por defecto `database`; `config/cache.php:18` → `CACHE_STORE` por defecto `database`; `.env.example:30,40` confirma ambos.
- Evidencia HTTP con bootstrap real y bindings stub (SQLite `:memory:`, sin tablas): `SESSION_DRIVER=database`, `CACHE_STORE=array` → `/install` `STATUS=500`.
- `RateLimiter::tooManyAttempts()` (`InstallController.php:82-86`) y `Cache::lock()` (`InstallService.php:261-269`) usan el store por defecto. Evidencia ejecutada con `CACHE_STORE=database` y esquema ausente: `RATE_LIMITER_THROWN=Illuminate\Database\QueryException` y `CACHE_LOCK_THROWN=Illuminate\Database\QueryException`.
- `phpunit.xml` fuerza `SESSION_DRIVER=array`/`CACHE_STORE=array`, ocultando el problema.
- Impacto: con el `.env` estándar del proyecto, `/install` no carga, `/install/bootstrap` falla al aplicar rate limit y `/install/run` falla al tomar el lock; el instalador no puede preparar el esquema que él mismo necesita. No hay override en el grupo de rutas ni en `config/install.php`, ni advertencia en las notas.

**B-03 — La comprobación "nunca reinstalar" se hace contra la conexión equivocada.**
- `InstallService::perform()` ejecuta `state()` en `InstallService.php:255` y otra vez en `:273`; `RuntimeDatabaseConfigurator::configure($database)` recién se invoca en `:280`.
- Los pasos previos también usan la conexión por defecto: `InstallController.php:77,104,123,148` (`assertVirgin()`).
- `recordInstallationInfo()` (`InstallService.php:342-350`) recién verifica `installation_info`/usuarios/sucursales **después** de `migrate()` y `seed()` (`:282-284`), y solo comprueba `installation_info`; no revalida usuarios/sucursales en la conexión destino.
- Impacto: si `.env` apunta a otra BD (o a una BD vacía) y la BD elegida ya tiene `installation_info`, usuarios o sucursales, se ejecutan migraciones/semillas sobre una instalación existente antes de detectarlo; y si `.env` no conecta, `perform()` responde `DATABASE_UNAVAILABLE` aunque la BD elegida sea válida. Además, en ese escenario `AtomicEnvSecretWriter` puede sobrescribir `APP_KEY` del `.env` de una instalación existente. Viola el criterio 3 y el 5.

### MAYORES

**M-01 — `/install` devuelve 500, no el estado controlado, cuando la BD no responde; y el instalador deshabilitado devuelve 500, no 404.**
- `bootstrap/app.php:14-22` añade `EnsureSetupCompleted` al grupo `web`; se ejecuta antes del middleware de ruta `EnsureInstallerEnabled` (`routes/web.php:22-29`).
- `EnsureSetupCompleted.php:53` llama `schemaReady()`; `SetupService.php:60-64` ejecuta `Schema::hasTable()` sin `try/catch`.
- Evidencia HTTP (mismo bootstrap, stubs de DI, SQLite con archivo inexistente): `STATUS=500` con `INSTALL_ENABLED=1` y también con `INSTALL_ENABLED=0`; se esperaba 423 controlado y 404 respectivamente. Con `:memory:` y stubs: `STATUS=200`.
- Impacto: el paso 2 "probar conexión BD" es inalcanzable si la BD aún no está configurada; el estado `DATABASE_UNAVAILABLE` de `InstallService.php:175` no llega a mostrarse; el fail-closed no responde 404 como afirma el diseño.

**M-02 — El éxito no redirige a `/setup`.**
- `InstallController.php:187-189` redirige a `route('install.complete')`; `Complete.vue:50-52` solo ofrece un enlace manual "Continuar a /setup".
- El packet (entregable 1) dice "Tras éxito, enviar a /setup"; el criterio 1 pide "redirección a /setup". Se muestra el cron y el enlace, pero no hay redirección automática.

**M-03 — El escritor sobrescribe claves existentes sin condición.**
- `AtomicEnvSecretWriter.php:29-39` incluye siempre `APP_KEY`, `BLIND_INDEX_KEY` y `BACKUP_ENCRYPTION_KEY`; `mergeLines()` (`:95-114`) reemplaza cualquier línea existente de esas claves.
- En una instancia VIRGIN con `.env` ya preprovisionado, o en el escenario B-03, se pierden las claves anteriores; datos cifrados con la `APP_KEY` previa quedarían irrecuperables. El criterio 4 exige no sobrescribir claves de una instalación existente.

### MENORES

**m-01 — El token de arranque no es realmente de un solo uso.**
- `InstallService.php:80-87` guarda en sesión el hash del token y regenera la sesión, pero no invalida/rota el token configurado; cualquier otra sesión puede reutilizarlo mientras `INSTALL_ENABLED=true`. Las notas indican rotación manual, pero no hay consumo ni invalidación automática.

**m-02 — El escaping de `.env` no cubre `$`/`${}`.**
- `AtomicEnvSecretWriter.php:119-126` solo escapa `\` y `"`. Un password de BD con `${...}` puede ser interpolado por phpdotenv al leerse; el test solo cubre comillas y backslash.

**m-03 — El token de arranque permanece en el formulario del cliente tras un error.**
- `Wizard.vue:467-471` usa `preserveState: true`; el valor de `bootstrapForm.bootstrap_token` queda en memoria del componente, aunque el texto de `Wizard.vue:25` afirma que "no se conserva en el formulario". El servidor sí limpia old input (test PASS).

**m-04 — Accesibilidad/tactilidad por debajo del contrato de marca.**
- `.sf-button` (`Wizard.vue:727-736`) tiene padding `0.55rem 1rem`, altura aproximada < 44 px; el contrato de marca pide objetivo táctil >= 44 px. Los errores usan `role="alert"` pero no se mueve el foco al primer error.

### OBSERVACIONES

**o-01 — Los gates verdes no prueban el cableado real.**
- `scripts/sf_verify.py` I3/I4 son comprobaciones de subcadenas; I8/I17 ejecutan tests con fakes y drivers `array`. Ningún gate verifica bindings de DI, `SESSION_DRIVER`/`CACHE_STORE` por defecto, BD caída ni estado contra la conexión destino. `GATES_PASS 21/21` es consistente con lo estático, pero insuficiente para el criterio 1/2/3.

**o-02 — Colores fuera de los tokens de marca.**
- `Wizard.vue:520-526` usa `#0f172a`/`#2563eb`; el contrato (`docs/branding/brand-contract.md`) define `#303846`/`#0856FF` y `resources/css/app.css:5-6` los expone como variables. Es el mismo patrón ya presente en `Pages/Setup/*.vue` (VERIFIED), por lo que se registra como observación y no como regresión nueva. No hay PNG oficial; el fallback textual StockFlash es correcto.

**o-03 — `/install/complete` es público.**
- `InstallController.php:192-215` no exige auth ni token; tras instalar expone `app_version`, `release_channel` e `installed_at`. Sin PII ni secretos; se recomienda mantenerlo no indexable o protegerlo si el owner lo considera sensible.

**o-04 — Trazabilidad de evidencia.**
- `INSTALL-001-NOTES-20261117.md:34-35` cita el reporte `...195651.md` como evidencia de 21/21; el reporte más reciente es `...200115.md`. Ambos son `GATES_PASS`, pero conviene referenciar el último.

**o-05 — Sin `config:clear` tras escribir `.env`.**
- Si el despliegue usa `php artisan config:cache`, las claves/BD recién escritas no se aplicarán a los siguientes requests (`/setup`) hasta limpiar la caché. No está documentado en las notas ni ejecutado por el instalador.

## 4. Pruebas ejecutadas (resultados reales)

| Comando | Resultado |
|---|---|
| `php artisan test tests/Feature/Install` | **PASS** — 58 tests, 639 assertions, 7.19 s |
| `php artisan test` | **PASS** — 587 tests, 5304 assertions, 105.29 s |
| `npm run typecheck` | **PASS** — exit 0 |
| `npm run build` | **PASS** — exit 0, 811 modules, 3.50 s |
| `py -3 scripts/check_contracts.py` | **PASS** — `OK DOC-001: 362 referencias, 6 hashes...` |
| `git diff --quiet HEAD -- <protected files>` | **PASS** — exit 0; sin cambios en `database/`, HTML editorial ni `CONTEXTO-CHATGPT.md` |
| `git diff --check HEAD` | **PASS** — solo warnings LF/CRLF de Git |
| Simulación HTTP `/install` con bindings stub y SQLite `:memory:` (`SESSION_DRIVER=array`, `CACHE_STORE=array`) | `STATUS=200` |
| Simulación HTTP `/install` con bindings stub y SQLite archivo inexistente, `INSTALL_ENABLED=1` | `STATUS=500` (esperado 423 controlado) — M-01 |
| Simulación HTTP `/install` con bindings stub y SQLite archivo inexistente, `INSTALL_ENABLED=0` | `STATUS=500` (esperado 404) — M-01 |
| Simulación HTTP `/install` con `SESSION_DRIVER=database`, esquema ausente | `STATUS=500` — B-02 |
| Bootstrap real resolviendo `InstallService` sin bindings | `BindingResolutionException` — B-01 |
| `RateLimiter::tooManyAttempts()` con `CACHE_STORE=database`, esquema ausente | `Illuminate\Database\QueryException` — B-02 |
| `Cache::lock()` con `CACHE_STORE=database`, esquema ausente | `Illuminate\Database\QueryException` — B-02 |

No se re-ejecutó `py -3 scripts/sf_verify.py INSTALL-001` de punta a punta para evitar generar un nuevo reporte y repetir la suite; sus gates no-suite se verificaron individualmente y la suite completa se re-ejecutó con el resultado anterior. El veredicto `GATES_PASS 21/21` no se contradice: los gates no cubren B-01/B-02/B-03/M-01.

## 5. Resultados no ejecutados / límites

- `real_installer=NOT_RUN`
- `maria=NOT_RUN`
- `remote_db=UNTOUCHED`
- `env_real=UNTOUCHED`
- `cron=NOT_INSTALLED`
- `deploy=NOT_RUN`
- `scripts/safe_git_pull.py=NOT_RUN`
- No se declaró `VERIFIED`; no se ejecutaron migraciones ni se modificó código (solo se creó este informe).

## 6. Veredicto

**`RECHAZADO`** — 3 bloqueantes (B-01, B-02, B-03) impiden que el instalador funcione en un entorno real y que se cumpla la garantía de no reinstalación. Corregir, volver a ejecutar gates/tests y solicitar nueva revisión independiente.

