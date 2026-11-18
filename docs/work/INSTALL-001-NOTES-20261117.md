# INSTALL-001 — Notas técnicas de implementación (2026-11-17, ciclo R2)

Feature: `docs/work/features/INSTALL-001.md` — Instalador web CORE-17 P0.
Modo: `ONE_SMALL_WAVE`, `AUTO_GIT`, `new_business_decisions: NONE`.
Base HEAD: `66b057bce6a936ae4e140e1edb9bbc3651e10a29`.
Ciclo correctivo posterior a `docs/work/INSTALL-001-REVIEW-20261117-R2.md`.
CI base en ese HEAD: **2/2 SUCCESS ya verificado** antes de implementar (hard start
guard del packet); esta sesión no ejecutó `gh` ni desplegó.

## Correcciones R2

- **B-04 — cron determinista en `/setup`.** Se eliminó el flash entre tiendas: el
  éxito redirige a `setup.show` sin `with('install_notice')`. `SetupController::show`
  calcula `install_notice` desde estado real (`installation_info` con fila,
  `is_setup_completed` aún inactivo y esquema listo) y expone
  `{completed, cron_command, app_version, release_channel}`. Tolera errores de BD
  devolviendo `null`. `Setup/Wizard.vue` muestra `cron_command`.
  Prueba: `InstallSetupNoticeTest` usa `RuntimeDatabaseConfigurator` real sobre SQLite
  archivo, sesión `database` y sesión del instalador ignorada; `/setup` contiene
  `php artisan schedule:run`.
- **B-05 — codificación.** `Install/Wizard.vue` y `Install/Unavailable.vue` fueron
  reescritos en UTF-8 correcto. Gate I29 revisa `.vue`, PHP de Install y tests, y el
  asset compilado (`public/build/assets/*.js`) contra `Ã`, `Â`, `â€`, `Ã³`, `Ã©`, `Ã±`.
- **M-05 — storage no escribible.** `UseInstallStores` envuelve la creación de tiendas
  file en `try/catch`; si falla usa `session.driver=array` y `cache` array, marca
  `install.runtime_storage_unavailable=true` y el preflight reporta el chequeo
  `writable` como fallo. Deshabilitado sigue 404. Prueba:
  `InstallStorageFailureTest` (200 con aviso/preflight fallido y 404 deshabilitado).
- **m-06.** `AtomicEnvSecretWriter` neutraliza `DB_URL` y `DB_SOCKET` heredados.
- **m-07.** El foco va al primer campo con error (`host`, `port`, etc.), no solo al
  resumen.
- **m-08.** `APP_KEY= # comentario` y `APP_KEY="" # comentario` se consideran vacías y
  se completan.
- **m-09.** El bootstrap usa `preserveState: true` y limpia solo el token; el error
  visible se conserva.
- **m-10.** Guard de estado instalado ejercido en el flujo real de run
  (`InstallStateTest`), cron con drivers database (`InstallSetupNoticeTest`), destino
  con `RuntimeDatabaseConfigurator` real (`InstallDestinationStateTest`) y gates
  conductuales I29–I32.
- **o-06.** `InstallSecretWriter::persist()` devuelve solo `preserved`/`written`, sin
  `lines`.
- **o-07.** `UseInstallStores` restaura la configuración de tiendas en `finally` para
  no filtrarla a requests posteriores; no rompe `/setup` ni `/login`.
- **o-08.** `show()` conserva la comprobación conservadora contra la conexión actual;
  la garantía decisiva de no reinstalación sigue en `perform()` sobre el destino.
- **o-09.** El `.env` se escribe antes de migrar/sembrar; si algo falla no se revierte
  automáticamente y queda estado recuperable. Documentado aquí para el operador.
- **o-10/o-11.** Este archivo referencia el reporte final de gates y registra la
  evidencia CI base.

## Evidencia

- `php artisan test tests/Feature/Install`: **73 pruebas / 939 aserciones PASS**.
- `php artisan test` completo: **602 pruebas / 5604 aserciones PASS**.
- `npm run typecheck`: PASS.
- `npm run build`: PASS; asset compilado sin mojibake (gate I29).
- `py -3 scripts/check_contracts.py`: PASS.
- `py -3 scripts/sf_verify.py INSTALL-001`: **GATES_PASS 32/32**, reporte
  `docs/work/INSTALL-001-VERIFY-20261117-212318.md`.
- CSRF conductual: POST sin token → **419** (`InstallBootstrapSecurityTest`).

## Operación y límites reales

- `config:clear` es un paso operativo manual si el despliegue usa `config:cache`; no se
  ejecutó.
- Tras instalar, deshabilitar `INSTALL_ENABLED` y rotar/retirar el token bootstrap. El
  estado `ALREADY_INSTALLED` bloquea reinstalación aunque el token siga presente.
- El aviso de cron en `/setup` ya no depende de flash; se calcula desde estado real.
- `real_installer=NOT_RUN`
- `maria=NOT_RUN`
- `remote_db=UNTOUCHED`
- `env_real=UNTOUCHED`
- `cron=NOT_INSTALLED`
- `deploy=NOT_RUN`
- `config_clear=NOT_RUN`

## Restricciones respetadas

- Sin cambios en `database/migrations`, `database/seeders`, HTML editorial,
  `resources/roadmap/index.html`, `docs/harness/CONTEXTO-CHATGPT.md`, contratos v2.2,
  `AGENTS.md` ni dependencias nuevas.
- Sin commit/push/deploy; sin ejecutar el instalador real ni migraciones/semillas reales.
- `NOT_READY_FOR_PRODUCTION`/BKP-08 intacto. No se declara VERIFIED.
