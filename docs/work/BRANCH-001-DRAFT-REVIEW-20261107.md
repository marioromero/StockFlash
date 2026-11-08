# BRANCH-001 - Revision QA documental del DRAFT (2026-11-07)

- Fecha: 2026-11-07
- Revisor: `stockflash-reviewer`
- Rama: `main`
- HEAD: `55b7f16e761adef306957904f78b20a9effbe6e7`
- Estado revisado: BRANCH-001 en **DRAFT**, no implementado.
- Veredicto: **DRAFT CONSISTENTE CON OBSERVACIONES - no aprobado para implementacion.**

No se modifica codigo, rutas, pruebas, migraciones ni `.env`. Esta revision es documental y no aprueba la implementacion.

## Hashes revisados

| Archivo | SHA-1 de objeto Git |
|---|---|
| `docs/work/CURRENT.md` (working tree) | `e0982537d4fcabd6c561673b18c37c5994bfba29` |
| `docs/work/features/BRANCH-001.md` (untracked) | `46bfa826276260944f244451e618101ae9dd640e` |
| `docs/work/AUTH-001-REVIEW-20261107-2.md` | `31e4ad41f4d782c2ac4d12e39dceb5afda4ba5b9` |
| `docs/work/AUTH-001-VERIFY-20261107-213531.md` | `a5e0d2d580929152498808722dba3167e8727b32` |

## Constancia de DRAFT y no implementacion

- `BRANCH-001.md` declara `status: DRAFT` y `owner_approval: Pendiente de aprobacion expresa del propietario. No implementar hasta pasar a APPROVED.`
- `git status` solo muestra cambios documentales: `docs/work/CURRENT.md` modificado y `docs/work/features/BRANCH-001.md` sin seguimiento. No hay cambios en `app/`, `routes/`, `resources/`, `tests/`, `database/` ni `config/`.
- No existe `app/Http/Controllers/BranchController.php`, `app/Http/Requests/BranchRequest.php`, `resources/js/Pages/Branches/` ni `tests/Feature/Branches/`.
- `routes/web.php` no contiene rutas `/branches` ni referencias a `BranchController`.
- No hay migraciones ni seeders nuevos para BRANCH-001.
- `CURRENT.md` registra: "BRANCH-001: DRAFT preparada, no implementada... no se implemento codigo, rutas ni pruebas."

## Verificacion DB-001 / RBAC / v2.2

| Punto | Verificacion |
|---|---|
| Tabla `branches` | Existe migracion `2026_10_16_000006_create_branches_table.php`; campos `code` UNIQUE VARCHAR(30), `name` VARCHAR(150), `type` ENUM('store','warehouse','online'), direccion/comuna/ciudad/region/telefono, `is_active`, `requires_cash_control`, `sales_flow_mode`, `seller_code_policy`, `allow_negative_stock`, `settings` JSON y `deleted_at` coinciden con Parte 2 4.6. |
| `license_states.max_branches` | Existe migracion `2026_10_16_000004_create_license_states_table.php`; `max_branches` SMALLINT UNSIGNED default 0; Parte 2 4.4 lo define como limite de sucursales activas (`0` = ilimitado). |
| `audit_logs` | Existe migracion `2026_10_16_000019_create_audit_logs_table.php`; incluye `module_code`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, IP y user-agent; comentario AO-P (inmutable, solo `retention:prune`). |
| Permiso `settings.manage` | Existe en `config/modules.php` linea 20 dentro del modulo `users`. `RolesAndPermissionsSeeder` sincroniza los permisos desde `config/modules.php` y asigna todos los modulos a `central_admin`. La matriz Parte 1 6.3 lo marca solo para Admin. |
| Permiso `branches.*` | No existe en `config/modules.php` ni en la matriz. El packet no crea permisos nuevos. |
| RBAC server-side | El packet usa el middleware existente `can.permission:settings.manage`, coherente con AUTH-001 y con "sin permisos asignados directamente a usuarios". |
| Migraciones nuevas | No se requieren: `branches`, `license_states` y `audit_logs` ya existen en DB-001. |
| Herencia de ajustes | `SystemConfigsSeeder` contiene `sales_flow_mode`, `seller_code_policy` y `allow_negative_stock`, por lo que el `NULL` = heredar de `system_configs` es verificable. |
| Soft delete | `App\Models\Branch` usa `SoftDeletes`; coherente con Parte 2 4.6 (SD). |
| Decisiones abiertas | ADR-01..06 de `docs/governance/DECISIONES-ABIERTAS-v2.2.md` no bloquean este CRUD. ADR-01 (computo de ventas mensuales) no aplica a sucursales. |

## Decisiones sujetas a aprobacion del propietario

El packet las declara expresamente sujetas a aprobacion; esta revision no las aprueba:

1. Usar `settings.manage` (Admin Central) como guard de autorizacion, sin crear `branches.manage`.
2. Hacer inmutable `branches.code` despues de crear la sucursal.
3. Usar solo borrado logico (`SoftDeletes`), sin borrado fisico ni restauracion.
4. Aplicar una verificacion minima de `license_states.max_branches` al crear/reactivar, dejando el `PlanGuard` completo para CORE-07.
5. Registrar auditoria basica en `audit_logs` para el CRUD.

Ademas, el packet contiene supuestos operativos que no estan en la lista anterior y que conviene incorporar a la aprobacion o eliminar: permitir desactivar la ultima sucursal activa, no aplicar limite cuando no existe fila en `license_states`, y reservar el `code` de una sucursal soft-deleted (la unicidad de DB sigue aplicando).

## Hallazgos

### Correcciones necesarias antes de pasar a APPROVED

1. **Desactivacion sin enforcement planificado.** El packet afirma que `is_active=0` impide nuevas operaciones y que las operaciones con sucursal responderan 403. El middleware actual `EnsureBranchAccess` solo valida existencia y soft delete; no valida `is_active`, y el plan de archivos no incluye modificarlo. Correccion: agregar la validacion de sucursal activa al middleware o a cada operacion con sucursal, incluirla en archivos previstos y pruebas, o acotar la aceptacion y diferir el enforcement con aprobacion explicita.
2. **Concurrencia del limite `max_branches` no resuelta.** Se exige que dos creaciones simultaneas no superen el limite, pero no se especifica transaccion, bloqueo (`lockForUpdate` sobre `license_states`) ni restriccion equivalente. Correccion: definir el mecanismo atomico y su prueba de concurrencia antes de implementar.
3. **Decisiones operativas no listadas.** "Desactivar la ultima sucursal activa", "sin fila de licencia no hay limite" y el destino del `code` de una sucursal eliminada son decisiones de comportamiento. Deben agregarse a la lista de aprobacion del propietario o eliminarse del packet.

### Observaciones no bloqueantes

1. `audit_logs` existe como tabla, pero no hay modelo/servicio de auditoria en `app/`. El packet deberia indicar donde se escribe la auditoria y los nombres de evento.
2. El CRUD de sucursales no usa `branch.access`; la frase "sin sucursal valida no hay operacion" aplica a operaciones con contexto de sucursal, no a la administracion global. Conviene aclararlo para evitar que se interprete como requisito de contexto para `/branches`.
3. `settings.manage` esta agrupado bajo el modulo `users` en `config/modules.php`; es el permiso aprobado y existe, pero el packet podria explicitar esa ubicacion.
4. La reutilizacion del `code` de una sucursal soft-deleted no esta definida; la unicidad de DB impedira reutilizarlo mientras la fila exista. Conviene documentar el comportamiento esperado.

## Pruebas ejecutadas

| Comando | Resultado real |
|---|---|
| `php artisan test tests/Feature/Auth` | **45 passed (108 assertions)** |
| `php artisan test` | **47 passed (112 assertions)** |

Estas pruebas validan AUTH-001 y confirman que el codigo existente no cambio. No existen pruebas de BRANCH-001 porque el packet no esta implementado.

## Verificacion de CURRENT

`CURRENT.md` refleja lo verificable: AUTH-001 publicado en `55b7f16`, gates `AUTH-001-VERIFY-20261107-213531.md` GATES_PASS, QA `AUTH-001-REVIEW-20261107-2.md` APROBADO, suite 47/112, correcciones de sesion/`must_change_password`/sucursal global/SEC-01 max 64/modelo `User`, BRANCH-001 DRAFT no implementada y VERIFIED pendiente externo. No declara AUTH-001 VERIFIED.

No fue posible verificar desde el repositorio local los runs de CI `42430883629` y `42430883632` ni el estado de PR #1: `gh` no esta disponible y la API de GitHub respondio 404 (repositorio privado o sin autenticacion). Si se verifico localmente que `dc4e018` no es ancestro de `main`, consistente con "no fusionado". Los workflows definen 3 jobs en `ci.yml` y 1 en `contract-check.yml`, por lo que "4 check-runs" es estructuralmente plausible.

## Veredicto

**DRAFT CONSISTENTE CON OBSERVACIONES - no aprobado para implementacion.**

No hay contradiccion grave con DB-001, RBAC o v2.2, y el packet no crea permisos ni migraciones. Antes de pasar a APPROVED deben resolverse las tres correcciones de consistencia indicadas y el propietario debe aprobar expresamente las decisiones listadas. No se declara VERIFIED.