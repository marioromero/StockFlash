# COREPIN-001 — Notas técnicas (2026-11-16)

Feature: `docs/work/features/COREPIN-001.md` · CORE-11 / SEC-12 / RN-04–05 · ola única sin UI.

## Alcance implementado

- Servicio interno `App\Support\Authorization\PinAuthorizationService`, sin rutas, controladores, endpoints ni componentes de interfaz.
- Política común: solicitante desde la sesión (`Auth::id()`), sucursal activa, acceso del solicitante, autorizador activo/no eliminado, rol aplicable exclusivamente en la sucursal solicitada, permiso real `override.authorize` y `roles.authority_level >= system_configs.override_min_authority`.
- Verificación de PIN con `Hash::check`, longitud exacta desde `system_configs.pin_length` (default 4) y solo dígitos.
- Bloqueo temporal con `users.pin_locked_until = now() + 30 minutos` tras `system_configs.pin_max_attempts` fallos (default 5), coherente con el bloqueo de login vigente. Sin tablas, columnas ni migraciones nuevas.
- Persistencia append-only de una fila en `authorization_overrides` por aprobación exitosa, con auditoría atómica en `audit_logs` (`is_security_event=1`). Un fallo de auditoría revierte la autorización completa.
- Eventos de seguridad para rechazo de política, PIN fallido, lockout y aprobación, sin PIN ni hash.
- **Revalidación transaccional autoritativa (cierre NB-1 AUTH_TOCTOU):** la política completa se vuelve a evaluar DENTRO de la misma `DB::transaction`, después de adquirir locks, y esa evaluación es la que decide la inserción del AO. La prevalidación previa se conserva solo como rechazo temprano.

## Revalidación transaccional y orden de locks

Dentro de `PinAuthorizationService::lockAndRevalidatePolicy()` se bloquean filas concretas con `lockForUpdate()` en este orden estable y documentado:

1. `users` del solicitante y del autorizador, ordenados por `id` ascendente.
2. `branch_user` aplicables a la sucursal solicitada, orden `(user_id, branch_id)`.
3. `roles`, `permissions` (`override.authorize`) y `role_permissions` del rol aplicable.
4. `branches` de la sucursal solicitada.
5. `pos_stations` si se informó estación.
6. `reason_codes` si se informó motivo.
7. `system_configs` relevantes (`override_min_authority`, `pin_max_attempts`), ordenados por `config_key`.

Sobre esas filas bloqueadas se revalida: solicitante autenticado/activo/no eliminado con acceso a la sucursal (rol global o `branch_user` de ESA sucursal); sucursal existente/no eliminada/activa; autorizador existente/activo/no eliminado; rol aplicable solo global o de esa sucursal; permiso real `override.authorize`; `roles.authority_level >= override_min_authority` (fallback 50); y, si vienen informados, `pos_station_id` (existencia, pertenencia a la sucursal, no eliminado) y `reason_code` (existencia, activo, scope `override`, `requires_note`). Una denegación aquí se audita dentro de la transacción (`authorization_override.denied`, `is_security_event=1`) y no crea fila AO.

## Prueba de revocación entre prevalidación e inserción

Sin hooks en código de producción, las pruebas escuchan `Illuminate\Database\Events\TransactionBeginning` una sola vez y, al iniciar la transacción real del servicio, revocan el permiso, la asignación de sucursal o desactivan el `reason_code` en BD. La revalidación transaccional real —no una política falsa— debe rechazar. Cubierto en `tests/Feature/Core/PinAuthorizationServiceTest.php`:

- permiso `override.authorize` revocado → `authorizer_permission_denied`;
- asignación `branch_user` del autorizador eliminada → `authorizer_role_missing`;
- `reason_code` desactivado → `reason_code_invalid`.

Cada caso verifica 0 filas en `authorization_overrides`, auditoría de denegación persistida con `is_security_event=1`, ausencia de `authorization_override.approved` y contador de PIN sin incrementar con PIN correcto.

## Decisiones de contrato

- **Convención de rechazo única:** toda negativa lanza `App\Support\Authorization\AuthorizationDeniedException` con un código estable (`reason`) y mensaje es-CL sin secretos. No se mezcla con `ValidationException`.
- **Formato de PIN:** faltante, no numérico o longitud distinta de `pin_length` se rechaza sin incrementar `pin_failed_attempts` ni bloquear.
- **PIN incorrecto:** se serializa por autorizador con `lockForUpdate` dentro de `DB::transaction`; el contador y la auditoría se confirman antes de lanzar la excepción para evitar rollback de evidencia.
- **Bloqueo:** al alcanzar el máximo se fija `pin_locked_until` a 30 minutos y se registra evento de lockout adicional. Un autorizador bloqueado se rechaza aun con PIN correcto.
- **Permiso por sucursal:** si el autorizador tiene `global_role_id`, ese rol aplica en todas las sucursales; si no, solo la fila `branch_user` de la sucursal solicitada. Nunca se usa el rol de otra sucursal.
- **Autoautorización:** permitida si el mismo usuario cumple PIN, rol, permiso y autoridad, conforme al contrato aprobado.
- **Auditoría:** `new_values` de aprobación contiene solo datos de negocio no sensibles (tipo, IDs, referencia, `reason_code_id`, `reason`, `old_value`/`new_value`). Los valores antiguo/nuevo son datos de negocio autorizados por el packet, no secretos. Nunca se registran PIN ni hash.
- **Contexto:** `pos_station_id` debe existir y pertenecer a la sucursal; `reference_type` máximo 50 caracteres; `old_value`/`new_value` máximo 100; `reason` máximo 255. `reason_code_id` debe existir, estar activo y tener scope `override`; `requires_note=1` exige motivo no vacío.
- **No reutilización:** cada éxito crea una fila nueva y devuelve `authorization_override_id`; no se reutilizan aprobaciones previas ni se concede elegibilidad comercial adicional.

## Archivos

- `app/Support/Authorization/PinAuthorizationService.php`
- `app/Support/Authorization/AuthorizationDeniedException.php`
- `tests/Feature/Core/PinAuthorizationServiceTest.php`
- `scripts/sf_verify.py` (gates COREPIN-001)
- `docs/work/GUIA-HTML-ESTADO.md` (metadato interno)
- `docs/work/COREPIN-001-NOTES-20261116.md`

## Pruebas

`tests/Feature/Core/PinAuthorizationServiceTest.php`: 31 pruebas, 120 aserciones. Cubren permisos global/sucursal, autoridad insuficiente/suficiente, autorizador inactivo/eliminado, sucursal inactiva/no asignada, rol cruzado entre sucursales, PIN faltante/no numérico/longitud incorrecta, hash erróneo e incremento secuencial, cinco fallos y lockout, bloqueo con PIN correcto, expiración y reset, persistencia de contexto, reason_code inválido/inactivo/scope, requires_note, tipo inválido, sin sesión, solicitante inactivo, auditoría sin secretos, rollback por fallo de auditoría, configuración intacta y revalidación transaccional tras revocación de permiso, asignación de sucursal o reason_code.

Gates: `py -3 scripts/sf_verify.py COREPIN-001` (CP1–CP17).

## Limitaciones

- **Concurrencia MariaDB real no ejecutada (pendiente predespliegue):** la suite local usa SQLite en memoria, donde `lockForUpdate` no impone bloqueo real. La serialización efectiva entre solicitudes concurrentes del mismo autorizador, el orden de locks frente a revocaciones concurrentes y la ausencia de deadlocks deben validarse contra una instancia MariaDB aislada autorizada antes del despliegue. No se afirma concurrencia MariaDB probada.
- No se implementa UI, endpoint, ruta, venta, caja, descuento ni emisión fiscal. El servicio solo entrega el ID de aprobación para que módulos futuros lo vinculen.
