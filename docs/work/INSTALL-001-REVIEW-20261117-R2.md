# INSTALL-001 - Revision QA independiente R2 (2026-11-17)

**Feature:** `docs/work/features/INSTALL-001.md` - Instalador web CORE-17 P0.
**Revisor:** QA independiente (solo lectura; sin commit, push, deploy, migraciones reales ni ejecucion del instalador real).
**Informe previo:** `docs/work/INSTALL-001-REVIEW-20261117.md` (RECHAZADO: B-01/B-02/B-03, M-01/M-02/M-03, m-01..m-04, o-01..o-05).
**Veredicto R2:** `RECHAZADO` (2 bloqueantes nuevos B-04/B-05, 1 mayor M-05 y defectos menores). No se declara VERIFIED.

## 1. Estado exacto revisado

- Repositorio: `C:\Users\Mario\PhpstormProjects\stockflash`
- Rama: `main`
- HEAD: `66b057bce6a936ae4e140e1edb9bbc3651e10a29`
- Estado: working tree **sin commit**; 11 archivos rastreados modificados y 51 archivos sin seguimiento (incluye este informe y el reporte de gates generado en la revision).
- No se ejecuto `scripts/safe_git_pull.py`: HEAD coincide con el estado informado y hay cambios locales; no se descarto nada.
- No se ejecuto el instalador real, no se conecto MariaDB/BD remota, no se toco el `.env` real, no se instalo cron ni se desplego. Las pruebas usaron SQLite `:memory:`/archivos temporales y dobles de conexion/migrador/secretos.
- Se genero un reporte de gates nuevo: `docs/work/INSTALL-001-VERIFY-20261117-210109.md` (GATES_PASS 28/28). No se modifico codigo fuente.

### Hashes SHA-256 (16 hex) de archivos relevantes

```text
e7893e872b7fb48d  app/Http/Controllers/InstallController.php
15b51b8324dc1c18  app/Http/Controllers/SetupController.php
b8278dc745cc269d  app/Http/Middleware/EnsureInstallerEnabled.php
e124b7a30cafdd16  app/Http/Middleware/UseInstallStores.php
82b7dcfd73c943ef  app/Http/Middleware/EnsureSetupCompleted.php
146ece4a4b6c970c  app/Providers/AppServiceProvider.php
405b1c531e5fee25  app/Support/Install/InstallService.php
473233f8650542b9  app/Support/Install/RuntimeDatabaseConfigurator.php
db4adfb19091494d  app/Support/Install/AtomicEnvSecretWriter.php
6ef90654ccc67ec6  app/Support/Install/ArtisanInstallMigrator.php
44bf60c1241b1e92  app/Support/Install/DefaultInstallPreflightChecker.php
631e4b8272f4e174  app/Support/Install/PdoInstallConnectionTester.php
7bfd17a690d9943f  app/Support/Install/InstallDatabaseConfig.php
ed0a63ae5430370d  app/Support/Install/InstallSecretGenerator.php
212d20ea82f5fa5a  app/Support/Setup/SetupService.php
82fb320dcef25237  bootstrap/app.php
c2a4b56bc7aeb2ec  routes/web.php
5d06759ebc9ccb5c  config/install.php
f94630edcec2d0fb  resources/js/Pages/Install/Wizard.vue
e4a785e33d342ef4  resources/js/Pages/Install/Unavailable.vue
759aab03246fe0fb  resources/js/Pages/Setup/Wizard.vue
4677379df588f8fe  tests/Feature/Install/InstallWiringTest.php
5aacc828d56a80c7  tests/Feature/Install/InstallDefaultStoresTest.php
89a446980dbe8eeb  tests/Feature/Install/InstallDestinationStateTest.php
4babff0f5e283d32  tests/Feature/Install/InstallRunTest.php
763a7c141c7858f5  tests/Feature/Install/InstallBootstrapSecurityTest.php
b77c6e8877891503  scripts/sf_verify.py
69ae72192e065194  .gitignore
```

## 2. Correccion de hallazgos previos

| Hallazgo | Estado R2 | Evidencia / observacion |
|---|---|---|
| B-01 DI real | **RESUELTO** | `AppServiceProvider.php:27-31` registra las 5 interfaces; `InstallWiringTest` resuelve `InstallService` sin fakes y GET `/install` responde 200 con el contenedor normal. Sonda independiente con bootstrap real: OK. |
| B-02 drivers database sin esquema | **RESUELTO** | `UseInstallStores` queda en indice 0 y `StartSession` en 3 (orden real medido). GET `/install` con `SESSION_DRIVER=database`/`CACHE_STORE=database` y BD inexistente: 200, estado `DATABASE_UNAVAILABLE`. Rate limit, lock y flujo completo funcionan con tiendas file dedicadas. `/login` y `/setup` conservan `session.driver=database`/`cache.default=database`. Instalador deshabilitado: 404 aun con BD caida. |
| B-03 estado contra BD destino | **RESUELTO** | `InstallService.php:285` configura el destino, `:287` evalua estado, `:293-297` recien escribe secretos/migra/siembra/registra. `recordInstallationInfo()` revalida usuarios/sucursales (`:379-384`). Sonda con configurador real: destino ya instalado => bloqueado, 0 migrate, 0 seed, `.env` intacto; destino virgen => exito y `ALREADY_INSTALLED` posterior. |
| M-01 sin 500 / 404 | **RESUELTO (con nueva M-05)** | BD caida: `/install` 200 Wizard; deshabilitado: 404; `schemaReady()` captura `Throwable` (`SetupService.php:58-73`). Queda un 500 si las tiendas file del instalador no se pueden crear (ver M-05). |
| M-02 exito a /setup mostrando cron | **NO RESUELTO** | El redirect server-side a `/setup` funciona, pero el flash `install_notice` se guarda en la sesion file dedicada del instalador y `/setup` usa otra tienda/path. Sonda de dos procesos: `/setup` recibe `"install_notice":null` y **no** muestra `php artisan schedule:run`. Ver B-04. |
| M-03 claves existentes | **RESUELTO** | `AtomicEnvSecretWriter::mergeLines()` conserva `APP_KEY`/`BLIND_INDEX_KEY`/`BACKUP_ENCRYPTION_KEY` no vacias y completa ausentes/vacias. Sonda: `preserved=["APP_KEY"]`, claves vacias completadas, linea `APP_KEY` intacta. |
| m-01 token unico | **RESUELTO con salvedad** | `consumeBootstrapToken()` (`InstallService.php:90-97`) usa la tienda de instalacion y el test lo valida desde otra sesion. El marcador expira en 1 dia y se pierde si se limpia la cache; la garantia practica depende de deshabilitar/rotar el token y de `ALREADY_INSTALLED`. |
| m-02 escaping `$`/`${}` | **RESUELTO** | `AtomicEnvSecretWriter::quote()` escapa `\\`, `"`, `$` (`:162-166`). Sonda con password `pw${X}yz"q$HOME\\tail`: phpdotenv lo lee literal (`DB_PASSWORD_EQ=YES`). |
| m-03 token no conservado | **RESUELTO parcial** | `preserveState:false` + reset y `dontFlash` limpian el token; el test server-side lo confirma. Efecto lateral: el error de validacion del bootstrap puede no mostrarse al remontarse el componente (ver m-09). |
| m-04 foco/44px | **PARCIAL** | 44 px en inputs/botones/checkbox. El foco solo va al recuadro resumen de errores `install`/`db`/`preflight`; errores de campo como `host`/`port` no mueven el foco (ver m-07). |
| o-01 gates verdes que no prueban wiring | **PARCIAL** | Se agregaron I22-I28; I23/I25/I26 ejecutan pruebas reales. I22/I24/I27/I28 siguen siendo verificaciones estaticas de subcadenas. No hay gate para el flash real ni para la codificacion de la UI. |
| o-02 colores de marca | **RESUELTO** | `#303846`/`#0856FF`; no quedan `#0f172a`/`#2563eb` en `Pages/Install`. |
| o-03 `/install/complete` publico | **RESUELTO** | Ruta, controlador y `Complete.vue` eliminados; no hay pagina publica con metadatos. |
| o-04 trazabilidad de evidencia | **PARCIAL** | `INSTALL-001-NOTES-20261117.md:61` cita `...204146.md`, no el ultimo (204620/210109). Ademas `GUIA-HTML-ESTADO.md:91` afirma que el aviso de cron se muestra en `/setup`, hoy falso (B-04). |
| o-05 config:clear | **RESUELTO/documentado** | `INSTALL-001-NOTES-20261117.md:65-66` lo documenta como paso manual; no se ejecuto. |

## 3. Hallazgos nuevos

### BLOQUEANTES

**B-04 - El exito no muestra la linea de cron en `/setup` con la configuracion por defecto.**
- El instalador usa una sesion file dedicada: `config/install.php:37-40` (`storage/framework/install-sessions`) y `UseInstallStores.php:68-69` cambia `session.driver`/`session.files` solo para `install.*`.
- El exito escribe el flash en esa sesion: `InstallController.php:204-214` (`->with('install_notice', ...)`).
- `/setup` lee el flash desde la sesion normal de la app: `SetupController.php:94`.
- La app por defecto usa `SESSION_DRIVER=database` (`config/session.php:21`, `.env.example:30`) con `session.files=storage/framework/sessions` (`config/session.php:63`), distinto path/tienda que la sesion del instalador. Aun con driver `file`, el path es distinto.
- Sonda real de dos procesos (sin tocar `.env` real): proceso 1 ejecuta el flujo completo y obtiene `notice_in_install_file_session=yes`, `cron=php artisan schedule:run`, redirect `http://localhost/setup`; proceso 2, con la cookie y `SESSION_DRIVER=database`, obtiene `setup_status=200`, `session.driver=database`, `"install_notice":null` y el body **no** contiene `php artisan schedule:run` (`db_sessions_rows=1`).
- Impacto: el entregable 1 y el criterio de M-02 piden "estado final y una unica linea de cron" tras exito; en la configuracion por defecto el operador no ve esa linea al continuar a `/setup`. Las notas ya reconocen el riesgo (`INSTALL-001-NOTES-20261117.md:69-71`), pero no se corrigio.
- Prueba que lo oculta: `InstallRunTest::test_instalacion_exitosa...` usa sesion `array` en el mismo proceso; `UseInstallStores` deja `session.driver=array` para el GET `/setup` del test, por lo que el flash si aparece en el test y no en produccion.

**B-05 - La UI nueva de `/install` esta doblemente codificada (mojibake); el texto visible no es es-CL.**
- `resources/js/Pages/Install/Wizard.vue`: 47 caracteres doble-codificados en 32 lineas; primeras lineas 7, 12, 15, 19, 21, 23, 24, 45, 73, 108, 110, 112. Ejemplo real: la linea 7 contiene los code points U+00C3 U+00B3 (bytes UTF-8 C3 83 C2 B3), que el navegador muestra como `InstalaciÃ³n tÃ©cnica`.
- `resources/js/Pages/Install/Unavailable.vue`: 8 caracteres doble-codificados en 6 lineas; lineas 7, 17, 22, 23, 24, 25.
- El asset compilado lo conserva: `public/build/assets/app-DP5OXCXs.js` contiene 22 secuencias doble-codificadas de `o` con acento y 8 de `e` con acento (bytes C3 83 C2 B3 / C3 83 C2 A9). `npm run build` pasa, pero el texto de produccion queda corrupto.
- Impacto: incumple el entregable 1 ("pagina ... es-CL") y la regla de no brand drift; la pantalla principal del instalador se ve con texto corrupto. No hay gate de codificacion; `typecheck`/`build` no lo detectan.
- Nota: `resources/js/Pages/Setup/Wizard.vue` no tiene mojibake (0 marcadores); el defecto es de los dos archivos nuevos de Install.

### MAYORES

**M-05 - 500 antes del preflight si no se pueden crear las tiendas file del instalador.**
- `UseInstallStores.php:50-57` ejecuta `File::ensureDirectoryExists()` para sesion/cache/lock antes de `StartSession`.
- Si `storage/framework` no es escribible (justo el fallo que el preflight debe reportar), el middleware lanza `ErrorException: mkdir(): No such file or directory`; GET `/install` responde 500 y nunca se muestra el preflight ni el estado controlado.
- Sonda con `install.session.files`/`install.cache.path` bajo un archivo existente: `GET /install STATUS=500`; middleware aislado: `THROWN=ErrorException: mkdir(): No such file or directory`.
- Tambien ocurre con `INSTALL_ENABLED=false` (el middleware corre antes del 404): sonda con `DISABLED_STATUS=500`, por lo que el "404 aun con BD caida" no se cumple en ese escenario de storage.
- El preflight (`DefaultInstallPreflightChecker.php:86-104`) verifica `storage/framework`, pero es inalcanzable si el middleware ya fallo. No hay test de storage no escribible.

### MENORES

**m-06 - `DB_URL`/`DB_SOCKET` preexistentes pueden anular el destino escrito.**
- `AtomicEnvSecretWriter.php:29-39` escribe `DB_CONNECTION/HOST/PORT/DATABASE/USERNAME/PASSWORD`, pero no neutraliza `DB_URL` ni `DB_SOCKET`.
- `config/database.php:37,49,...` lee `env('DB_URL')` y las conexiones `mysql`/`mariadb` leen `DB_SOCKET`; si el `.env` preexistente los define, el siguiente request (`/setup`) puede usar otra BD/socket aunque el instalador haya migrado la BD elegida. `.env.example` no los define, por lo que es un caso de configuracion heredada.

**m-07 - Foco al primer error incompleto.**
- `Wizard.vue:96-106` solo renderiza el recuadro con foco para errores `install`/`db`/`preflight`; `focusWizardErrors()` (`:495-497`) no mueve el foco si el error es de campo (`host` linea 175, `port` 197, `database` 217, `username` 237). El requisito "foco al primer error" queda parcial.

**m-08 - Deteccion de valor vacio no cubre comentario en la misma linea.**
- `AtomicEnvSecretWriter.php:143-154`: una linea `APP_KEY= # comentario` o `APP_KEY="" # comentario` se considera no vacia y se preserva; phpdotenv la interpreta como vacia, dejando la app sin clave. Caso borde no cubierto por pruebas.

**m-09 - El error del bootstrap puede quedar invisible tras el error.**
- `Wizard.vue:484-493` usa `preserveState:false` y `useForm` no inicializa `errors` desde los props de la pagina; al remontarse el componente, el mensaje de `bootstrap_token` puede no mostrarse y el `ref` de foco pertenece al componente anterior. El token se limpia (bien), pero se pierde la retroalimentacion de error.

**m-10 - Pruebas y gates con puntos ciegos.**
- `tests/Feature/Install/InstallStateTest.php:98-104` ("estado instalado no permite ejecutar migraciones") solo prueba un POST de preflight con token invalido (419); no ejerce el guard de estado instalado en el flujo real.
- `InstallRunTest` valida el flash con sesion `array`; no reproduce B-04.
- `InstallDestinationStateTest` usa el configurador fake con `onConfigure`; no ejercita el `RuntimeDatabaseConfigurator` real (la sonda independiente si lo hizo).
- I22/I24/I27/I28 son subcadenas/archivos, no comportamiento; no hay gate para mojibake, storage no escribible ni flash real.

### OBSERVACIONES

**o-06 - `InstallSecretWriter::persist()` devuelve `lines` con el contenido completo del `.env`.**
- `AtomicEnvSecretWriter.php:136-140` retorna `lines` ademas de `preserved`/`written`; la interfaz solo documenta `preserved`/`written` (`Contracts/InstallSecretWriter.php:17-21`). Hoy no se registra ni se expone, pero un `var_dump`/log futuro podria volcar secretos existentes. Conviene devolver solo metadatos.

**o-07 - `UseInstallStores` no restaura la configuracion en el mismo proceso.**
- `UseInstallStores.php:59-74` deja `session.driver=file`/`cache.default=install` para el resto del proceso. En PHP-FPM por request no afecta; en pruebas y en entornos long-running (Octane) puede filtrar tiendas del instalador a requests posteriores. La prueba de `/setup` post-install pasa justamente por este efecto.

**o-08 - `show()` sigue evaluando estado en la conexion default.**
- `InstallController.php:39` llama `state()` antes de que exista destino elegido. Es conservador y no sustituye la comprobacion decisiva (perform usa el destino), pero si el `.env` apunta a una BD ya instalada, la pagina bloquea 423 y no ofrece camino UI para instalar en otro destino. Registrar como decision de diseno, no como fallo de la garantia de no reinstalacion.

**o-09 - El `.env` se escribe antes de migrar/sembrar.**
- `InstallService.php:293-297`: si migrate/seed/registro falla, el `.env` queda con la BD/claves nuevas y sin `installation_info` (sonda C2). Es coherente con "no revertir automaticamente" y "estado recuperable", pero debe quedar explicito para el operador; las notas lo mencionan parcialmente.

**o-10 - Evidencia documental desactualizada/incoherente.**
- `INSTALL-001-NOTES-20261117.md:61` cita `...204146.md`; el ultimo reporte entregado era `...204620.md` y esta revision genero `...210109.md`.
- `GUIA-HTML-ESTADO.md:91` afirma que el aviso de cron se muestra en `/setup`; con B-04 no ocurre en la configuracion por defecto.

**o-11 - Evidencia de hard start guard/CI no incluida.**
- El packet exige confirmar GitHub Actions 2/2 SUCCESS en el HEAD base exacto antes de implementar. Las notas no registran ese dato y esta QA no ejecuto `gh`; no se verifico CI. No afecta el veredicto de codigo, pero es una laguna de trazabilidad.

## 4. Comandos y resultados reales

| Comando / prueba | Resultado |
|---|---|
| `php artisan test tests/Feature/Install` | **PASS** - 68 tests, 735 assertions, 10.39 s |
| `php artisan test tests/Feature/Setup` | **PASS** - 51 tests, 468 assertions, 18.90 s |
| `php artisan test tests/Feature/Auth` | **PASS** - 45 tests, 108 assertions, 5.47 s |
| `php artisan test` | **PASS** - 597 tests, 5400 assertions, 120.79 s |
| `npm run typecheck` | **PASS** - exit 0 |
| `npm run build` | **PASS** - 808 modules, `app-DP5OXCXs.js` 435.37 kB |
| `py -3 scripts/check_contracts.py` | **PASS** - `OK DOC-001: 362 referencias, 6 hashes...` |
| `py -3 scripts/sf_verify.py INSTALL-001` | **GATES_PASS 28/28** - reporte `docs/work/INSTALL-001-VERIFY-20261117-210109.md` |
| `git diff --quiet HEAD -- <protegidos>` | **PASS** - exit 0; sin cambios en `database/`, `docs/product`, `AGENTS.md`, `opencode.json`, `CONSTITUTION`, `docs/branding`, HTML editorial, roadmap ni `CONTEXTO-CHATGPT.md` |
| `git status --porcelain -- database ...` | **VACIO** |
| `git diff --check HEAD` | **PASS** - exit 0 (solo warnings LF/CRLF de Git) |

### Sondas independientes (bootstrap/app real; sin `.env` real modificado)

| Sonda | Resultado |
|---|---|
| DI real | Contenedor resuelve `InstallService` + 5 interfaces; GET `/install` 200; sin `BindingResolutionException`. |
| Orden de middleware real | `UseInstallStores > EncryptCookies > ... > StartSession`; `UseInstallStores@0`, `StartSession@3`. |
| `/install` con `SESSION_DRIVER=database`, `CACHE_STORE=database`, BD inexistente | `STATUS=200`, `DATABASE_UNAVAILABLE=yes`; session file/cache `install` configurados. |
| `/login` y `/setup` con drivers database | `/login` 200, `session.driver=database`, `cache.default=database`; `/setup` 503 controlado (esquema incompleto), sin cambio de drivers. |
| Instalador deshabilitado, BD caida | `/install` 404; `/install/bootstrap` 404. |
| B-03 destino instalado (configurador y writer reales) | `blocked=true`, `migrate=0`, `seed=0`, `.env` sin cambios; fila del destino intacta. |
| B-03 revalidacion post-migrate | Destino virgen con users creados por el migrador simulado: `ok=false`, `installation_info=0` (rechazo dentro del lock). |
| B-03 exito | `ok=true`, UUID insertado, `state_after=ALREADY_INSTALLED`; `APP_KEY` existente preservada, claves vacias completadas. |
| Escritor `.env` | `preserved=["APP_KEY"]`; `DB_PASSWORD` con `$`/`${}`/comillas/backslash se lee literal con phpdotenv (`DB_PASSWORD_EQ=YES`). |
| Flash post-exito con sesion database | Proceso 1: run 302 a `/setup`, notice en sesion file del instalador. Proceso 2: `/setup` 200, `install_notice:null`, **sin** `php artisan schedule:run`. |
| Storage del instalador no escribible | `GET /install STATUS=500`; con `INSTALL_ENABLED=false` tambien `DISABLED_STATUS=500`; middleware aislado lanza `ErrorException: mkdir(): No such file or directory`. |
| Mojibake UI | `Wizard.vue`: 47 code points doble-codificados en 32 lineas; `Unavailable.vue`: 8 en 6 lineas; asset compilado conserva 22+8 secuencias doble-codificadas. `Setup/Wizard.vue`: 0. |

## 5. Resultados no ejecutados / limites

- `real_installer=NOT_RUN`
- `maria=NOT_RUN`
- `remote_db=UNTOUCHED`
- `env_real=UNTOUCHED` (no modificado; el framework cargo el entorno en las sondas, pero no se copiaron valores a este informe)
- `cron=NOT_INSTALLED`
- `deploy=NOT_RUN`
- `config_clear=NOT_RUN`
- `safe_git_pull=NOT_RUN` (HEAD ya coincidia; habia cambios locales y no se descarto nada)
- `CI=NOT_VERIFIED` (no se ejecuto `gh`; el packet exige evidencia 2/2 en el HEAD base y no esta registrada en las notas)
- `tests/Feature/Install/InstallSecretsTest.php=NOT_READ`: la politica de entorno deny `*secrets*` impide abrirlo; se reviso indirectamente por nombres de pruebas, reporte de gates y una sonda independiente del escritor.
- No se declaro `VERIFIED`; no se ejecutaron migraciones ni semillas reales; no se modifico codigo fuente (solo se agrego este informe y el reporte de gates generado por `sf_verify`).

## 6. Veredicto

**`RECHAZADO`** - Los tres bloqueantes previos B-01/B-02/B-03 estan resueltos y M-01/M-03/m-01/m-02/m-03/o-02/o-03/o-05 estan corregidos, pero:

- **B-04**: el aviso de cron posterior al exito se pierde en la configuracion por defecto (`SESSION_DRIVER=database` + sesion file dedicada del instalador); `/setup` muestra `install_notice:null` y no muestra `php artisan schedule:run`. Incumple M-02/entregable 1; es bloqueante de aceptacion.
- **B-05**: `Install/Wizard.vue` e `Install/Unavailable.vue` estan doblemente codificados (mojibake) y el asset compilado conserva el texto corrupto; la UI no es es-CL. Incumple el entregable 1 y brand/no-drift; es bloqueante de aceptacion.
- **M-05**: si las tiendas file del instalador no se pueden crear, `/install` responde 500 antes del preflight; el preflight de permisos queda inalcanzable. Es mayor y debe corregirse.
- Se suman m-06..m-10 y observaciones o-06..o-11.

Corregir B-04/B-05/M-05, agregar pruebas que reproduzcan el flash con tiendas distintas, la codificacion de la UI y el storage no escribible, re-ejecutar gates/suite/CI y solicitar nueva revision independiente. `VERIFIED` solo puede decidirlo ChatGPT con gates, QA y CI sobre el SHA publicado.