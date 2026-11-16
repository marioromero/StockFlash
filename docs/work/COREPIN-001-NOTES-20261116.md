# COREPIN-001 — Notas técnicas (2026-11-16)

Feature: `docs/work/features/COREPIN-001.md` · CORE-11 / SEC-12 / RN-04–05 · ola única sin UI.

## Alcance implementado

- Servicio interno `App\Support\Authorization\PinAuthorizationService`, sin rutas, controladores, endpoints ni componentes de interfaz.
- Política común: solicitante desde la sesión (`Auth::id()`), sucursal activa, acceso del solicitante, autorizador activo/no eliminado, rol aplicable exclusivamente en la sucursal solicitada, permiso real `override.authorize` y `roles.authority_level >= system_configs.override_min_authority`.
- Verificación de PIN con `Hash::check`, longitud exacta desde `system_configs.pin_length` (default 4) y solo dígitos.
- Bloqueo temporal con `users.pin_locked_until = now() + 30 minutos` tras `system_configs.pin_max_attempts` fallos (default 5), coherente con el bloqueo de login vigente. Sin tablas, columnas ni migraciones nuevas.
- Persistencia append-only de una fila en `authorization_overrides` por aprobación exitosa, con auditoría atómica en `audit_logs` (`is_security_event=1`). Un fallo de auditoría revierte la autorización completa.
- Eventos de seguridad para rechazo de política, PIN fallido, lockout y aprobación, sin PIN ni hash.

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

`tests/Feature/Core/PinAuthorizationServiceTest.php`: 28 pruebas, 103 aserciones. Cubren permisos global/sucursal, autoridad insuficiente/suficiente, autorizador inactivo/eliminado, sucursal inactiva/no asignada, rol cruzado entre sucursales, PIN faltante/no numérico/longitud incorrecta, hash erróneo e incremento secuencial, cinco fallos y lockout, bloqueo con PIN correcto, expiración y reset, persistencia de contexto, reason_code inválido/inactivo/scope, requires_note, tipo inválido, sin sesión, solicitante inactivo, auditoría sin secretos, rollback por fallo de auditoría y configuración intacta.

Gates: `py -3 scripts/sf_verify.py COREPIN-001` (CP1–CP17).

## Limitaciones

- **Concurrencia MariaDB real no ejecutada (pendiente predespliegue):** la suite local usa SQLite en memoria; `lockForUpdate` y el bloqueo concurrente real entre solicitudes del mismo autorizador deben validarse contra una instancia MariaDB aislada autorizada antes del despliegue.
- No se implementa UI, endpoint, ruta, venta, caja, descuento ni emisión fiscal. El servicio solo entrega el ID de aprobación para que módulos futuros lo vinculen.
