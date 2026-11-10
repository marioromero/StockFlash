# USERS-001 — Administración de usuarios, roles y asignación a sucursales

status: DRAFT
owner_approval: Pendiente de aprobación expresa del propietario. No implementar hasta pasar a APPROVED.
etapa: R3_CORE (CORE-03)
dependencias: AUTH-001 (VERIFIED 2026-11-09: autenticación, sesiones y RBAC server-side), BRANCH-001 (VERIFIED 2026-11-10: sucursales y `EnsureBranchAccess` con sucursal activa), DB-001 (tablas `users`, `roles`, `permissions`, `role_permissions`, `branch_user`), `config/modules.php` (permiso `users.manage`).
responsable_implementacion: OpenCode conforme al harness vigente; owner + ChatGPT delimitan alcance y declaran VERIFIED.

## Qué / Por qué / No incluido

**Qué.** CRUD administrativo de usuarios con rol global o por sucursal, activación/desactivación, PIN de autorización (solo hash), topes propios de descuento y devolución, asignación de roles por sucursal y sucursal inicial. Incluye rutas, controlador, FormRequests, pantallas Vue/Inertia responsive en es-CL, validaciones, permisos server-side, auditoría transaccional y pruebas de aceptación.

**Por qué.** CORE-03 es P0 y hoy solo existe `admin:create` y usuarios de prueba creados por tests. No hay forma segura de administrar cuentas reales, asignar roles por sucursal ni desactivar accesos desde la aplicación.

**No incluido.**
- Autenticación, login/logout, cambio de contraseña propio y motor RBAC: ya cubiertos por AUTH-001.
- CRUD de roles personalizados: CORE-16 (P2), fuera de este packet. Los 5 roles de sistema son de solo lectura.
- Edición de la matriz de permisos (`role_permissions`) o de `config/modules.php`: fuera de alcance.
- MFA/TOTP (SEC-03, P1?), anonimización (PRV-09, P1), autorizaciones por PIN (CORE-11), vendedores (SEL-01), `PlanGuard` completo (CORE-07), onboarding (CORE-02), importación masiva, restablecimiento por correo y restauración de eliminados.
- Borrado físico de usuarios con historial y restauración: fuera de alcance.
- Vistas de auditoría (`audit.view`): se registra auditoría, pero no se construye el visor.

## Referencias precisas

- **CORE-03** (Parte 1 §9.2): CRUD de usuarios con rol global o por sucursal, activar/desactivar, PIN, tope de descuento y de devolución propios.
- **CORE-04** (Parte 1 §9.2): RBAC único de 5 roles de sistema; permisos por módulo; verificación en middleware y políticas.
- **RN-02**: el tope efectivo de descuento es el mínimo entre producto, cliente y operador (usuario, si no su rol). USERS-001 solo captura los topes del usuario.
- **SEC-01**: contraseñas de 15 a 64 caracteres, sin reglas artificiales de composición.
- **SEC-06**: autorización en backend en cada acción y pruebas automáticas rol × permiso × ruta (403 e IDOR).
- **SEC-09**: eventos de seguridad en `audit_logs` (`is_security_event`): alta/baja de usuarios y cambios de rol, entre otros.
- **SEC-12**: PIN de autorización con hash, bloqueo por intentos y nunca en registros. USERS-001 solo almacena el hash; el flujo de autorización pertenece a CORE-11.
- **Parte 1 §6.1–6.3**: 5 roles (`seller`, `cashier`, `warehouse_keeper`, `supervisor`, `central_admin`), autoridad, usuario global (`users.global_role_id`) vs usuario por sucursal (`branch_user`), permisos efectivos por rol. No hay permisos directos a usuarios.
- **Parte 2 §4.7–4.11**: `roles`, `permissions`, `role_permissions`, `users` (SD, PII), `user_mfa_recovery_codes` (P1) y `branch_user` (PK `(user_id, branch_id)`).
- **DB-001**: esquema ya migrado; no se crean tablas ni columnas nuevas.
- **AUTH-001**: `users.manage` ya existe y `CheckPermission` lo verifica server-side; `VerifySession` invalida sesiones de cuentas desactivadas.
- **BRANCH-001**: las sucursales inactivas responden 403 en rutas operativas; la administración de sucursales no usa `branch.access`.

## Contratos / casos límite

- **Email:** obligatorio, válido, máximo 150, único sin distinguir mayúsculas/minúsculas. Se normaliza a minúsculas antes de guardar y de validar. Un email de usuario eliminado lógicamente sigue reservado por el índice UNIQUE.
- **Contraseña:** en creación es obligatoria, 15–64 caracteres, sin composición artificial (SEC-01). El administrador define una contraseña temporal y se marca `must_change_password=1`. En edición es opcional; si se informa, se aplica la misma regla y se marca `must_change_password=1`. Nunca se devuelve, registra ni audita en claro.
- **PIN:** opcional; si se informa, 4–8 dígitos, se guarda solo `pin_hash`, se devuelve únicamente “configurado/no configurado” y puede limpiarse. No se implementa aquí la ventana de autorización por PIN.
- **Acceso global vs por sucursal:** `global_role_id` no nulo = usuario global (rol aplica en todas las sucursales) y **no** lleva asignaciones en `branch_user`. Sin rol global = usuario por sucursal, con una fila por sucursal asignada.
- **Rol global:** debe ser un rol de sistema existente. `central_admin` solo puede asignarse como rol global, no como rol por sucursal.
- **Roles por sucursal:** `seller`, `cashier`, `warehouse_keeper`, `supervisor`. Un usuario por sucursal sin filas en `branch_user` no tiene acceso operativo; la pantalla lo advierte.
- **Sucursal inicial:** opcional. Si se informa, debe existir, estar activa y —para usuario por sucursal— estar entre las sucursales asignadas. El usuario global puede tener sucursal inicial sin asignaciones.
- **Topes propios:** `max_discount_percent` nulo o 0–100; `max_return_amount` nulo o ≥ 0. Nulo = hereda el del rol (RN-02). No se exige que sean menores que el rol.
- **Activación/desactivación:** solo por acciones dedicadas, no desde la edición. No se puede desactivar ni eliminar la propia cuenta. No se puede desactivar ni eliminar al último usuario activo con `users.manage`.
- **Eliminación:** borrado lógico (`soft delete`) únicamente. El email queda reservado; no hay restauración en este packet. Antes de eliminar se deja la cuenta inactiva para invalidar sesiones en la siguiente petición.
- **Sesiones:** `VerifySession` ya expulsa a un usuario inactivo. No se promete revocación inmediata de sesiones ya abiertas por cambio de contraseña; se documenta como limitación.
- **Límite de plan mínimo:** al crear o activar, validar `license_states.max_users` con `DB::transaction` + `lockForUpdate()`. `0` o sin fila = ilimitado. La concurrencia real se valida en MariaDB DEV; en SQLite la prueba es secuencial.
- **Auditoría:** eventos `user.created`, `user.updated`, `user.deactivated`, `user.activated`, `user.deleted`, `user.role_changed`, `user.branch_assigned`, `user.branch_unassigned`, `user.password_reset`, `user.pin_reset`. Se escriben **dentro de la misma transacción** de la operación; si la auditoría falla, todo se revierte. Nunca se auditan contraseñas, PIN, hashes ni secretos. Los eventos de alta/baja, cambio de rol, activación/desactivación y reseteos llevan `is_security_event=1`.
- **IDOR:** todas las rutas de administración usan `users.manage` a nivel global; no hay gestión de usuarios por sucursal ni acceso de un usuario sin permiso a datos de otro.
- **Concurrencia:** la protección del último administrador y el límite de usuarios se verifican con bloqueo de fila dentro de la transacción; prueba secuencial obligatoria en CI.

## Aceptación (EARS)

- CUANDO el Admin Central abre `/users`, EL SISTEMA DEBE listar usuarios con nombre, email, tipo de acceso (global/sucursal), estado, rol principal y sucursales asignadas.
- CUANDO el Admin Central busca por nombre o email, EL SISTEMA DEBE filtrar server-side y mantener la paginación.
- CUANDO el Admin Central crea un usuario con email válido y contraseña de 15–64 caracteres, EL SISTEMA DEBE crear la cuenta, marcar cambio de contraseña obligatorio y auditar `user.created`.
- CUANDO se intenta crear o editar con un email ya existente ignorando mayúsculas/minúsculas, EL SISTEMA DEBE rechazar con error de validación sin crear ni modificar nada.
- CUANDO se crea un usuario global con `central_admin`, EL SISTEMA DEBE guardar solo `global_role_id` y no aceptar asignaciones de sucursal.
- CUANDO se crea un usuario por sucursal, EL SISTEMA DEBE aceptar una o más filas únicas `(branch_id, role_id)` y rechazar sucursales duplicadas, inexistentes o inactivas.
- CUANDO un usuario por sucursal no tiene asignaciones, EL SISTEMA DEBE permitir guardarlo solo con advertencia explícita de que no tendrá acceso operativo.
- CUANDO se informa una sucursal inicial que no está asignada al usuario por sucursal, EL SISTEMA DEBE rechazar la operación.
- CUANDO se edita un usuario sin cambiar el email, EL SISTEMA DEBE permitir guardar sin colisionar consigo mismo.
- CUANDO se informa una contraseña nueva válida, EL SISTEMA DEBE rehasharla, marcarla como temporal y auditar `user.password_reset` sin registrar el valor.
- CUANDO se informa o limpia un PIN válido, EL SISTEMA DEBE actualizar solo `pin_hash` y auditar `user.pin_reset` sin exponer el valor.
- CUANDO se intenta modificar `is_active` desde la edición, EL SISTEMA DEBE rechazar la petición y exigir las acciones Activar/Desactivar.
- CUANDO el Admin Central desactiva a otro usuario, EL SISTEMA DEBE impedir el acceso en su siguiente petición y auditar `user.deactivated` con evento de seguridad.
- CUANDO el Admin Central intenta desactivarse o eliminarse a sí mismo, EL SISTEMA DEBE responder 403 y no cambiar nada.
- CUANDO se intenta desactivar o eliminar al último usuario activo con `users.manage`, EL SISTEMA DEBE rechazar la operación dentro de una transacción.
- CUANDO se elimina lógicamente un usuario, EL SISTEMA DEBE conservarlo, reservar su email, dejarlo inactivo y auditar `user.deleted`.
- CUANDO se alcanza `license_states.max_users`, EL SISTEMA DEBE rechazar crear o activar otro usuario sin estado parcial.
- CUANDO falla la escritura de auditoría en cualquier operación, EL SISTEMA DEBE revertir la operación completa.
- CUANDO un usuario sin `users.manage` accede a cualquier ruta de usuarios o intenta mutar por ID, EL SISTEMA DEBE responder 403 en backend.
- CUANDO el Admin Central usa un dispositivo móvil, EL SISTEMA DEBE mostrar listado y formulario usables, responsive y en español de Chile.

## Plan técnico mínimo por OpenCode

- **Rutas** (`routes/web.php`): `/users` (index), `/users/create`, `POST /users`, `/users/{user}/edit`, `PUT/PATCH /users/{user}`, `POST|PATCH /users/{user}/activate`, `POST|PATCH /users/{user}/deactivate`, `DELETE /users/{user}`, y actualización transaccional de asignaciones. Todas bajo `auth`, `password.change`, `verify.session` y `can.permission:users.manage`.
- **Backend:** `app/Http/Controllers/UserController.php`, `app/Http/Requests/UserRequest.php`, `app/Http/Requests/UserBranchAssignmentRequest.php` y un servicio acotado (`app/Support/UserAccessManager.php` o equivalente) para la transacción de último administrador y límite de plan.
- **Auditoría:** generalizar `app/Support/AuditLogger.php` para admitir `users` y `branches` sin duplicar lógica, con envoltorio compatible que preserve el comportamiento de BRANCH-001. Misma transacción y filtro de secretos existente.
- **Modelos:** usar `User`, `Role`, `BranchUser` y `Branch` de DB-001. Añadir relaciones/consultas estrictamente necesarias; no crear migraciones.
- **UI Inertia/Vue:** `resources/js/Pages/Users/Index.vue` y `resources/js/Pages/Users/Form.vue`; filtros, paginación, badges de estado, editor de asignaciones por sucursal, confirmaciones y advertencias; enlace de navegación visible solo con `users.manage`.
- **Compartición de props:** extender `HandleInertiaRequests` solo con lo necesario para mostrar el enlace y los catálogos de roles/sucursales, sin exponer datos sensibles ni autorizar desde la UI.
- **Pruebas:** `tests/Feature/Users/UserCrudTest.php`, `UserAuthorizationTest.php`, `UserBranchAssignmentTest.php`, `UserStateTransitionTest.php`, `UserAuditTest.php`, `UserLimitTest.php` y regresión de AUTH-001/BRANCH-001.
- **Documentación:** `documentacion/modulos/usuarios/administracion-usuarios.md` con los 10 puntos obligatorios y actualización de `documentacion/README.md`.
- **Sin cambios** en `docs/product/*`, constitución, branding, `AGENTS.md`, `opencode.json`, permisos globales, migraciones ni despliegues.

## Decisiones propuestas para aprobación única

1. **Permiso:** usar `users.manage` (solo Admin Central) ya existente; no crear permisos nuevos ni tocar la matriz/DB-001.
2. **Roles de sistema de solo lectura:** USERS-001 asigna roles existentes y muestra su referencia; no edita `roles`, `permissions` ni `role_permissions` (CORE-16 queda P2).
3. **Global XOR sucursal:** un usuario no puede tener `global_role_id` y filas en `branch_user` a la vez. Al pasar a global se eliminan las asignaciones; al pasar a por sucursal se limpia `global_role_id`.
4. **`central_admin` solo global:** no se permite como rol por sucursal.
5. **Cero asignaciones:** un usuario por sucursal puede guardarse sin asignaciones, con advertencia de que no tendrá acceso operativo.
6. **Sucursal inicial:** debe estar activa y, para usuario por sucursal, estar asignada.
7. **Contraseña temporal:** el administrador define la contraseña inicial/reseteada (15–64); se marca `must_change_password=1`; no se envía correo en este packet. No se revocan sesiones ya abiertas por cambio de contraseña (limitación documentada).
8. **PIN:** opcional, 4–8 dígitos, solo hash, sin recuperación ni visualización; el flujo de autorización por PIN es CORE-11.
9. **Topes propios:** 0–100 % de descuento y monto de devolución ≥ 0; nulos heredan del rol (RN-02).
10. **Estado y baja:** `is_active` solo por acciones dedicadas; no auto-desactivación ni auto-eliminación; no desactivar/eliminar al último usuario activo con `users.manage`; eliminación solo lógica.
11. **Límite de plan mínimo:** validar `license_states.max_users` en crear/activar con transacción y bloqueo; `0`/sin fila = ilimitado; `PlanGuard` completo queda para CORE-07.
12. **Auditoría transaccional:** eventos de usuario con `is_security_event=1` en alta/baja, rol, estado y credenciales; misma transacción y reversión si falla; sin secretos. Generalizar `AuditLogger` con regresión de BRANCH-001.
13. **Alcance de datos:** el listado no muestra PIN, contraseña, hashes ni secretos; muestra “PIN configurado” y metadatos no sensibles.

## Gates propuestos (se añadirán a `GATES_BY_PACKET` solo al aprobar)

- [ ] packet `APPROVED` y activo en `docs/work/CURRENT.md`.
- [ ] archivos de backend/UI/documentación presentes.
- [ ] rutas bajo `users.manage`, sin `branch.access`, y guard de auto-desactivación/último administrador.
- [ ] auditoría transaccional verificable (helper y pruebas de reversión).
- [ ] tests de usuarios: CRUD, validaciones, email case-insensitive, global XOR sucursal, asignaciones, topes, PIN sin exposición, 403/IDOR, estado, último administrador, límite de plan, soft delete, auditoría.
- [ ] regresión AUTH-001 (sesiones/RBAC) y BRANCH-001 (AuditLogger y sucursales) verde.
- [ ] suite completa, `npm run typecheck` y `npm run build`.
- [ ] reviewer independiente `APROBADO` sobre el estado exacto.
- [ ] documentación funcional en `/documentacion/modulos/usuarios/` e índice actualizado.

## Documentación funcional de usuario (obligatorio al implementar)

- Guía `documentacion/modulos/usuarios/administracion-usuarios.md` con los 10 puntos: nombre, para qué sirve, quién puede usarla, cómo acceder, cómo utilizarla, opciones, reglas y limitaciones, resultados esperados, errores frecuentes y ejemplos prácticos.
- Actualización de `documentacion/README.md` y enlaces internos válidos.
- Si alguna parte fuera puramente técnica, registrar `NO_APLICA` con justificación.

## Estado de funciones: implementado vs propuesto

- **Implementado y disponible como base:** tablas `users`, `roles`, `permissions`, `role_permissions` y `branch_user` (DB-001); login/logout, cambio de contraseña, sesiones, RBAC server-side y `admin:create` (AUTH-001); CRUD de sucursales, `EnsureBranchAccess` con sucursal activa y `AuditLogger` transaccional específico de sucursales (BRANCH-001); `users.manage` en `config/modules.php`.
- **Propuesto en USERS-001 (DRAFT, sin implementar):** rutas, controlador, FormRequests, servicio de guardas, pantallas Vue/Inertia, generalización del `AuditLogger`, pruebas y documentación descritas arriba.
- **Diferido:** MFA/TOTP, anonimización, autorización por PIN (CORE-11), roles personalizados (CORE-16), vendedores (SEL-01), `PlanGuard` completo (CORE-07), importación masiva, restablecimiento por correo, restauración de eliminados y visor de auditoría.
