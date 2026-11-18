# INSTALL-001 — Revisión QA independiente R3 (2026-11-17)

**Feature:** `docs/work/features/INSTALL-001.md` — Instalador web CORE-17 P0.
**Revisor:** QA independiente (solo lectura; sin commit, push, deploy, migraciones reales ni ejecución del instalador real).
**Informes previos:** `docs/work/INSTALL-001-REVIEW-20261117.md` (RECHAZADO R1), `docs/work/INSTALL-001-REVIEW-20261117-R2.md` (RECHAZADO R2).
**Veredicto R3:** `APROBADO` (0 bloqueantes; 0 mayores; hallazgos no bloqueantes y límites abajo). No se declara VERIFIED.

## 1. Estado exacto revisado

- Repositorio: `C:\Users\Mario\PhpstormProjects\stockflash`
- Rama: `main`
- HEAD: `66b057bce6a936ae4e140e1edb9bbc3651e10a29`
- Working tree: **sin commit**; 11 archivos rastreados modificados y 56 archivos sin seguimiento (23 entradas `??`, incluido este informe).
- No se ejecutó `scripts/safe_git_pull.py`: HEAD coincide con el estado solicitado y hay cambios locales; no se descartó nada. `git branch -vv` local no tiene el commit remoto posterior `836965d` (docs-only según `gh`); fuera del alcance de esta revisión.
- No se ejecutó el instalador real; no se conectó MariaDB/BD remota; no se tocó el `.env` real; no se instaló cron; no se desplegó.
- Las sondas usaron SQLite en archivos temporales bajo `storage/framework/testing/` y `php -r` con bootstrap real; los temporales creados por esta revisión fueron eliminados. No se modificó código fuente.

### Hashes SHA-256 (16 hex)

```text
c282ab091f6a3144  app/Http/Controllers/InstallController.php
39d2ee531846f938  app/Http/Controllers/SetupController.php
b8278dc745cc269d  app/Http/Middleware/EnsureInstallerEnabled.php
82b7dcfd73c943ef  app/Http/Middleware/EnsureSetupCompleted.php
4b8c5fe7595b13e2  app/Http/Middleware/UseInstallStores.php
117a8cb6ab7301b4  app/Providers/AppServiceProvider.php
6ef90654ccc67ec6  app/Support/Install/ArtisanInstallMigrator.php
f7252e006630ef42  app/Support/Install/AtomicEnvSecretWriter.php
e65b44505da46873  app/Support/Install/DefaultInstallPreflightChecker.php
7bfd17a690d9943f  app/Support/Install/InstallDatabaseConfig.php
ed0a63ae5430370d  app/Support/Install/InstallSecretGenerator.php
405b1c531e5fee25  app/Support/Install/InstallService.php
631e4b8272f4e174  app/Support/Install/PdoInstallConnectionTester.php
473233f8650542b9  app/Support/Install/RuntimeDatabaseConfigurator.php
212d20ea82f5fa5a  app/Support/Setup/SetupService.php
82fb320dcef25237  bootstrap/app.php
5d06759ebc9ccb5c  config/install.php
c2a4b56bc7aeb2ec  routes/web.php
892d2954eb535d96  resources/js/Pages/Install/Wizard.vue
8244ec3556cdb7ec  resources/js/Pages/Install/Unavailable.vue
d80df6068c3a922d  resources/js/Pages/Setup/Wizard.vue
763a7c141c7858f5  tests/Feature/Install/InstallBootstrapSecurityTest.php
8c8f7825ac56c81b  tests/Feature/Install/InstallConnectionTest.php
4ab53d16945726f6  tests/Feature/Install/InstallDefaultStoresTest.php
895645ae9f17d8c8  tests/Feature/Install/InstallDestinationStateTest.php
b10163b580c77384  tests/Feature/Install/InstallGuardRegressionTest.php
01a8907b1a3f4965  tests/Feature/Install/InstallNoDestructiveTest.php
71852803da8cc088  tests/Feature/Install/InstallPreflightTest.php
2fe874d79ab74302  tests/Feature/Install/InstallRunTest.php
ed98924c6a0db7f3  tests/Feature/Install/InstallSetupNoticeTest.php
4fea4cc672f36c7d  tests/Feature/Install/InstallStateTest.php
68d6252563cb84fc  tests/Feature/Install/InstallStorageFailureTest.php
35bcafdfcdd42bf8  tests/Feature/Install/InstallTestCase.php
4677379df588f8fe  tests/Feature/Install/InstallWiringTest.php
6a27e38a8d0e896b  scripts/sf_verify.py
69ae72192e065194  .gitignore
```

## 2. Verificación adversarial B-04

**Resultado: RESUELTO.** El aviso ya no usa flash ni la sesión file del instalador; se calcula en servidor desde `installation_info` + `is_setup_completed`.

- `app/Http/Controllers/InstallController.php:203-207`: tras éxito solo `resetSession()` y redirect a `setup.show`; no hay `with('install_notice', ...)`.
- `app/Http/Controllers/SetupController.php:95-99,383-411`: `installNotice()` lee `installation_info` (una fila) y `SetupService::isCompleted()`; devuelve `{completed, cron_command, app_version, release_channel}` o `null`; todo dentro de `try/catch (Throwable)` y con `schemaReady()`/`hasTable` previos.
- `SetupService::schemaReady()` captura `Throwable` (`app/Support/Setup/SetupService.php:58-73`), por lo que una BD caída no produce 500 en el guard.
- `resources/js/Pages/Setup/Wizard.vue:18-33` renderiza `install_notice.cron_command`; el texto no depende de sesión.

**Sonda independiente (bootstrap real, sesión `database`, sin cookie del instalador):** tabla `sessions`, `system_configs`, `installation_info`, `users`, `branches`, `roles`, `audit_logs` en SQLite temporal; requests HTTP reales a `/setup`.

```text
BEFORE_INSTALL=200, component=Setup/Wizard, body_has_cron=false
INSTALLED_SETUP_PENDING=200, install_notice={"completed":true,"cron_command":"php artisan schedule:run","app_version":"9.9.9","release_channel":"stable"},
  body_has_cron=true
SETUP_COMPLETED=302 (redirect a /login), body_has_cron=false
NO_INSTALLATION_ROW=200, body_has_cron=false
NO_INSTALLATION_TABLE=200, body_has_cron=false
```

Fragmento real del `data-page` en el caso instalado/pendiente:

```text
"install_notice":{"completed":true,"cron_command":"php artisan schedule:run","app_version":"9.9.9","release_channel":"stable"}
```

- No expone secretos: solo versión, canal y la línea fija de cron; no hay claves, credenciales ni PII.
- No aparece antes de instalar ni con `installation_info` ausente o `is_setup_completed=1`; no es falsificable desde el request (se calcula desde BD).
- La prueba `InstallSetupNoticeTest` usa `RuntimeDatabaseConfigurator` real, archivo SQLite, borra la sesión y fija `session.driver=database`/`cache.default=database`; pasa (1 test, 17 assertions). `InstallRunTest` verifica el mismo `install_notice` tras el flujo HTTP.

## 3. Verificación B-05 (UTF-8 real)

**Resultado: RESUELTO.**

- Escaneo independiente de 40 archivos nuevos/modificados de Install (Vue/PHP/requests/tests) y de `resources/js/Pages/Setup/Wizard.vue`: decodificación UTF-8 estricta OK; **0** secuencias `Ã`/`Â`/`â€`; 0 `U+FFFD`; 0 controles C1; todos los code points no ASCII son español legítimo (`U+00E1/00E9/00ED/00F3/00FA/00F1/00B7/2014/2026`). Wizard de Install: 57 caracteres no ASCII válidos; Unavailable: 8; Setup/Wizard: 72.
- Asset compilado: `public/build/assets/app-B1HQAl7B.js` (435.770 bytes, 21:28:19) contiene el texto actual (`No fue posible crear las tiendas temporales`, `El token no se muestra ni se conserva`, `Paso 4`, `Instalación técnica`, `php artisan schedule:run`) con **0** mojibake, 0 `U+FFFD` y 0 pares dobles `C3 83 C2`. CSS (`app-BTBPBNPV.css`, `app-BZvL8UBI.css`) también limpio. El build es posterior a la modificación más reciente de `resources/js` (21:16:14).
- Gate I29 probado de forma adversaria con el código exacto de `scripts/sf_verify.py:1200-1201` sobre un directorio temporal:
  - contenido limpio: exit 0;
  - mojibake en `resources/js/Pages/Install/Wizard.vue`: exit 1;
  - mojibake solo en asset JS con `manifest.json` presente: exit 1;
  - mojibake solo en asset JS **sin** manifest: exit 0 (punto ciego; en `sf_verify.py` I19-build precede a I29, así que en el run completo el manifiesto existe).
- `npm run typecheck` (wrapper) no pudo ejecutarse por `ENOSPC` del disco del revisor; `node_modules/.bin/vue-tsc.cmd --noEmit` directo devolvió **exit 0**.

## 4. Verificación M-05 (storage no escribible)

**Resultado: RESUELTO en comportamiento controlado.**

- `app/Http/Middleware/UseInstallStores.php:65-96`: creación de sesión/caché file dentro de `try/catch (Throwable)`; si falla, usa `array` y marca `install.runtime_storage_unavailable=true`.
- `DefaultInstallPreflightChecker.php:97-108`: agrega el fallo de “tiendas temporales del instalador (sesión/caché)” al check `writable`.
- `resources/js/Pages/Install/Wizard.vue:19-28`: muestra el aviso `storage_warning` y explica que no se ejecutará la instalación hasta corregir permisos.
- `UseInstallStores.php:117-125`: restaura la configuración en `finally` (o-07).

**Sonda independiente (bootstrap real, sin fakes, storage bajo un archivo que no es directorio):**

```text
ENABLED_STATUS=200, storage_warning prop=present, HTTP 500=no
DISABLED_STATUS=404
DISABLED_BOOTSTRAP_STATUS=419 (sin token CSRF; fail-closed)
```

- `InstallStorageFailureTest`: 2 tests / 31 assertions PASS (200 con `storage_warning` true y preflight `writable=fail`; 404 deshabilitado).
- `InstallDefaultStoresTest`: 5/55 PASS, incluido GET `/install` con `SESSION_DRIVER=database`, `CACHE_STORE=database` y BD inexistente → 200 `DATABASE_UNAVAILABLE`, y deshabilitado → 404.
- Matiz no bloqueante: con storage realmente no escribible, la sesión `array` no persiste entre requests en un navegador; la página muestra el aviso de storage pero el detalle del preflight no es alcanzable en un flujo real de dos requests (ver N-02).

## 5. Menores R2 corregidos

| Hallazgo | Estado R3 | Evidencia |
|---|---|---|
| m-06 DB_URL/DB_SOCKET | **RESUELTO** | `AtomicEnvSecretWriter.php:39-42`; I32 PASS; suite Install 73/939 |
| m-07 foco al primer campo | **RESUELTO** | `Wizard.vue:510-537` mueve foco a `host/port/database/username/db_password/driver/...`; fallback al resumen |
| m-08 clave vacía con comentario | **RESUELTO** | `AtomicEnvSecretWriter.php:150-168`; prueba `escritor considera vacias claves con comentario en la misma linea` PASS |
| m-09 error bootstrap visible | **RESUELTO** | `Wizard.vue:497-508` `preserveState:true` + reset solo del token + foco |
| m-10 pruebas/gates con puntos ciegos | **PARCIAL (no bloqueante)** | Nuevos `InstallSetupNoticeTest`, `InstallStorageFailureTest`, `InstallDestinationStateTest` (configurador real) e `InstallStateTest` (guard real). Quedan brechas de gate y de representatividad de storage: ver N-01/N-02/N-06 |
| o-06 `lines` en persist() | **RESUELTO** | `AtomicEnvSecretWriter.php:86-89` y contrato `Contracts/InstallSecretWriter.php:17-21` solo `preserved`/`written` |
| o-07 config no restaurada | **RESUELTO** | `UseInstallStores.php:117-125` y aserción en `InstallDefaultStoresTest.php:29-33` |
| o-10 evidencia documental | **PARCIAL** | `INSTALL-001-NOTES...:58-59` cita `VERIFY-212318`; el último reporte es `VERIFY-212833` (ambos 32/32). GUIA ya describe el aviso real |
| o-11 CI base | **VERIFICADO por QA** | `gh run list`: workflows `CI StockFlash PHP/Node` y `Verify StockFlash contracts` **success** en `66b057b` |

## 6. Regresiones y garantías del packet

- `php artisan test tests/Feature/Install`: **PASS** — 73 tests, 939 assertions, 10.93 s.
- `php artisan test tests/Feature/Setup`: **PASS** — 51 tests, 468 assertions, 15.24 s.
- `php artisan test tests/Feature/Auth`: **PASS** — 45 tests, 108 assertions, 5.67 s.
- `php artisan test`: **PASS** — 602 tests, 5604 assertions, 128.13 s.
- `node_modules/.bin/vue-tsc.cmd --noEmit`: **PASS** (exit 0).
- `npm run build`: **NO RE-EJECUTADO** por `ENOSPC` del disco (npm `-4055`, `failed to load config from vite.config.ts`). El asset existente es posterior a las fuentes y fue verificado byte a byte (sección 3). El límite queda registrado; no se declara un build nuevo.
- `py -3 scripts/check_contracts.py`: **PASS** — `OK DOC-001: 362 referencias, 6 hashes y reglas contractuales comprobadas`.
- `py -3 scripts/sf_verify.py INSTALL-001`: **NO RE-EJECUTADO de punta a punta** por el mismo `ENOSPC` en I18/I19; el reporte previo `VERIFY-212833` declara 32/32. Se re-ejecutaron los gates relevantes: I5/I6/I7/I8/I9/I10/I17/I21, I26–I32 (vía tests) y la lógica I29 de forma adversaria.
- `git diff --quiet HEAD -- <protegidos>`: **exit 0**; `git status --porcelain -- database public/docs/index.html resources/roadmap/index.html docs/harness/CONTEXTO-CHATGPT.md` vacío. Sin cambios en `database/`, HTML editorial, roadmap ni `CONTEXTO-CHATGPT.md`.
- `git diff --check HEAD`: **exit 0** (solo warnings LF/CRLF de Git).
- Garantías revisadas en código: ningún GET migra; bootstrap consume token en tienda dedicada; rate limit y lock usan la tienda `install`; `perform()` configura el destino antes de evaluar estado; no hay `migrate:fresh`/`db:wipe`/`db:reset`/`truncate`/`delete`/`Schema::drop` ni shell; escritura temporal + rename (con fallback Windows no atómico); claves existentes preservadas y `DB_URL`/`DB_SOCKET` neutralizados; `dontFlash` para `bootstrap_token`/`db_password`; props sin secretos; CSRF 419 conductual; `/install` 404 cuando está deshabilitado; `/roadmap` y privadas protegidas; `/docs` intacto.
- `InstallStateTest` ejerce el guard real de estado instalado en el flujo `run` (preflight/connection reales vía fakes, `run` → 423, 0 migrate/seed) y `InstallDestinationStateTest` usa `RuntimeDatabaseConfigurator` real (destino ya instalado → bloqueado, 0 migrate/0 seed/0 writer, BD por defecto intacta). No son tautológicos.

## 7. Hallazgos nuevos (no bloqueantes)

**N-01 (menor) — Cobertura incompleta del gate I29 y del escaneo de asset.**
`scripts/sf_verify.py:1200-1201` escanea `resources/js/Pages/Install/*.vue`, `app/Support/Install/*.php` + contracts, `InstallController`, `UseInstallStores` y tests de Install. **No** escanea `app/Http/Requests/Install/*.php` (mensajes visibles al usuario), `app/Http/Middleware/EnsureInstallerEnabled.php`, `app/Providers/AppServiceProvider.php` ni `resources/js/Pages/Setup/Wizard.vue` (modificado). Además, el bloque de asset está condicionado a `public/build/manifest.json` (`:1207-1213`). En esta revisión todos esos archivos están limpios por escaneo independiente y el run completo de gates construye antes de I29; no afecta el resultado actual.

**N-02 (menor) — La prueba de storage no representa el flujo real de dos requests.**
`tests/Feature/Install/InstallStorageFailureTest.php:38-53` llega a preflight porque la sesión `array` persiste dentro del mismo proceso de test. En un navegador con storage roto, la sesión array no persiste y el operador ve el aviso `storage_warning` (`Wizard.vue:19-28`), no la lista de checks. El comportamiento es controlado (200, sin 500) y fail-closed; la prueba del checker real (`InstallPreflightTest.php:99-110`) cubre el reporte del permiso.

**N-03 (menor) — `POST /install/bootstrap` deshabilitado responde 419 sin CSRF, no 404, en entorno real.**
Sonda real: `DISABLED_STATUS=404` (GET) y `DISABLED_BOOTSTRAP_STATUS=419` (POST sin token CSRF). Las pruebas declaran 404 (`InstallStorageFailureTest.php:75-77`, `InstallBootstrapSecurityTest.php:10-18`) porque el entorno de test omite CSRF. Es fail-closed (419), pero la afirmación “404 incluso ante payload inválido” (`EnsureInstallerEnabled.php:9-13`) no es exacta para POST sin CSRF.

**N-04 (observación) — Restauración parcial del store del instalador en el fallback.**
`UseInstallStores.php:86-96` deja `cache.stores.install` como `array` y `:119-124` no restaura esa entrada. Sin efecto en PHP-FPM (config por request); solo relevante en procesos long-running (Octane).

**N-05 (observación operativa) — Prerrequisito de sesión para `/setup` posterior.**
`.env.example` y `config/session.php:21` usan `SESSION_DRIVER=database`, pero `database/migrations` no crea `sessions`/`cache`; el despliegue histórico usa `SESSION_DRIVER=file` (`BOOT-001-EVIDENCE.md:21,29`, `STAGING-001-INFORME-DESPLIEGUE.md:29`). El instalador no lo advierte y `InstallSetupNoticeTest` crea la tabla `sessions` manualmente (`:126-139`). No es una regresión de INSTALL-001, pero el operador debe usar file o disponer de la tabla antes de continuar a `/setup`; de lo contrario `/setup`/`/login` fallan por sesión.

**N-06 (observación) — Falta una prueba negativa del aviso.**
No hay test que afirme `install_notice=null` antes de instalar o con `is_setup_completed=1`; la sonda independiente lo verificó. Se recomienda agregarla como regresión.

**N-07 (observación documental) — Referencia de gates desactualizada.**
`INSTALL-001-NOTES-20261117.md:58-59` cita `VERIFY-212318`; el reporte más reciente es `VERIFY-212833` (32/32 en ambos).

**N-08 (observación de alcance) — Esquema arbitrario no pre-detectado.**
`InstallService::state()` detecta `installation_info` incompatible y usuarios/sucursales, pero no un esquema ajeno sin esas tablas. En ese caso `migrate` falla de forma controlada sin borrar datos ni marcar éxito (y el `.env` ya escrito queda para recuperación manual, o-09). No hubo borrado ni reinstalación; se recomienda un preflight de conflicto de tablas si el owner quiere cubrir literalmente “esquema incompatible”.

**N-09 (observación) — Metadatos públicos en `/install`.**
`InstallController.php:52-53` expone `app_version` y `release_channel` antes de autorizar; no son secretos, pero conviene decidir si deben mostrarse en la página pública habilitada.

## 8. Límites / no ejecutados

- `real_installer=NOT_RUN`
- `maria=NOT_RUN`
- `remote_db=UNTOUCHED`
- `env_real=UNTOUCHED` (no se leyó ni modificó; las sondas cargan la app, no copian valores)
- `cron=NOT_INSTALLED`
- `deploy=NOT_RUN`
- `config_clear=NOT_RUN`
- `safe_git_pull=NOT_RUN` (HEAD ya era el solicitado; había cambios locales y no se descartó nada)
- `npm_run_build=NOT_RERUN_ENOSPC`; `npm_run_typecheck_wrapper=FAIL_ENOSPC` (binario `vue-tsc` directo PASS)
- `sf_verify_end_to_end=NOT_RUN` (I18/I19 requieren npm y el disco está lleno); reporte previo `32/32`
- `tests/Feature/Install/InstallSecretsTest.php=NOT_READ` por política de entorno `*secrets*`; se ejecutó en la suite y en I32 y se revisó su efecto por contrato/nombres de pruebas/gate
- `CI=2/2 SUCCESS en 66b057b` verificado con `gh`; los cambios sin commit no están cubiertos por CI

## 9. Veredicto

**`APROBADO` — 0 bloqueantes.**

B-04, B-05 y M-05 están resueltos con evidencia ejecutada; no se reprodujeron regresiones en SETUP/AUTH ni en la suite completa; no hay filtración de secretos ni operaciones destructivas; los protegidos permanecen intactos. Los puntos N-01..N-09 son no bloqueantes y deben considerarse para el cierre/endurecimiento. Este informe no declara `VERIFIED`; esa decisión corresponde a la revisión externa con gates, QA y CI sobre el SHA publicado.