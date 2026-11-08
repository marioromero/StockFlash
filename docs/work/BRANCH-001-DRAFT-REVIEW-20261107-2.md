# BRANCH-001 - Revision QA del DRAFT actualizado (2026-11-07, ronda 2)

- Fecha: 2026-11-07
- Revisor: `stockflash-reviewer`
- Rama: `main`
- HEAD: `970775e8c9411159c2caa6e209440e4c0265750a`
- Estado revisado: BRANCH-001 en **DRAFT**, no implementado.
- Veredicto: **DRAFT LISTO PARA APROBACIÓN DEL PROPIETARIO — no implementado, no VERIFIED**.

No se modifica codigo, rutas, pruebas, migraciones ni `.env`. No se modifica el artefacto anterior `docs/work/BRANCH-001-DRAFT-REVIEW-20261107.md`.

## Hashes revisados

| Archivo | SHA-1 de objeto Git |
|---|---|
| HEAD | `970775e8c9411159c2caa6e209440e4c0265750a` |
| `docs/work/features/BRANCH-001.md` (working tree) | `602d46e678dea398b0b9fec7fcc115c73ae3a95d` |
| `docs/work/features/BRANCH-001.md` (HEAD) | `46bfa826276260944f244451e618101ae9dd640e` |
| `docs/work/BRANCH-001-DRAFT-REVIEW-20261107.md` | `c5fd05dc81fdcf21ced39ec9d564e6937fd98f21` |
| `docs/work/CURRENT.md` | `0d175f25c347731e0ecef643554def1c8ae7c7c7` |
| `docs/work/AUTH-001-VERIFY-20261107-213531.md` | `a5e0d2d580929152498808722dba3167e8727b32` |

## Resultado de las 3 observaciones bloqueantes

### 1. `is_active=0` sin enforcement planificado - CERRADA

El DRAFT ahora:

- Amplia `app/Http/Middleware/EnsureBranchAccess.php` para exigir `is_active=1` ademas de existencia y no soft-deleted, incluso para usuario global.
- Declara que `/branches` es administracion global y no usa `branch.access`, por lo que un Admin Central puede listar y editar sucursales inactivas.
- Incluye aceptacion EARS: sucursal desactivada responde 403 en rutas operativas, incluso para global; dashboard global y `/branches` siguen accesibles.
- Incluye el cambio en plan tecnico, archivos previstos, pruebas de `sucursal desactivada 403 en branch.access` y gate de regresion de la suite AUTH-001.

Verificacion de no regresion: `/dashboard` no usa `branch.access`; la suite AUTH-001 pasa completa (45 passed / 108 assertions). El cambio solo afecta rutas con contexto de sucursal y esta declarado como decision sujeta a aprobacion.

### 2. Concurrencia de `license_states.max_branches` no resuelta - CERRADA

El DRAFT ahora:

- Define `DB::transaction` para creacion/reactivacion.
- Bloquea la fila de `license_states` con `lockForUpdate()` cuando hay limite y cuenta las sucursales activas dentro de la misma transaccion.
- Mantiene la unicidad de `code` en base de datos y exige capturar la violacion de clave unica sin estado parcial.
- Define prueba secuencial obligatoria en CI y declara que la concurrencia real contra MariaDB DEV es validacion diferida de implementacion, porque `lockForUpdate` es no-op en SQLite.
- Incluye aceptacion EARS de dos creaciones compitiendo por el ultimo cupo y gate de pruebas de limite de plan.

La prueba concurrente real no es ejecutable de forma fiable en SQLite CI; queda explicitamente diferida a MariaDB DEV, tal como el criterio de cierre solicitado permite. La logica secuencial del limite si es verificable en CI.

### 3. Decisiones operativas no listadas - CERRADA

El DRAFT incorpora como decisiones expresas:

- Codigo de sucursal soft-deleted: queda reservado por UNIQUE, no se reutiliza; reactivacion/restauracion queda para un packet posterior.
- Desactivar la ultima sucursal activa: permitido con confirmacion y advertencia; operaciones con sucursal responden 403; dashboard y administracion siguen accesibles.
- Sin fila en `license_states`: se trata como ilimitado en BRANCH-001; validacion completa de licencia queda para CORE-08 y `PlanGuard` completo para CORE-07.

Estas decisiones aparecen en contratos, aceptacion EARS, plan y lista de decisiones; ya no quedan como supuestos implicito.

## Consistencia DB-001 / RBAC / v2.2

- No se crean permisos nuevos ni migraciones: el diff solo modifica `docs/work/features/BRANCH-001.md`.
- `settings.manage` existe en `config/modules.php` (modulo `users`, linea 20) y el seeder lo asigna a `central_admin`.
- No existe permiso `branches.*`; el packet mantiene `settings.manage`.
- `branches`, `license_states` y `audit_logs` existen en DB-001; no se requieren migraciones.
- `audit_logs` ya tiene `module_code`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, IP y user-agent; `AuditLogger` se propone como helper minimo sobre esa tabla, sin dependencia externa.
- `SystemConfigsSeeder` contiene `sales_flow_mode`, `seller_code_policy` y `allow_negative_stock`, por lo que la herencia `NULL` desde `system_configs` es verificable.
- `App\Models\Branch` usa `SoftDeletes`; coherente con Parte 2 4.6.
- Las decisiones abiertas ADR-01..06 no bloquean este CRUD; ADR-01 de ventas mensuales no aplica.
- La exigencia de sucursal activa no rompe el dashboard global: `/dashboard` no usa `branch.access`. La suite AUTH-001 completa queda verde. El cambio de comportamiento para sucursales inactivas en rutas operativas queda declarado como decision 8 sujeta a aprobacion.

## Constancia de no implementacion

- `BRANCH-001.md` conserva `status: DRAFT` y `owner_approval: Pendiente...`.
- `git status` solo muestra `docs/work/features/BRANCH-001.md` modificado; no hay cambios en `app/`, `routes/`, `tests/`, `resources/`, `database/` ni `config/`.
- No existen `BranchController`, `BranchRequest`, `resources/js/Pages/Branches/`, `tests/Feature/Branches/` ni `app/Support/AuditLogger.php`.
- `routes/web.php` no contiene rutas `/branches`.
- No hay migraciones ni seeders nuevos.

## Decisiones sujetas a la aprobacion unica del propietario

1. Usar `settings.manage` (Admin Central), sin crear `branches.manage`.
2. Codigo inmutable despues de crear la sucursal.
3. Solo borrado logico, sin borrado fisico ni restauracion en este packet.
4. Codigo de sucursal soft-deleted reservado y no reutilizable.
5. Permitir desactivar la ultima sucursal activa con confirmacion/advertencia y 403 operativo.
6. Sin fila en `license_states` = ilimitado en BRANCH-001.
7. Limite de plan minimo y atomico con transaccion y `lockForUpdate()`; prueba secuencial obligatoria y concurrencia real MariaDB DEV diferida.
8. Sucursal activa obligatoria en operaciones mediante `EnsureBranchAccess`; `/branches` no usa `branch.access`.
9. Auditoria basica mediante helper `app/Support/AuditLogger.php`.

## Pruebas ejecutadas

| Comando | Resultado real |
|---|---|
| `php artisan test tests/Feature/Auth` | **45 passed (108 assertions)** |
| `php artisan test` | **47 passed (112 assertions)** |

Estas pruebas validan el codigo existente y la no regresion de AUTH-001. No existen pruebas de BRANCH-001 porque el packet no esta implementado.

## Observaciones no bloqueantes

1. La concurrencia real no se puede probar de forma fiable en SQLite CI; el packet la difiere a MariaDB DEV y mantiene prueba secuencial obligatoria. Limitacion conocida y declarada, no bloqueante.
2. Definir si la escritura de `AuditLogger` ocurre dentro de la misma transaccion y si un fallo de auditoria revierte la operacion.
3. El cambio de `EnsureBranchAccess` altera el acceso de usuario global a sucursales inactivas en rutas operativas; es intencional y esta sujeto a la decision 8. El dashboard global no se ve afectado.
4. `app/Support/AuditLogger.php` todavia no existe; es parte del plan futuro, no de una implementacion actual.

## Addendum 2026-11-07

Delta posterior revisado: el implementador actualizo solo `docs/work/CURRENT.md` para reflejar el DRAFT revisado de BRANCH-001. `docs/work/features/BRANCH-001.md` conserva el hash `602d46e678dea398b0b9fec7fcc115c73ae3a95d`; no hubo cambios en `app/`, `routes/`, `tests/`, `resources/`, `database/` ni `config/`.

- `docs/work/CURRENT.md` nuevo hash: `0d175f25c347731e0ecef643554def1c8ae7c7c7`.
- La linea de BRANCH-001 registra el DRAFT revisado listo para una sola aprobacion, el veredicto de esta revision, las 3 observaciones cerradas, la no implementacion y que AUTH-001 permanece sin cambios y sin VERIFIED.
- No se detectan SHA ni resultados inventados; el unico delta es la linea documental de BRANCH-001 en `CURRENT.md`.
- El veredicto **DRAFT LISTO PARA APROBACIÓN DEL PROPIETARIO — no implementado, no VERIFIED** se mantiene para el estado exacto con `CURRENT.md` actualizado. No se declara VERIFIED ni se aprueba implementacion.

## Veredicto

**DRAFT LISTO PARA APROBACIÓN DEL PROPIETARIO — no implementado, no VERIFIED**

Las tres observaciones bloqueantes quedaron cerradas conforme al criterio solicitado. No se detectan bloqueantes nuevos en DB-001, RBAC o v2.2. No se aprueba implementacion ni se declara VERIFIED.