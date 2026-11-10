# USERS-001 — Revisión QA documental del DRAFT — Ronda 2 (2026-11-10)

- Fecha: 2026-11-10
- Revisor: `stockflash-reviewer`
- Base publicada: `11abfb0b72aad4bbe9db1eb552c49173a61266db`
- Rama: `main`
- Objeto revisado: working tree contra la base; `docs/work/features/USERS-001.md` y `docs/work/CURRENT.md`.
- Evidencia histórica excluida del objeto: `docs/work/USERS-001-DRAFT-REVIEW-20261110.md` (ronda 1; veredicto `CAMBIOS NECESARIOS`).
- Estado revisado: USERS-001 en **DRAFT**, sin implementación.
- Modo: solo lectura. No se modificaron archivos, no se ejecutaron migraciones, pruebas, build ni deploy.
- Veredicto QA del DRAFT: **APROBADO**.

## 1. Árbol, base y hashes

Comandos de control ejecutados:

```text
git rev-parse HEAD
11abfb0b72aad4bbe9db1eb552c49173a61266db

git status --porcelain=v1 --untracked-files=all
 M docs/work/CURRENT.md
 M docs/work/features/USERS-001.md
?? docs/work/USERS-001-DRAFT-REVIEW-20261110.md

git ls-files --others --exclude-standard
docs/work/USERS-001-DRAFT-REVIEW-20261110.md

git diff --stat HEAD
 docs/work/CURRENT.md            |  2 +-
 docs/work/features/USERS-001.md | 96 ++++++++++++++++++++++++++++-------------
 2 files changed, 66 insertions(+), 32 deletions(-)
```

Hashes:

| Elemento | Hash |
|---|---|
| HEAD / base | `11abfb0b72aad4bbe9db1eb552c49173a61266db` |
| `docs/work/features/USERS-001.md` | `b08875c7da8b9747c1da5eeadc0f158478ee063a` |
| `docs/work/CURRENT.md` | `44ddf0e044fe2308098a0f851ec57bd14dc41f5e` |
| Ronda 1 (histórica) | `33e8ace36e3f17928cadabf2bbb9fcf946f25f9d` |

El aviso de Git sobre LF/CRLF en `USERS-001.md` no altera el contenido revisado ni los hashes solicitados. El artefacto de esta ronda aún no está guardado, por lo que no tiene hash propio.

## 2. Verificación de los 5 ajustes previos

### 2.1 Último administrador protegido frente a cambios de rol/acceso

**Correcto.**

- La protección cubre estado, rol global, conversión global↔sucursal y reemplazo de asignaciones: `USERS-001.md:12,60-61`.
- Define el conjunto bloqueado: filas de usuarios activos (`users` con `is_active=1`) mediante `lockForUpdate()` antes de contar y actualizar: `USERS-001.md:61,75,109`.
- Define la comprobación: si el objetivo pierde `users.manage`, se cuenta cuántos otros usuarios activos y no eliminados lo conservarían; si son cero, se rechaza sin estado parcial: `USERS-001.md:61`.
- El auto-cambio de rol/acceso tiene EARS propio: `USERS-001.md:98`.
- Precedente de bloqueo de fila de licencia: `app/Http/Controllers/BranchController.php:196-199`.
- Pruebas previstas: `UserLastAdminTest` en `USERS-001.md:115`; gate en `:142,147`.

### 2.2 SEC-05: invalidación de sesiones por contraseña, roles y accesos

**Correcto.**

- Fingerprint por sesión guardado al iniciar sesión y recalculado en `VerifySession`: `USERS-001.md:13,63,110`.
- Cálculo con HMAC-SHA256 (clave de aplicación) o SHA-256 sobre componentes normalizados, nunca por concatenación cruda: `USERS-001.md:63,135`.
- Cubre contraseña, rol global, asignaciones por sucursal, sucursal inicial y estado: `USERS-001.md:63,99,135`.
- No se registra ni se expone: `USERS-001.md:63,135`.
- Fingerprint ausente = sesión invalidada: `USERS-001.md:63,73`.
- Sin migraciones ni cambio de driver: `USERS-001.md:63,110,135`.
- Pruebas previstas: `UserSessionInvalidationTest` en `USERS-001.md:115`; gate en `:143`.

### 2.3 PIN según `system_configs.pin_length`

**Correcto.**

- Exige exactamente `pin_length` dígitos, predeterminado 4, leído en servidor: `USERS-001.md:14,43,54,92,128,144`.
- No fija un rango 4–8; solo lo menciona para negarlo: `USERS-001.md:14,144`.
- No fija el valor en la UI: `USERS-001.md:54`.
- Solo guarda `pin_hash`; se devuelve “configurado/no configurado”: `USERS-001.md:54,92,133`.
- Contrato: Parte 2 línea 217; seeder: `database/seeders/SystemConfigsSeeder.php:29`.

### 2.4 `SoftDeletes` en `User` reutilizando `deleted_at`

**Correcto.**

- El DRAFT exige añadir el trait reutilizando `deleted_at`, sin migraciones: `USERS-001.md:15,62,68,112,136,145`.
- La columna ya existe: `database/migrations/2026_10_16_000009_create_users_table.php:39` (`$table->softDeletes()`).
- El modelo actual no usa el trait: `app/Models/User.php:38-40` (`use Notifiable;` solamente).
- El estado de funciones lo declara correctamente: `USERS-001.md:161`.
- Pruebas de exclusión de eliminados: `USERS-001.md:68,100,145,147`.

### 2.5 `max_users` limita usuarios activos

**Correcto.**

- Cuenta solo `is_active=1` y no eliminados lógicamente: `USERS-001.md:16,64,101,131`.
- `license_states.max_users` está definido como “Usuarios activos”: Parte 2 línea 340; migración `2026_10_16_000004_create_license_states_table.php:20`.
- Bloquea la fila vigente de `license_states` antes de contar: `USERS-001.md:64,109,131`.
- `0` o sin fila = ilimitado: `USERS-001.md:64,131`; coherente con el precedente de `BranchController.php:196-209`.
- Pruebas previstas: `UserLimitTest` en `USERS-001.md:115`; gate en `:147`.

## 3. Cierre de H1–H4 y O1–O2

| Hallazgo | Corrección verificada | Evidencia | Estado |
|---|---|---|---|
| H1 — CORE-03/04/15 citaban `Parte 1 §9.2` | Ahora citan `Parte 1 §8.1` | `USERS-001.md:34-36`; spec Parte 1 líneas 543-555; `INDEX-REQUISITOS.md:91-105` | **Cerrado** |
| H2 — bloqueo transaccional del último administrador y `max_users` | Define filas de usuarios activos bloqueadas, fila de `license_states` bloqueada y EARS de auto-cambio | `USERS-001.md:61,64,75,98,109,134`; `BranchController.php:196-199` | **Cerrado** |
| H3 — fingerprint/hash de contraseña | HMAC-SHA256 o SHA-256 sobre componentes normalizados; no se registra ni expone; se eliminó la expresión “dato no secreto” | `USERS-001.md:63,135` | **Cerrado** |
| H4 — comandos existentes incompletos | Reconoce `admin:create`/`admin:setup` | `USERS-001.md:22`; `AdminSetupCommand.php:13-15` | **Cerrado** |
| O1 — ruta `/usuarios` | Se conserva en este packet; limpieza se evaluará en packet posterior | `USERS-001.md:108`; `routes/web.php:33-35` | **Cerrado** |
| O2 — email de eliminados lógicamente | Validación con `withTrashed` y prueba explícita | `USERS-001.md:52,85,136,147` | **Cerrado** |

No se detectan bloqueantes residuales de la ronda 1. Las observaciones de la sección 8 son de precisión/editoriales y no alteran el alcance aprobable del DRAFT.

## 4. Coherencia de contratos, EARS, plan, decisiones, gates, riesgos y estado de funciones

| Sección | Ubicación | Resultado |
|---|---|---|
| Contratos / casos límite | `USERS-001.md:50-68` | Refleja los 5 ajustes; sin contradicción sustantiva con v2.2, DB-001, AUTH-001 o BRANCH-001. |
| Aceptación EARS | `USERS-001.md:82-104` | Incluye contraseña/sesiones (`:91,99`), PIN por `pin_length` (`:92`), último administrador (`:96-97`), auto-cambio (`:98`), eliminación lógica (`:100`) y `max_users` sobre activos (`:101`). |
| Plan técnico mínimo | `USERS-001.md:108-117` | Incluye rutas, `UserController`, FormRequests, `UserAccessManager`, `UserAccessFingerprint`, `SoftDeletes`, UI y pruebas. |
| Decisiones propuestas | `USERS-001.md:121-136` | 16 decisiones rotuladas como propuestas para aprobación única; no se presentan como aprobadas. |
| Gates propuestos | `USERS-001.md:140-151` | Gates de último administrador, fingerprint, PIN, `SoftDeletes`, límite sobre activos y regresión. Correctamente no registrados aún en `GATES_BY_PACKET`. |
| Riesgos y controles | `USERS-001.md:71-78` | Declara fingerprint ausente, concurrencia SQLite, matriz de permisos, unicidad de email, regresión y credenciales. |
| Estado de funciones | `USERS-001.md:161-163` | Distingue base implementada, propuesta DRAFT y diferidos; aclara que `User` aún no usa `SoftDeletes`. |

No se detectan contradicciones con v2.2, DB-001, AUTH-001 ni BRANCH-001. Las precisiones de las secciones 8.1–8.3 no cambian el alcance.

## 5. Constancia de no implementación

- `USERS-001.md:3-4` conserva `status: DRAFT` y `owner_approval: Pendiente...`.
- `docs/work/CURRENT.md:18` describe USERS-001 como “DRAFT revisado, pendiente de QA documental y aprobación”, no activa y no VERIFIED.
- `git status` solo muestra los 2 archivos documentales indicados y el artefacto histórico sin seguimiento.
- No existen `app/Http/Controllers/UserController.php`, `app/Http/Requests/UserRequest.php`, `app/Http/Requests/UserBranchAssignmentRequest.php`, `app/Support/UserAccessManager.php`, `app/Support/UserAccessFingerprint.php`, `tests/Feature/Users/`, `resources/js/Pages/Users/` ni `documentacion/modulos/usuarios/`.
- `routes/web.php` no contiene rutas CRUD `/users`; solo conserva la ruta de prueba AUTH-001 `/usuarios` (`routes/web.php:33-35`).
- No hay migraciones nuevas ni modificadas: `git diff --name-only HEAD` solo lista `docs/work/CURRENT.md` y `docs/work/features/USERS-001.md`.
- `scripts/sf_verify.py` no registra `USERS-001` en `GATES_BY_PACKET`; las entradas existentes incluyen BOOT-001, HARNESS-001, BRAND-001, CI-001, STAGING-001, AUTH-001 y BRANCH-001.
- `config/modules.php` no se modificó; `users.manage` ya existe en la línea 20.
- No hay UI implementada; no se aplica revisión de brand drift.

## 6. Permisos, PII y decisiones

- El permiso sigue siendo `users.manage`, server-side, sin permiso nuevo: `config/modules.php:20`; `USERS-001.md:121`.
- La matriz v2.2 asigna `users.manage`, `settings.manage` y `modules.manage` solo al Admin Central: Parte 1 línea 296.
- No se modifican `roles`, `permissions` ni `role_permissions`; CORE-16 queda fuera: `USERS-001.md:26,122`.
- Las 16 decisiones están rotuladas como propuestas; no se introducen decisiones aprobadas por intuición.
- `max_users` sobre usuarios activos deriva de Parte 2 línea 340, no es una decisión nueva.
- “Sin fila de `license_states` = ilimitado” es la decisión propuesta n.º 11, coherente con BRANCH-001.
- “`central_admin` solo global” está en contratos (`USERS-001.md:56`) y también como decisión propuesta n.º 4 (`:124`); debe entenderse como propuesta, no como regla ya aprobada.
- No se exponen contraseñas, PIN, hashes ni secretos; el listado muestra “PIN configurado” y metadatos no sensibles (`USERS-001.md:54,133`).
- La auditoría declarada cubre mutaciones con `is_security_event=1` (`USERS-001.md:65,132`), no lectura de PII del listado; ver observación 8.3.

## 7. Verificación de referencias

| Referencia | Ubicación en el DRAFT | Verificación | Resultado |
|---|---|---|---|
| CORE-03, CORE-04, CORE-15 | `USERS-001.md:34-36` | Parte 1 §8.1, líneas 543-555; `INDEX-REQUISITOS.md:91-105` | Correcta |
| SEC-01, SEC-04, SEC-05, SEC-06, SEC-09, SEC-12 | `USERS-001.md:38-43` | Parte 1 §9.3, líneas 888-899 | Correcta |
| Parte 1 §6.1–6.3 | `USERS-001.md:44` | Líneas 236-301; matriz `users.manage` en línea 296 | Correcta |
| Parte 2 §4.7–4.11 | `USERS-001.md:45` | Líneas 391-445 | Correcta |
| `system_configs.pin_length` | `USERS-001.md:43,54` | Parte 2 línea 217; seeder línea 29 | Correcta |
| `license_states.max_users` | `USERS-001.md:64` | Parte 2 línea 340; migración línea 20 | Correcta |
| `users.email` único / `deleted_at` | `USERS-001.md:52,63` | Migración `users` líneas 15 y 39 | Correcta |
| `branch_user` PK `(user_id, branch_id)` | `USERS-001.md:45` | Migración `branch_user` línea 16 | Correcta |
| AUTH-001 / `VerifySession` | `USERS-001.md:47,63,110` | `VerifySession.php:29-36`; `LoginController.php:98-100` | Correcta |
| BRANCH-001 / bloqueo de licencia | `USERS-001.md:48,64` | `BranchController.php:196-199` | Correcta |
| `admin:setup` | `USERS-001.md:22` | `AdminSetupCommand.php:13-15` | Correcta |
| DB-001 | `USERS-001.md:7,46` | Packet `DB-001.md` en `status: APPROVED`; tablas y columnas citadas existen en migraciones | Correcta; no se le atribuye VERIFIED |
| `sf_verify.py` | `USERS-001.md:140-151` | `GATES_BY_PACKET` sin entrada USERS-001 | Correcta; gates no registrados |

## 8. Observaciones no bloqueantes

1. **Redacción del auto-cambio frente a la prohibición absoluta.** `USERS-001.md:61` dice: “La misma regla aplica a auto-desactivación, auto-eliminación y auto-cambio…”. Puede leerse como si la auto-desactivación/auto-eliminación fueran condicionales, cuando `USERS-001.md:60` y EARS `:95` las prohíben siempre. Recomendación: explicitar que la comprobación del último administrador también cubre esos casos, pero que la auto-desactivación y auto-eliminación siguen prohibidas en todos los casos.
2. **Cobertura real de `VerifySession`.** `USERS-001.md:63,110` afirma que el fingerprint se recalcula “en cada petición autenticada”. Hoy `verify.session` solo se aplica al grupo del dashboard (`routes/web.php:25`); las rutas de cambio de contraseña (`routes/web.php:19-22`) solo usan `auth`. Precisar si se ampliará la cobertura o acotar la afirmación a rutas protegidas.
3. **Auditoría de lectura de PII.** El listado muestra nombre/email, pero la auditoría declarada (`USERS-001.md:65`) cubre mutaciones, no lectura. PRV-06/CORE-10 exige registro de acceso a datos personales (Parte 1 líneas 550 y 805). Aclarar si se difiere expresamente a CORE-10 o se incluye en este packet; no bloquea el DRAFT, pero debe quedar declarado antes de implementar.
4. **Detalles editoriales.** `USERS-001.md:5` dice “4 ajustes” y la sección `:10-16` enumera 5. `USERS-001.md:161` todavía omite `admin:setup` al listar la base implementada, aunque `:22` ya lo corrige.

## 9. Comandos y pruebas

Comandos ejecutados (solo lectura):

- `git rev-parse HEAD`
- `git status --porcelain=v1 --untracked-files=all`
- `git ls-files --others --exclude-standard`
- `git diff --stat HEAD`
- `git diff --name-only HEAD`
- `git hash-object docs/work/features/USERS-001.md docs/work/CURRENT.md docs/work/USERS-001-DRAFT-REVIEW-20261110.md`
- Lecturas y búsquedas de solo lectura en `app/`, `config/`, `database/`, `routes/`, `tests/`, `scripts/` y `docs/`.

No ejecutados:

- `php artisan test`
- migraciones o seeders
- `npm run typecheck`
- `npm run build`
- deploy o staging
- `scripts/safe_git_pull.py` (no fue necesario)

Motivo: revisión documental sin cambios de código. No se declaran pruebas ejecutadas que no se hayan realizado.

## 10. Veredicto

**APROBADO** como QA documental del DRAFT.

Los 5 ajustes previos siguen correctos y H1–H4/O1–O2 quedaron cerrados. No hay bloqueantes. Las observaciones de la sección 8 son de precisión, cobertura o edición, y pueden resolverse antes de implementar sin reabrir el alcance del DRAFT.

Este artefacto no declara la feature APPROVED ni VERIFIED. USERS-001 sigue en **DRAFT**, no activa, sin implementación y sin gates registrados en `sf_verify.py`. La aprobación del propietario y el paso a `APPROVED` en `docs/work/CURRENT.md` siguen pendientes.
