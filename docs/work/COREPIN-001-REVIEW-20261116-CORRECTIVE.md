# COREPIN-001 — Revisión QA independiente del ciclo correctivo NB-1 (AUTH_TOCTOU)

reviewer: stockflash-reviewer
fecha: 2026-11-16
feature_packet: `docs/work/features/COREPIN-001.md` (APPROVED, AUTO_GIT, ONE_SMALL_WAVE; `approval_basis: existing_approved_contract_owner_delegation_2026-11-16`)
base_sha: `3c7e68846090078586ee8d6ad1aa44e983683b3b` (origin/main; worktree local, sin commit ni push)
alcance_revisado: FIX AUTH_TOCTOU — NB-1 de `docs/work/COREPIN-001-REVIEW-20261116.md`
veredicto: **APROBADO**
bloqueantes: 0
no_bloqueantes: 2
observaciones: 3

> Este dictamen es una revisión QA independiente sobre un estado local sin commit. **No es `VERIFIED`**, no autoriza despliegue, no ejecuta migraciones remotas y no sustituye la revisión técnica externa de ChatGPT.

---

## 1. Estado exacto verificado

`git status --porcelain` al iniciar la revisión:

```
 M app/Support/Authorization/PinAuthorizationService.php
 M docs/work/COREPIN-001-NOTES-20261116.md
 M tests/Feature/Core/PinAuthorizationServiceTest.php
?? docs/work/COREPIN-001-VERIFY-20261116-205450.md
```

`git ls-files --others --exclude-standard` al iniciar: solo `docs/work/COREPIN-001-VERIFY-20261116-205450.md`; mi ejecución de gates agregó después `docs/work/COREPIN-001-VERIFY-20261116-205938.md` (artefacto de esta revisión) y este propio informe.
`git status --porcelain -- <rutas protegidas>` (AGENTS.md, opencode.json, CONSTITUTION.md, docs/product, docs/branding, CONTEXTO-CHATGPT.md, public/docs/index.html, resources/roadmap/index.html, ROADMAP-HTML-ESTADO.md): vacío.

Hashes calculados con `git hash-object` — **coinciden exactamente** con los entregados por el orquestador:

| Archivo | SHA-1 | Estado |
|---|---|---|
| `app/Support/Authorization/PinAuthorizationService.php` | `917b38ecee29c4518828f75075d1d599c5693e72` | Modificado en este ciclo |
| `tests/Feature/Core/PinAuthorizationServiceTest.php` | `e182cff618ffc68ed77307703179aa0954c874e4` | Modificado en este ciclo |
| `docs/work/COREPIN-001-NOTES-20261116.md` | `65e34004124f4a011a3fabd1ec3f6c2ec696ad45` | Modificado en este ciclo |
| `scripts/sf_verify.py` | `36f6232791c163a6c6a5e2c74bbd1758ab2cb5a9` | Sin cambios en este ciclo |
| `app/Support/Authorization/AuthorizationDeniedException.php` | `d200f49b00dc4ea0e35f05064b3c36e9607e35f2` | Sin cambios |

Contexto estable: packet `ab60330f908af3cb9b038d0aa2e9b5f1198e9ce0`, `docs/work/CURRENT.md` `b10e926a085f6a15184697292feee6ff1f0b86de`, `app/Support/AuditLogger.php` `61fedb4b822ed50612abeecb66043cce40dda7de`.

`git diff HEAD --stat`: 3 archivos, +466/−24. No hay cambios en rutas, controladores, migraciones, esquema, seeders, RBAC, `config/modules.php`, UI ni dependencias.

---

## 2. Resultados por criterio

### C1 — Revalidación de política DENTRO de la transacción y después de los locks — PASS

- La transacción única comienza en `PinAuthorizationService.php:128`; la **única** inserción en `authorization_overrides` está en `:213-226` y solo se alcanza después de `lockAndRevalidatePolicy()` (`:145-157`) y de un PIN correcto (`:178-211`).
- `lockAndRevalidatePolicy()` (`:275-450`) adquiere primero las filas con `lockForUpdate()` y recién después evalúa la política (`:384-447`). La decisión de insertar o denegar depende exclusivamente de esas filas bloqueadas; la prevalidación previa (`:122-126`) queda como rechazo temprano y no puede por sí sola autorizar una inserción.
- Se revalida completo: solicitante existente/activo/no eliminado (`:386-388`), acceso a la sucursal por rol global o `branch_user` de ESA sucursal (`:390-403`), sucursal existente/no eliminada (`:405-407`) y activa (`:409-411`), autorizador existente/no eliminado (`:413-415`) y activo (`:417-419`), rol aplicable global o de la sucursal (`:324-325`, `:421-423`), permiso real `override.authorize` vía `permissions` + `role_permissions` (`:334-345`, `:425-427`), `authority_level >= override_min_authority` leído de configuración bloqueada (`:366-382`, `:429-431`), `pos_station` existente/de la sucursal/no eliminado (`:433-437`) y `reason_code` existente/activo/scope `override`/`requires_note` (`:439-447`).
- No hay permiso cruzado: `branch_user` se filtra por `branch_id` y solo por los dos usuarios involucrados (`:306-312`); el rol de otra sucursal no se consulta.
- Si la revalidación deniega, se retorna antes de la inserción; no existe ruta alternativa que inserte AO sin esa evaluación.

### C2 — Locks en orden estable y razonable — PASS (con límite de concurrencia MariaDB)

Orden efectivo, idéntico y documentado en `:256-273`:

1. `users` de solicitante y autorizador, deduplicados y ordenados por `id` (`:284-299`).
2. `branch_user` de la sucursal, orden `(user_id, branch_id)` (`:306-312`).
3. `roles` (`:327-332`), `permissions` por `code` único (`:334-337`), `role_permissions` (`:339-345`).
4. `branches` (`:347-350`).
5. `pos_stations` si aplica (`:352-357`).
6. `reason_codes` si aplica (`:359-364`).
7. `system_configs` (`override_min_authority`, `pin_max_attempts`), orden `config_key` (`:366-371`).

No se detecta orden inconsistente dentro del servicio. `users`, `branch_user`, `role_permissions` y `system_configs` se recorren en orden de clave primaria/única, y `permissions.code` es único en el esquema. `UserAccessManager::lockActiveUsers()` también bloquea primero `users`, por lo que no aparece un ciclo evidente con la administración de usuarios. **La serialización real, el comportamiento de `lockForUpdate` y la ausencia de deadlocks en MariaDB no pueden probarse con SQLite en memoria: quedan pendientes predespliegue (§5).**

### C3 — Persistencia de fallos de PIN, contadores, lockout y eventos — PASS

- Los rechazos por PIN se retornan como `denied` dentro de la transacción y la excepción se lanza **después** del commit (`:200-205`, `:246-251`), por lo que intentos, `pin_locked_until` y auditorías persisten.
- Contador secuencial y lockout tras `pin_max_attempts` leído de la configuración bloqueada (`:178-197`); éxito resetea contadores y bloqueo (`:207-211`).
- La revalidación transaccional no altera ese contrato: si deniega, audita y retorna sin tocar contadores de PIN (los tests lo verifican con PIN correcto).
- Auditorías de denegación, fallo, lockout y aprobación mantienen `is_security_event=1` y no incluyen PIN ni hash (`:790-864`).

### C4 — Atomicidad AO + auditoría de aprobación — PASS

- La inserción del AO (`:213-226`) y `auditApproval()` (`:228-241`) ocurren en la misma transacción. La prueba existente `test_approval_rolls_back_when_audit_fails` sigue pasando: ante fallo del logger inyectado, no queda fila AO y los contadores se revierten.
- La denegación transaccional se audita dentro de la transacción (`transactionDenial()`, `:458-468`) y el commit ocurre antes de lanzar `AuthorizationDeniedException`; la auditoría de denegación queda persistida con `is_security_event=1`.

### C5 — Prueba determinista de revocación entre prevalidación e inserción — PASS

- Seam sin hooks de producción: `Event::listen(TransactionBeginning::class, ...)` en `PinAuthorizationServiceTest.php:871-883`. Como el evento se dispara al abrir la transacción real del servicio, la revocación ocurre **después** de toda la prevalidación y **antes** de `lockAndRevalidatePolicy()`.
- Casos cubiertos:
  - permiso `override.authorize` revocado → `authorizer_permission_denied` (`:706-756`);
  - asignación `branch_user` del autorizador eliminada → `authorizer_role_missing` (`:758-816`);
  - `reason_code` desactivado → `reason_code_invalid` (`:818-869`).
- Cada caso verifica: 0 filas en `authorization_overrides`, auditoría de denegación persistida con `is_security_event=1`, 0 eventos `authorization_override.approved` y contador de PIN sin incrementar con PIN correcto. El seam muta datos reales y la revalidación real decide; no se inyecta una política falsa.
- La prueba detectaría la eliminación de la revalidación: con la revalidación ausente, el PIN correcto insertaría el AO y la aserción de excepción/0 filas fallaría.

### C6 — Alcance y archivos protegidos — PASS

- Diff limitado a servicio, pruebas y notas. Sin rutas/endpoints/UI, sin migraciones ni cambios de esquema, sin RBAC/seeders/`config/modules.php`, sin dependencias nuevas.
- `git status` y `git ls-files --others` no muestran archivos en `docs/product`, `docs/branding`, `docs/harness`, `public/docs`, `resources/roadmap` ni cambios en `AGENTS.md`, `opencode.json`, `CONSTITUTION.md`, `CONTEXTO-CHATGPT.md` o `ROADMAP-HTML-ESTADO.md`.
- `scripts/sf_verify.py` no cambió en este ciclo (hash idéntico).

### C7 — Regresiones — PASS

Suite objetivo, Core y suite completa en verde; detalle en §4. No se observan regresiones funcionales ni de contrato.

---

## 3. Hallazgos

### Bloqueantes

Ninguno.

### No bloqueantes

**NB-A — Cobertura transaccional parcial en pruebas de seam.**
Las tres pruebas nuevas cubren revocación de permiso, asignación de sucursal y `reason_code`. No hay pruebas de seam para las demás ramas de denegación transaccional (`requester_inactive`, `branch_not_found`, `branch_inactive`, `authorizer_inactive`, `authorizer_role_missing` por rol global inexistente, `authorizer_authority_insufficient`, `pos_station_invalid`, `reason_note_required`, `:386-447`). Hoy esas comprobaciones existen en el código y las rutas equivalentes se ejercitan en la prevalidación, pero si en el futuro se removieran del bloque transaccional, las pruebas actuales no lo detectarían.
Condición de aceptación sugerida: añadir casos de seam para al menos autoridad insuficiente y sucursal inactiva, o extraer la política a una función única compartida por prevalidación y revalidación que permita una prueba directa. No bloquea este ciclo porque NB-1 se refiere específicamente a rol/permiso y el código actual revalida todas las condiciones.

**NB-B — Orden de locks y concurrencia real MariaDB no verificados empíricamente.**
El orden es coherente por inspección y está documentado, pero SQLite no impone `lockForUpdate`; no se probó serialización entre solicitudes concurrentes del mismo autorizador ni ausencia de deadlocks con revocaciones concurrentes. Debe validarse en MariaDB aislada antes del despliegue, como ya declara `COREPIN-001-NOTES-20261116.md`. No es un defecto del código revisado, sino una verificación pendiente por entorno.

### Observaciones

**OB-A.** La prueba de `reason_code` desactivado verifica `pin_failed_attempts = 0` pero no `pin_locked_until` nulo; las otras dos sí lo verifican. Mejora menor de completitud.

**OB-B.** Se mantiene la doble consulta de política (prevalidación + revalidación), lo que agrega lecturas y locks, pero es deliberado: la prevalidación da rechazo temprano y la revalidación es la autoritativa. Correcto para el objetivo de seguridad.

**OB-C.** Si `AuditLogger` fallara durante una denegación transaccional, la transacción se revertiría y no quedaría la auditoría de denegación. Es coherente con el contrato de auditoría atómica (`AuditLogger.php:17-19`), no un defecto funcional.

---

## 4. Comandos ejecutados y resultados exactos

| Comando | Resultado |
|---|---|
| `git status --porcelain` | 3 archivos modificados esperados + 1 reporte de gates untracked; sin archivos prohibidos |
| `git ls-files --others --exclude-standard` | solo reportes de gates |
| `git hash-object <archivos>` | los 5 hashes coinciden exactamente con el estado entregado |
| `git diff --check HEAD` | exit 0 (solo warnings de fin de línea CRLF de Git) |
| `php artisan test tests/Feature/Core/PinAuthorizationServiceTest.php` | **31 passed (120 assertions)**, exit 0, ~6.0 s |
| `php artisan test tests/Feature/Core` | **97 passed (529 assertions)**, exit 0, ~9.4 s |
| `php artisan test` | **359 passed (2376 assertions)**, exit 0, ~51.2 s |
| `py -3 scripts/sf_verify.py COREPIN-001` | **GATES_PASS — 17/17 PASS**, exit 0; generó `docs/work/COREPIN-001-VERIFY-20261116-205938.md` (artefacto de esta revisión) |

Desglose de gates de mi ejecución: CP1 packet/activo PASS; CP2 archivos PASS; CP3 sin rutas/UI/HTTP PASS; CP4 invariantes PASS; CP5 sin cambios de esquema/UI/config PASS; CP6 protegidos intactos PASS; CP7 contratos PASS; CP8 Core PASS; CP9 Auth PASS; CP10 Branches PASS; CP11 Users PASS; CP12 Settings PASS; CP13 suite completa PASS; CP14 typecheck PASS; CP15 build PASS; CP16 metadatos internos PASS; CP17 diff check PASS.

---

## 5. Límites de esta revisión

- **Concurrencia MariaDB real no probada.** La suite usa SQLite en memoria, donde `lockForUpdate` no bloquea. La serialización efectiva entre solicitudes del mismo autorizador, el orden de locks frente a revocaciones concurrentes y la ausencia de deadlocks quedan pendientes de validación predespliegue contra una instancia MariaDB aislada autorizada. No se afirma concurrencia MariaDB probada.
- No se ejecutaron migraciones, seeders, despliegues ni operaciones sobre BD remota; no se hizo commit ni push.
- No se verificó CI de GitHub (no hay commit en esta revisión).
- No se evaluó endpoint/UI porque el packet no los incluye.
- El seam de pruebas ejecuta la revocación dentro de la misma transacción del servicio (al dispararse `TransactionBeginning`); prueba la revalidación real contra datos cambiados, pero no la visibilidad entre transacciones concurrentes, que es justamente el punto pendiente de MariaDB.

---

## 6. Veredicto final

**APROBADO** — 0 bloqueantes, 2 no bloqueantes y 3 observaciones.

El FIX AUTH_TOCTOU cumple el objetivo: la política se revalida de forma autoritativa dentro de la transacción y después de locks, la evaluación gobierna la inserción del AO, las denegaciones transaccionales no crean AO y se auditan con `is_security_event=1`, se preservan los contadores/lockout y la atomicidad AO+auditoría, y las pruebas de revocación ejercitan la revalidación real. Sin ampliación de alcance ni cambios en archivos protegidos.

Este veredicto **no es `VERIFIED`**, no autoriza despliegue ni BD remota, y deja explícitamente pendiente la validación de concurrencia MariaDB antes del predespliegue.