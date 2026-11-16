# CORELIC-001 — Notas técnicas (2026-11-16)

Feature: **CORE-08 / RN-67–70** — contrato local `LicenseValidator`, stub de simulación, resolver de solo lectura y banner de estado en Dashboard.

- Packet: `docs/work/features/CORELIC-001.md` (`status: APPROVED`, `AUTO_GIT`, `ONE_SMALL_WAVE`).
- HEAD base: `606fe6d155f400df4e68a9454cd7f17c436e71cb`.
- Sin decisiones comerciales nuevas (`new_business_decisions: NONE`).

## Alcance implementado

1. **Contrato tipado** `App\Contracts\LicenseValidator` con los métodos exactos del contrato:
   `isValid()`, `getLicenseInfo()`, `getLimits()`, `hasModule(string $module)`,
   `getDaysRemaining()`, `reportStatus(InstallationStatusReport)`.
2. **Estructura mínima** `App\Support\License\InstallationStatusReport` con metadatos técnicos
   no sensibles (`app_version`, `schema_version`, `active_modules`, canal, última actualización y
   `extensions` reservado). No incluye clientes, ventas, RUT, documentos, `installation_key` ni
   información comercial.
3. **Stub local explícito** `App\Support\License\LocalLicenseStub`:
   - límites ilimitados con la semántica contractual `0 = ilimitado`;
   - módulos técnicos disponibles solo en simulación;
   - `isValid()` y `getLicenseInfo()` marcados explícitamente como simulación;
   - `reportStatus()` es un no-op;
   - el constructor rechaza instanciarse si `app.env = production`.
4. **Resolver de solo lectura** `App\Support\License\LicenseStateResolver` que lee una única fila de
   `license_states` seleccionando **solo** `status`, `expires_at`, `grace_until`. No crea ni modifica
   filas, no expone secretos ni límites.
5. **Banner de Dashboard** vía `App\Http\Controllers\DashboardController` y prop sanitizada
   `license_notice` (`type`, `severity`, `message`, `date`, `date_label`). `Dashboard.vue` renderiza un
   banner accesible con clases `sf-*`; `active` no muestra banner.
6. **Regla pura centralizada** `App\Support\License\LicenseReadOnlyRule`, reutilizada por
   CFG-001/002/003, BranchController y UserAccessManager sin cambiar mensajes, transacciones,
   `lockForUpdate` ni permisos.
7. **Gates declarativos** `CORELIC-001` en `scripts/sf_verify.py`.
8. **Pruebas** `tests/Feature/Core/LicenseValidatorTest.php` y
   `tests/Feature/Core/DashboardLicenseNoticeTest.php`.

## Clasificación RN-70 aplicada

| Estado local | Solo lectura | Banner |
|---|---|---|
| Sin fila | No (los guards existentes siguen bloqueando altas) | `unconfigured` (warning) |
| `active` | No | Ninguno, aunque `expires_at` esté vencido |
| `expiring_soon` | No | `expiring_soon` (warning, fecha de término si existe) |
| `suspended` + gracia futura (fin de día) | No | `suspended_grace` (warning, fecha de gracia) |
| `suspended` + gracia vencida | Sí | `suspended_read_only` (danger, fecha de gracia si existe) |
| `suspended` sin gracia | Sí | `suspended_read_only` (danger, sin fecha) |

No se infiere suspensión desde `expires_at` cuando `status` no es `suspended`.

## Decisión sobre centralización de guards

Se centralizó **solo la regla booleana** de solo lectura (`LicenseReadOnlyRule`), no el flujo de
guardas. Los cuatro puntos existentes conservan:

- sus mensajes de validación y claves de error;
- sus transacciones y `lockForUpdate`;
- sus verificaciones de permisos y de fila ausente;
- la diferencia entre «sin fila» (bloqueo de altas) y «suspendido fuera de gracia» (solo lectura).

El cambio es de bajo riesgo y está cubierto por las suites Settings, Branches, Users y Auth.

## Límites explícitos

- **Sin Control central**: no hay cliente, endpoint, heartbeat ni cron.
- **Sin firmas**: no se generan ni validan claves o firmas criptográficas.
- **Sin tráfico saliente**: el stub y el resolver no realizan solicitudes; se prueba con
  `Http::fake()` + `Http::assertNothingSent()`.
- **Sin migraciones ni cambios de esquema**: se usa `license_states` ya existente.
- **Stub no comercial**: no sustituye `license_states`, no concede derechos comerciales y no
  convierte una licencia real inválida en válida. Tampoco actúa como bypass de `PlanGuard`,
  RN-67 o RN-70.
- **Sin pantalla de administración** de licencias: el banner es informativo y de solo lectura.
- Sin dependencias nuevas, sin `.env`, sin BD remota, sin deploy, sin commit/push.

## Evidencia de no tráfico saliente

`LicenseValidatorTest::test_stub_reporta_estado_sin_trafico_saliente` y
`test_resolver_y_stub_no_realizan_solicitudes_salientes` usan `Http::fake()` y
`Http::assertNothingSent()` después de invocar todos los métodos del stub y del resolver.

## Pruebas ejecutadas

- `php artisan test tests/Feature/Core` — contrato, stub, resolver, regla, banner y ausencia de secretos.
- Regresión: `tests/Feature/Settings`, `tests/Feature/Branches`, `tests/Feature/Users`,
  `tests/Feature/Auth` y suite completa.
- `npm run typecheck` y `npm run build`.
- `py -3 scripts/check_contracts.py`.
- `py -3 scripts/sf_verify.py CORELIC-001` — GATES_PASS; reporte en
  `docs/work/CORELIC-001-VERIFY-20261116-160851.md`.

## Documentación de usuario

Introduce texto nuevo de banner en Dashboard; corresponde `GUIA_PENDIENTE_CHATGPT`. Solo se
actualizaron metadatos en `docs/work/GUIA-HTML-ESTADO.md`; no se editó `public/docs/index.html`,
`resources/roadmap/index.html` ni `docs/harness/CONTEXTO-CHATGPT.md`. No hay cambio de roadmap
editorial que OpenCode deba redactar.
