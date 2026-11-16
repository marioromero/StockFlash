# CORELIC-001 — Revisión QA independiente (2026-11-16)

- **Revisor:** QA independiente (`deepseek-flash`, agente reviewer). No participé en la implementación.
- **Feature:** CORELIC-001 — CORE-08 / RN-67–70.
- **Estado revisado:** árbol de trabajo **sin commit**, sobre HEAD `606fe6d155f400df4e68a9454cd7f17c436e71cb`.
- **Dictamen:** **APROBADO — 0 bloqueantes.**
- **Nota:** este documento es evidencia de revisión; no es un dictamen `VERIFIED` ni autoriza despliegue.

## 1. Estado exacto revisado

`git status --porcelain` (árbol sucio, sin staged):

```text
 M app/Http/Controllers/BranchController.php
 M app/Http/Controllers/Settings/BusinessSettingsController.php
 M app/Http/Controllers/Settings/GeneralSettingsController.php
 M app/Http/Controllers/Settings/ReceiptSettingsController.php
 M app/Support/UserAccessManager.php
 M docs/work/GUIA-HTML-ESTADO.md
 M resources/js/Pages/Dashboard.vue
 M routes/web.php
 M scripts/sf_verify.py
?? app/Contracts/
?? app/Http/Controllers/DashboardController.php
?? app/Support/License/
?? docs/work/CORELIC-001-NOTES-20261116.md
?? docs/work/CORELIC-001-VERIFY-20261116-160607.md
?? docs/work/CORELIC-001-VERIFY-20261116-160851.md
?? docs/work/CORELIC-001-VERIFY-20261116-161146.md
?? docs/work/CORELIC-001-VERIFY-20261116-161401.md
?? docs/work/CORELIC-001-VERIFY-20261116-161706.md
?? tests/Feature/Core/DashboardLicenseNoticeTest.php
?? tests/Feature/Core/LicenseValidatorTest.php
```

Los archivos sin seguimiento se leyeron directamente (no aparecen en `git diff HEAD`). El reporte `CORELIC-001-VERIFY-20261116-161706.md` fue generado por la ejecución de gates durante esta revisión; no es una edición manual.

### Archivos e hashes SHA-256 del estado revisado

| Archivo | SHA-256 |
|---|---|
| `app/Http/Controllers/BranchController.php` | `b518ee99eb04bb9fafbd1be54d2108f9a2437f8805da8d6c51fb5c93ba85ff95` |
| `app/Http/Controllers/Settings/BusinessSettingsController.php` | `ccc26499323ca082026407236dc9fa032977b413b22493d84691bd089b4aa9ca` |
| `app/Http/Controllers/Settings/GeneralSettingsController.php` | `17b21bb6e82e46598cdaac44e849fb3d372ba7d08792d1140eaf536a16797268` |
| `app/Http/Controllers/Settings/ReceiptSettingsController.php` | `3a8e995ac70aeea89f08f9a9eab646183cc9944d77606179432dedc0a949c401` |
| `app/Support/UserAccessManager.php` | `7e0449beef45f4923479baeb273ce23c7bc710eb61e7fdf481ba4a162bf5cd3c` |
| `docs/work/GUIA-HTML-ESTADO.md` | `bd911858e7680901ea3e572e0881aab6de8a6e076480731893b31fe9408b40ec` |
| `resources/js/Pages/Dashboard.vue` | `a3f7142558aaf10a1b3915315a64b0f497d7419ce0b5a98726ef8639921799d3` |
| `routes/web.php` | `bdcdaa407c2e57fc9fa20ff66c0078b72218620420845243a598b2deb8b23278` |
| `scripts/sf_verify.py` | `05ba68ffca8636ae66f58a83886b91d5f39f2eb05fa20474a327f832afdf797c` |
| `app/Contracts/LicenseValidator.php` | `b0415b4f060a5e28e460f3afa575caeee8445d9b76ca2c416151348364154d57` |
| `app/Http/Controllers/DashboardController.php` | `b4b292224ad465d6265d3c64ebf35b0f7adac42881b262bb724164a52df4c2bd` |
| `app/Support/License/InstallationStatusReport.php` | `10d3195ad79944c9650a1ca1e6870c0819bb3dd6d42d8ecf2b1fba3d80bca2bc` |
| `app/Support/License/LicenseReadOnlyRule.php` | `8362c8e16d009412c5b777e7cec7a6dae896e22b30dc102297262c50f355048f` |
| `app/Support/License/LicenseSnapshot.php` | `61f939f8336f3fedebfd67d6b336d4beeeb7207ad66b1fe151eb65ca2eed414c` |
| `app/Support/License/LicenseStateResolver.php` | `60a646300e66ceff3d8802145f5813b6b01136f9789deb302bbcdf176f726439` |
| `app/Support/License/LocalLicenseStub.php` | `754274c2372a9a7cb22d982db30e4446aa4bf3b00ed3e8345abe790ff4d48ee7` |
| `tests/Feature/Core/LicenseValidatorTest.php` | `6e0a338062c4d5adf2a9abd89c391eec05551dfb83be57462370664ecce588e4` |
| `tests/Feature/Core/DashboardLicenseNoticeTest.php` | `5c4edc5cc80ac0e98d8ac397d5e634d2ad359061a712b449425f935dddb5f7f4` |
| `docs/work/CORELIC-001-NOTES-20261116.md` | `b45d7a15fd9f67bd416458d90cd9d487efece7c96323bdd0f18db2f1b9fb08b8` |

Resumen de diff rastreado: 9 archivos, `+141 / -38`; `scripts/sf_verify.py` solo agrega 53 líneas y no elimina líneas existentes.

## 2. Alcance verificado

1. Contrato `App\Contracts\LicenseValidator` con los seis métodos exactos y `InstallationStatusReport`.
2. Stub `LocalLicenseStub`: límites ilimitados solo simulación, sin HTTP/cron/heartbeat/firmas/tokens, bloqueo de instanciación en producción, sin sustituir `license_states`.
3. `LicenseStateResolver` / `LicenseSnapshot`: lectura de solo `status`, `expires_at`, `grace_until`; RN-70; sin inferir suspensión por `expires_at`; sin secretos ni límites.
4. `LicenseReadOnlyRule` centralizada y usada por Branch, CFG-001/002/003 y Users sin alterar mensajes, transacciones, `lockForUpdate` ni permisos.
5. Banner de Dashboard solo autenticado, estados `active`/`expiring_soon`/`suspended`/sin fila, fechas condicionadas, accesibilidad y sanitización de props.
6. Pruebas de contrato, estados, fechas, secretos, anónimo y no tráfico saliente.
7. Gates declarativos `CORELIC-001` y documentación de cierre/handoff.

## 3. Hallazgos por severidad

### Bloqueantes

Ninguno.

### Mayores

Ninguno.

### Menores

- **M1 — Referencia a reporte de gates anterior en las notas.** `docs/work/CORELIC-001-NOTES-20261116.md` (línea 89) apunta a `CORELIC-001-VERIFY-20261116-160851.md`, mientras el reporte declarado como vigente para esta revisión es `...-161401.md` y la ejecución QA produjo `...-161706.md`. El reporte referenciado existe y también es `GATES_PASS`, por lo que no hay impacto funcional; conviene actualizar el puntero antes del handoff público.
- **M2 — El gate `CL5-protegidos-intactos` usa `git diff` y no detecta archivos nuevos sin seguimiento dentro de rutas protegidas.** En el estado actual no existe ningún archivo untracked en `docs/product`, `docs/branding`, `docs/harness/CONTEXTO-CHATGPT.md`, `resources/roadmap` ni `public/docs`, y la revisión directa de `git status` lo confirma. Es una observación de robustez futura, no un defecto de esta entrega.

### Observaciones

- **O1 — `getDaysRemaining()` solo se verifica como `null` en el stub.** Es coherente con CORE-08: no se implementa un validador real de vencimiento en esta fase y el contrato queda declarado para LIC-02–LIC-05. No es un hueco bloqueante; no hay lógica de días restantes que probar todavía.
- **O2 — El stub devuelve `isValid() = true` y `status = active` como simulación.** Está mitigado por `is_simulated`, el constructor que rechaza producción y la ausencia de binding en `AppServiceProvider`; no se usa en guards reales. Riesgo residual bajo si una fase futura lo inyecta sin revisar el contexto de simulación.
- **O3 — Se acumulan cinco reportes `CORELIC-001-VERIFY-*` sin seguimiento.** El publisher debe elegir explícitamente cuáles versionar; no afecta al código ni a los gates.
- **O4 — `DashboardLicenseNoticeTest` ejecuta `artisan migrate` dentro de `RefreshDatabase`.** Es redundante pero inofensivo; las suites pasan. No se exige refactor cosmético.
- **O5 — Clase CSS `sf-license-banner--info` no utilizada actualmente.** Cosmético; no afecta accesibilidad ni build.

### Revisión de marca y seguridad

- **Marca:** el banner usa colores de estado ámbar/rojo/azul independientes de los acentos de módulo, mantiene la identidad textual `StockFlash` existente y no introduce logos ni PNG falsos. PASS para el cambio UI revisado.
- **Seguridad/privacidad:** la prop `license_notice` contiene solo `type`, `severity`, `message`, `date`, `date_label`. No incluye `installation_key`, `signed_payload`, `signature`, límites ni PII. El mensaje se interpola con Vue (sin HTML crudo). El dashboard exige `auth`, `password.change` y `verify.session`. Las pruebas convierten secretos señuelo y verifican que no aparecen en la respuesta.

## 4. Verificación detallada por punto solicitado

1. **Contrato y stub:** `LicenseValidator` declara `isValid`, `getLicenseInfo`, `getLimits`, `hasModule`, `getDaysRemaining` y `reportStatus(InstallationStatusReport)`. El stub implementa el contrato, usa límites `0 = ilimitado`, no contiene llamadas HTTP ni mutaciones de `license_states`, y lanza `LogicException` si `app.env = production`. `AppServiceProvider` no registra ningún binding del contrato al stub.
2. **Resolver/RN-70:** `LicenseStateResolver::COLUMNS` es exactamente `['status', 'expires_at', 'grace_until']` y se usa en `first(self::COLUMNS)`. `LicenseReadOnlyRule` solo activa solo lectura con `status = suspended` y gracia nula o vencida; `expires_at` no se usa para suspender. `LicenseSnapshot` contiene solo seis campos sanitizados. Sin fila devuelve `unconfigured` y no altera los guards de altas.
3. **Banner:** `routes/web.php` mantiene el nombre `dashboard` y el middleware `auth`, `password.change`, `verify.session`; `php artisan route:list` confirma `DashboardController@index`. `active` devuelve `license_notice = null`; `expiring_soon`, `suspended_grace`, `suspended_read_only` y `unconfigured` devuelven avisos con fecha solo si existe. El banner usa `role="alert"`/`status`, `time datetime` y estilos `sf-*`.
4. **Centralización:** el diff reemplaza únicamente el booleano de gracia vencida en Branch, CFG-001/002/003 y Users. Los mensajes y claves de error se conservan; los `lockForUpdate` y transacciones no se tocan; la diferencia entre «sin fila» y «suspensión» se mantiene. Las suites de Settings, Branches, Users y Auth pasan.
5. **Pruebas:** `LicenseValidatorTest` cubre contrato, límites, simulación, producción, no mutación de fila, ausencia de fila, `active` con vencimiento, `expiring_soon`, gracia futura/mismo día/vencida, suspensión sin gracia, ausencia de secretos y `Http::fake()` + `Http::assertNothingSent()`. `DashboardLicenseNoticeTest` cubre anónimo, `active`, `expiring_soon`, gracia futura, fuera de gracia, sin gracia, sin fila, no secretos y permisos existentes. Los huecos detectados son de alcance futuro (días restantes reales), no defectos de CORE-08.
6. **Gates:** `py -3 scripts/sf_verify.py CORELIC-001` ejecutado sobre este estado exacto: **GATES_PASS 17/17**. El cambio en `scripts/sf_verify.py` es solo aditivo (53 líneas, 0 eliminadas); no relaja gates previos ni crea scripts nuevos.
7. **Documentación:** `CORELIC-001-NOTES-20261116.md` describe alcance y límites; `GUIA-HTML-ESTADO.md` registra `pending_docs: CORELIC-001 (GUIA_PENDIENTE_CHATGPT)`. No se modificaron `public/docs/index.html`, `resources/roadmap/index.html`, `docs/work/ROADMAP-HTML-ESTADO.md`, `docs/harness/CONTEXTO-CHATGPT.md`, `AGENTS.md`, `opencode.json`, `docs/product`, `docs/branding`, `docs/governance/CONSTITUTION.md` ni `database`.

## 5. Evidencia ejecutada

| Comprobación | Resultado |
|---|---|
| `git status --porcelain` | Árbol sucio esperado; sin cambios en rutas protegidas ni en `database`. |
| `git ls-files --others --exclude-standard` | Solo archivos nuevos de la feature, notas, reportes de gates y tests. |
| `git diff --stat` | 9 archivos rastreados, `+141 / -38`. |
| `git diff HEAD -- scripts/sf_verify.py` | `+53 / -0`; sin relajación de gates previos. |
| `py -3 scripts/sf_verify.py CORELIC-001` | `GATES_PASS` 17/17; reporte `docs/work/CORELIC-001-VERIFY-20261116-161706.md`. |
| `php artisan test tests/Feature/Core` | 47 passed (303 assertions). |
| `php artisan test tests/Feature/Settings` | 59 passed (640 assertions). |
| `php artisan test tests/Feature/Branches` | 53 passed (388 assertions). |
| `php artisan test tests/Feature/Users` | 79 passed (564 assertions). |
| `php artisan test tests/Feature/Auth` | 45 passed (108 assertions). |
| `php artisan test` | 308 passed (2145 assertions). |
| `npm run typecheck` | PASS. |
| `npm run build` | PASS. |
| `py -3 scripts/check_contracts.py` | OK: 362 referencias, 6 hashes y reglas contractuales. |
| `php artisan route:list --path=dashboard --json` | `dashboard` → `DashboardController@index`, middleware `web, auth, password.change, verify.session`. |
| `php -l` sobre los siete archivos PHP nuevos | Sin errores de sintaxis. |

### Resultados no ejecutados

- No se ejecutó navegador real, prueba visual E2E ni verificación de contraste con captura; la UI se validó por código, typecheck, build y pruebas Inertia.
- No se ejecutaron migraciones ni pruebas sobre MariaDB real; esta feature no incluye migraciones y usa la tabla existente.
- No hubo deploy, publicación, commit ni push.
- La revisión no declara `VERIFIED`.

## 6. Veredicto

**APROBADO — 0 bloqueantes.**

El estado revisado cumple el contrato acotado de CORE-08/RN-70, preserva los guards y transacciones existentes, no expone secretos, no introduce tráfico saliente ni dependencias, y mantiene intactos los documentos y configuraciones protegidos. Los hallazgos menores no afectan la corrección funcional ni la seguridad de esta entrega.

## 7. Límites de esta revisión

- No modifiqué código de la feature, no ejecuté migraciones, no hice commit/push y no publiqué nada.
- Sí creé este documento de revisión por instrucción expresa del solicitante.
- `scripts/sf_verify.py` generó automáticamente el reporte `CORELIC-001-VERIFY-20261116-161706.md`; es un artefacto de verificación, no una edición manual.
- El dictamen `APROBADO` no equivale a `VERIFIED`; esa decisión corresponde al proceso externo establecido.
