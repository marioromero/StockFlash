# USERS-001 — Revisión QA documental del DRAFT (2026-11-10)

- Fecha: 2026-11-10
- Revisor: `stockflash-reviewer`
- Base revisada: `11abfb0b72aad4bbe9db1eb552c49173a61266db` (`docs(users): add USERS-001 draft proposal`)
- Rama: `main`
- Alcance: working tree contra base `11abfb0`; solo `docs/work/features/USERS-001.md` y `docs/work/CURRENT.md`.
- Estado revisado: USERS-001 en **DRAFT**, sin implementación.
- Modo: solo lectura. No se modificaron archivos, no se ejecutaron migraciones, pruebas, build ni deploy.
- Veredicto QA del DRAFT: **CAMBIOS NECESARIOS** (correcciones documentales; no aprueba implementación ni declara VERIFIED).

## 1. Estado del árbol y hashes

| Elemento | Valor |
|---|---|
| HEAD / base | `11abfb0b72aad4bbe9db1eb552c49173a61266db` |
| `docs/work/features/USERS-001.md` | `af1457e298e8c3db9eb7d2a38c46563e4584ad00` |
| `docs/work/CURRENT.md` | `44ddf0e044fe2308098a0f851ec57bd14dc41f5e` |

Comandos de control:

```text
git status --porcelain=v1 --untracked-files=all
 M docs/work/CURRENT.md
 M docs/work/features/USERS-001.md

git ls-files --others --exclude-standard
(sin salida)

git diff --stat HEAD
 docs/work/CURRENT.md            |  2 +-
 docs/work/features/USERS-001.md | 83 ++++++++++++++++++++++++++++-------------
 2 files changed, 59 insertions(+), 26 deletions(-)
```

El aviso de Git sobre LF/CRLF en `USERS-001.md` no altera el contenido revisado ni los hashes solicitados.

## 2. Verificación punto por punto de los 5 ajustes

### 2.1 Último administrador protegido frente a cambios de rol/acceso

**Estado: incorporado, con precisión pendiente (H2).**

- El DRAFT amplía la protección a cambios de rol global, conversión global↔sucursal y reemplazo de asignaciones que quiten `users.manage`: `USERS-001.md:12,60-61,97,133`.
- Exige comprobación transaccional y pruebas propias: `USERS-001.md:61,67,114,141` (`UserLastAdminTest`).
- La regla cubre auto-desactivación, auto-eliminación y auto-cambio de rol: `USERS-001.md:61`.
- El permiso `users.manage` solo lo posee `central_admin` por la matriz v2.2 y el seeder: spec Parte 1:296; `database/seeders/RolesAndPermissionsSeeder.php:54-65`.
- La autorización real es server-side y basada en permiso, no en nombre de rol: `app/Http/Middleware/CheckPermission.php:45-60`.

Precisión pendiente: no se define el conjunto de filas bloqueadas para contar administradores restantes. El precedente de BRANCH-001 bloquea una fila estable (`license_states`) antes de contar (`app/Http/Controllers/BranchController.php:196-199`). Para el último administrador se debe bloquear el conjunto de usuarios que hoy tienen `users.manage`, o una fila de serialización equivalente, antes de contar y actualizar. También debe aclararse si el auto-cambio de rol se prohíbe siempre o solo cuando dejaría cero administradores; hoy `USERS-001.md:61` es ambiguo y no tiene EARS específico.

### 2.2 SEC-05: invalidación de sesiones al restablecer contraseña o cambiar roles/accesos

**Estado: incorporado.**

- Mecanismo concreto: fingerprint de acceso por sesión, guardado al iniciar sesión y recalculado en `VerifySession`: `USERS-001.md:13,63,109,134`.
- Cubre contraseña, rol global, asignaciones por sucursal, sucursal inicial y estado: `USERS-001.md:13,63,98,142`.
- Es compatible con la arquitectura existente: `LoginController` ya guarda `session_started_at` y `last_activity` (`app/Http/Controllers/Auth/LoginController.php:98-100`); `VerifySession` ya invalida sesiones y actualiza actividad (`app/Http/Middleware/VerifySession.php:29-36,48-78`).
- Sin migraciones ni cambio de driver: `USERS-001.md:63,109,134`.
- Fingerprint ausente = sesión invalidada: `USERS-001.md:63,73`.
- Prueba prevista: `UserSessionInvalidationTest` en `USERS-001.md:114`; gate en `:142`.

Observación menor: el fingerprint incluye el hash de contraseña. Conviene calcularlo con HMAC/hash de componentes y no llamar “no secreto” al hash; el DRAFT ya exige no registrarlo ni exponerlo (`USERS-001.md:63`). La invalidación por sucursal inicial y estado excede el texto literal de SEC-05, pero es coherente con la seguridad de sesión y está declarada como decisión propuesta 15 (`USERS-001.md:134`), sujeta a aprobación.

### 2.3 PIN validado contra `system_configs.pin_length`

**Estado: incorporado.**

- Exige exactamente `pin_length`, predeterminado 4, leído en servidor: `USERS-001.md:14,43,54,92,127,143`.
- Elimina el rango fijo 4–8; solo lo menciona para negarlo: `USERS-001.md:14,143`.
- El contrato define `pin_length` con defecto 4: Parte 2:217; el seeder lo contiene: `database/seeders/SystemConfigsSeeder.php:29`.
- No se fija el valor en la UI: `USERS-001.md:54`.
- Solo se guarda `pin_hash` y no se expone: `USERS-001.md:54,92,132`.

### 2.4 `SoftDeletes` en el modelo `User` reutilizando `deleted_at`

**Estado: incorporado.**

- El DRAFT exige añadir el trait reutilizando `deleted_at`, sin migraciones: `USERS-001.md:15,62,68,111,135,144`.
- La columna ya existe: `database/migrations/2026_10_16_000009_create_users_table.php:39` (`$table->softDeletes()`).
- El modelo actual no usa el trait: `app/Models/User.php:38-40` (`use Notifiable;` solamente).
- El estado de funciones lo declara correctamente: `USERS-001.md:160` (“aún no usa `SoftDeletes`”).
- Pruebas de exclusión de eliminados previstas: `USERS-001.md:68,99,144`.

### 2.5 `max_users` limita usuarios activos

**Estado: incorporado.**

- El conteo considera `is_active=1` y no eliminados lógicamente: `USERS-001.md:16,64,100,130`.
- `license_states.max_users` está definido como “Usuarios activos”: Parte 2:340; migración `database/migrations/2026_10_16_000004_create_license_states_table.php:20`.
- `0` = ilimitado es coherente con el contrato v2.2 y con el precedente de BRANCH-001.
- Se mantiene la transacción y el bloqueo: `USERS-001.md:64,67,130`.
- Pruebas previstas: `UserLimitTest` y gate de límite sobre activos (`USERS-001.md:114,146`).

Precisión recomendada: al igual que BRANCH-001, conviene declarar que se bloquea la fila de `license_states` antes de contar, para evitar carreras de creación/activación (`app/Http/Controllers/BranchController.php:196-199`).

## 3. Coherencia entre contratos, EARS, plan, decisiones, gates, riesgos y estado de funciones

| Sección | Ubicación | Resultado |
|---|---|---|
| Contratos / casos límite | `USERS-001.md:50-68` | Refleja los 5 ajustes; sin contradicción con v2.2, DB-001, AUTH-001 o BRANCH-001. |
| Aceptación EARS | `USERS-001.md:80-103` | Incluye criterios para contraseña/sesiones, PIN, último administrador por rol, eliminación lógica y `max_users` activo. Falta EARS específico para auto-cambio de rol (H2). |
| Plan técnico mínimo | `USERS-001.md:105-116` | Incluye `UserAccessManager`, `UserAccessFingerprint`, `SoftDeletes`, `UserLastAdminTest` y `UserSessionInvalidationTest`. |
| Decisiones propuestas | `USERS-001.md:118-135` | 16 decisiones rotuladas como propuestas; no se presentan como aprobadas. |
| Gates propuestos | `USERS-001.md:137-150` | Añade gates de último administrador, fingerprint SEC-05, PIN por `pin_length`, `SoftDeletes` y límite sobre activos. Correctamente no registrados aún en `GATES_BY_PACKET`. |
| Riesgos y controles | `USERS-001.md:71-78` | Declara fingerprint ausente, concurrencia SQLite, matriz de permisos, unicidad de email, regresión y credenciales. |
| Estado de funciones | `USERS-001.md:158-162` | Distingue base implementada de propuesta; aclara que `User` aún no usa `SoftDeletes`. |

No se detectan contradicciones con v2.2, DB-001, AUTH-001 ni BRANCH-001 en los 5 ajustes. La única ambigüedad interna relevante es el alcance del auto-cambio de rol (H2).

## 4. Constancia de no implementación

- `USERS-001.md` conserva `status: DRAFT` y `owner_approval: Pendiente...` (`USERS-001.md:3-4`).
- `docs/work/CURRENT.md:18` describe USERS-001 como “DRAFT revisado, pendiente de QA documental y aprobación”, no activa y no VERIFIED.
- `git status` solo muestra los 2 archivos documentales indicados; no hay archivos sin seguimiento.
- No existen `app/Http/Controllers/UserController.php`, `tests/Feature/Users/`, `resources/js/Pages/Users/`, `app/Support/UserAccessManager.php` ni `app/Support/UserAccessFingerprint.php`.
- `routes/web.php` no contiene rutas CRUD `/users`; solo conserva la ruta de prueba AUTH-001 `/usuarios` (`routes/web.php:33-35`).
- No hay migraciones nuevas ni modificadas.
- `scripts/sf_verify.py` no registra `USERS-001` en `GATES_BY_PACKET` (entradas existentes: BOOT-001, HARNESS-001, BRAND-001, CI-001, STAGING-001, AUTH-001 y BRANCH-001).
- `config/modules.php` no se modificó; `users.manage` ya existe en la línea 20.

## 5. Permisos y decisiones

- El permiso sigue siendo `users.manage`, server-side, sin permiso nuevo: `config/modules.php:20`; `USERS-001.md:120`.
- La matriz v2.2 asigna `users.manage`, `settings.manage` y `modules.manage` solo al Admin Central (spec Parte 1:296).
- No se detectan decisiones de negocio nuevas presentadas como aprobadas. Las 16 decisiones están rotuladas como “propuestas para aprobación única”.
- `max_users` sobre usuarios activos no es una decisión nueva: deriva de Parte 2:340.
- “Sin fila de `license_states` = ilimitado” es una decisión propuesta (n.º 11), coherente con BRANCH-001, no una regla ya aprobada.

## 6. Verificación de referencias

| Referencia | Ubicación en el DRAFT | Verificación | Resultado |
|---|---|---|---|
| CORE-03 | `USERS-001.md:34` | Spec Parte 1 §8.1, línea 543; INDEX línea 91 | **Incorrecta: dice §9.2** |
| CORE-15 | `USERS-001.md:35` | Spec Parte 1 §8.1, línea 555; INDEX línea 105 | **Incorrecta: dice §9.2** |
| CORE-04 | `USERS-001.md:36` | Spec Parte 1 §8.1, línea 544; INDEX línea 92 | **Incorrecta: dice §9.2** |
| SEC-04 | `USERS-001.md:39` | Spec Parte 1 §9.3, línea 891 | Correcta |
| SEC-05 | `USERS-001.md:40` | Spec Parte 1 §9.3, línea 892 | Correcta |
| SEC-12 | `USERS-001.md:43` | Spec Parte 1 §9.3, línea 899 | Correcta |
| DB-001 Parte 2 §4.7–4.11 | `USERS-001.md:45` | Parte 2 líneas 391–445: `roles`, `permissions`/`role_permissions`, `users`, `user_mfa_recovery_codes`, `branch_user` | Correcta |
| `users.global_role_id` / `default_branch_id` / `deleted_at` | `USERS-001.md:63` | Migración `users` líneas 19–22 y 39 | Correcta |
| `branch_user` PK `(user_id, branch_id)` | `USERS-001.md:45` | Migración `branch_user` línea 16 | Correcta |
| `system_configs.pin_length` | `USERS-001.md:14,43,54` | Seeder línea 29; Parte 2 línea 217 | Correcta |
| `license_states.max_users` | `USERS-001.md:64` | Parte 2 línea 340; migración `license_states` línea 20 | Correcta |
| AUTH-001 / `VerifySession` | `USERS-001.md:47` | `VerifySession.php:29-36` | Correcta |
| BRANCH-001 / sucursales inactivas | `USERS-001.md:48` | `EnsureBranchAccess.php:61-64` | Correcta |

Corrección necesaria: reemplazar `Parte 1 §9.2` por `Parte 1 §8.1` en `USERS-001.md:34-36`.

## 7. Riesgos residuales

Declarados en el DRAFT:

- Sesiones previas al despliegue sin fingerprint: se invalidan y exigen nuevo login (`USERS-001.md:73`).
- `lockForUpdate()` es no-op en SQLite; la carrera real se difiere a MariaDB DEV (`USERS-001.md:75`).
- La matriz `role_permissions` no se cubre con el fingerprint; su edición queda fuera de USERS-001 (`USERS-001.md:74`).
- Regresión de AUTH-001/BRANCH-001 por tocar `AuditLogger`, `VerifySession`/`LoginController` y `SoftDeletes` (`USERS-001.md:77`).
- Credenciales temporales comunicadas fuera del sistema (`USERS-001.md:78`).
- Unicidad de email sin depender del collation (`USERS-001.md:76`).

Riesgo residual no declarado con precisión: el conjunto de filas bloqueadas para la protección del último administrador y para `max_users` no está definido; debe precisarse antes de aprobar (H2).

## 8. Hallazgos priorizados

### H1 — Referencias CORE con sección incorrecta (corrección obligatoria)

`USERS-001.md:34-36` citan `Parte 1 §9.2`. El contrato v2.2 ubica CORE-03, CORE-04 y CORE-15 en `Parte 1 §8.1 Núcleo (core)` (spec líneas 535–555; INDEX líneas 91–105). La línea 35 (CORE-15) es nueva en esta revisión; las líneas 34 y 36 ya venían mal desde el DRAFT base. Corregir las tres a `Parte 1 §8.1`.

### H2 — Precisión transaccional del último administrador y `max_users` (precisar antes de aprobar)

`USERS-001.md:61,64,67` prometen transacción y bloqueo, pero no definen el recurso bloqueado. Para evitar que dos degradaciones concurrentes de administradores distintos confirmen ambas, debe bloquearse un conjunto estable: todas las filas que hoy pueden ostentar `users.manage`, o una fila de serialización equivalente. Para `max_users`, replicar el patrón de `BranchController.php:196-199` bloqueando `license_states`. Añadir prueba concurrente en MariaDB DEV y prueba secuencial en CI. Aclarar además si el auto-cambio de rol se prohíbe siempre o solo si dejaría cero administradores; hoy `USERS-001.md:61` es ambiguo y los EARS `95-97` no lo cubren.

### H3 — Fingerprint y hash de contraseña (menor)

`USERS-001.md:63` llama “dato no secreto” al hash de contraseña. Es material derivado de credencial. Precisar que el fingerprint se calcula con HMAC/hash de componentes, no con concatenación cruda, y que nunca se registra. El mecanismo sigue siendo compatible con `LoginController.php:98-100` y `VerifySession.php:29-36,48-78`, sin migración.

### H4 — Mención incompleta de comandos existentes (menor)

`USERS-001.md:22` afirma que “hoy solo existe `admin:create`”. También existe `admin:setup` (`app/Console/Commands/AdminSetupCommand.php:13-15`). Ajustar la frase; no cambia el alcance del packet.

### Observaciones no bloqueantes

- O1: la ruta de prueba `/usuarios` (`routes/web.php:33-35`) coexistirá con el futuro CRUD `/users`; el plan debe indicar si se reemplaza o elimina.
- O2: la reserva de email de usuarios soft-deleted (`USERS-001.md:52,68,99`) debe cubrirse explícitamente en la validación y en una prueba de unicidad, no solo en el índice UNIQUE.

## 9. Pruebas y comandos

Comandos ejecutados (solo lectura):

- `git rev-parse HEAD`
- `git status --porcelain=v1 --untracked-files=all`
- `git ls-files --others --exclude-standard`
- `git diff --stat HEAD`
- `git diff --name-only HEAD`
- `git hash-object docs/work/features/USERS-001.md docs/work/CURRENT.md`
- Lecturas y búsquedas de solo lectura en `app/`, `config/`, `database/`, `routes/`, `tests/`, `scripts/` y `docs/`.

No ejecutados:

- `php artisan test`
- migraciones o seeders
- `npm run typecheck`
- `npm run build`
- deploy o staging

Motivo: revisión documental sin cambios de código. No se declaran pruebas ejecutadas que no se hayan realizado.

## 10. Veredicto

**CAMBIOS NECESARIOS**

Los 5 ajustes de la revisión externa del 2026-11-10 están correctamente incorporados en el DRAFT, sin contradicciones sustantivas con v2.2, DB-001, AUTH-001 ni BRANCH-001. Antes de una nueva revisión debe corregirse H1 (referencias CORE a §9.2). Se recomienda cerrar H2 antes de pasar a APPROVED. H3 y H4 son correcciones menores.

Este artefacto es QA documental del DRAFT. No se declara la feature APPROVED ni VERIFIED; USERS-001 sigue en DRAFT, no activa y sin implementación.
