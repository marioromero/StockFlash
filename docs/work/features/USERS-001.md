# USERS-001 — Administración de usuarios, roles y asignación a sucursales

status: VERIFIED
owner_approval: Aprobación expresa del propietario (2026-11-13) de las 16 decisiones y precisiones A/B. Publicación Git inicialmente retenida; posteriormente autorizada expresamente y ejecutada en 882f281668793116ea18db6ab31be53b7a312d76. El propietario aprobó después política permanente AUTO_GIT para correcciones y futuras features aprobadas. No autoriza staging, producción ni migraciones BD remotas.
execution_mode: ONE_WAVE_ALL_16
publication_mode: AUTO_GIT
revision_externa: 2026-11-10 — incorpora los 5 ajustes de la revisión externa (último administrador frente a cambios de rol/acceso, invalidación de sesiones SEC-05, PIN según `system_configs.pin_length`, `SoftDeletes` en `User`) y aclara que `max_users` limita usuarios activos.
etapa: R3_CORE (CORE-03)
dependencias: AUTH-001 (VERIFIED 2026-11-09: autenticación, sesiones y RBAC server-side), BRANCH-001 (VERIFIED 2026-11-10: sucursales y `EnsureBranchAccess` con sucursal activa), DB-001 (tablas `users`, `roles`, `permissions`, `role_permissions`, `branch_user`), `config/modules.php` (permiso `users.manage`).
responsable_implementacion: OpenCode conforme al harness vigente; propietario autoriza alcance; ChatGPT determina VERIFIED técnico con revisión independiente.

## Autorización única de ejecución — 2026-11-13

- **Una ola, las 16 decisiones completas:** ejecutar todos los EARS de USERS-001 y precisiones P1–P4 en este packet con implementer → gates `py -3 scripts/sf_verify.py USERS-001` → reviewer independiente, reintentos automáticos por defectos del alcance (hasta 3 ciclos). Una vez iniciado desde un único `ok` en OpenCode, NO solicitar aprobación intermedia de ChatGPT ni del propietario por subtarea ordinaria.
- **Resultado exigido:** reporte `GATES_PASS`, reviewer `APROBADO` y matriz explícita D01–D16 + P1–P4 con evidencia de pruebas para el estado exacto. Comunicar solo `RESULT` o `BLOCK` compacto vía SFH1. Cuando no se haya publicado código, informar `sha=LOCAL_UNPUBLISHED` y hashes/rutas locales reales, sin inventar SHA remoto.
- **Publicación Git incluida por autorización posterior:** `publication_mode: AUTO_GIT`. El ciclo de implementación, gates, QA y Git publisher se ejecuta sin pedir autorización Git adicional; correcciones ChatGPT por SFH1 siguen la misma regla. Primera publicación: `882f281668793116ea18db6ab31be53b7a312d76`. `ok` no habilita deploy, migración remota, cambios contractuales ni VERIFIED.
- **Guardas:** no cambiar `docs/product/*`, constitución, permisos generales ni contratos pendientes; no nuevas tablas/migraciones, BD remota, secretos ni producción. Autorizado solo ajuste de ausencia de licencia de BRANCH-001 descrito abajo. `public/docs/index.html` y `resources/roadmap/index.html` siguen siendo de ChatGPT: al acabar generar `GUIA_PENDIENTE_CHATGPT` y `ROADMAP_PENDIENTE_CHATGPT`, sin pausar la implementación para edición externa.
- **Bloqueo real:** conservar trabajo/checkpoints si aparece decisión material no resuelta, error de permisos o imposibilidad de pruebas; nunca fabricar PASS. Carrera real con MariaDB solo si existe una instancia LOCAL aislada y autorizada; si no, cubrir secuencial SQLite y reportar esa prueba de concurrencia como pendiente, sin afirmar que corrió.


## Revisión externa posterior a publicación — 2026-11-13

- Publicado en `882f281668793116ea18db6ab31be53b7a312d76`, QA OC APROBADO y CI remota PHP/Node/smoke/contratos SUCCESS. **Revisión externa ChatGPT detectó exposición de nombre y correo en datos de auditoría de cambios**: ver `docs/work/USERS-001-EXTERNAL-REVIEW-20261113.md`. Es un bloqueante para VERIFIED; OpenCode debe corregirlo al siguiente `ok` SFH1, repetir gates/QA y publicar nuevo SHA automáticamente bajo `AUTO_GIT`. No desplegar.

## Ajustes de la revisión externa (2026-11-10)

1. **Último administrador protegido también frente a cambios de rol o tipo de acceso.** No basta con impedir desactivar/eliminar al último usuario activo con `users.manage`: también se rechaza cualquier cambio de rol global, cambio a usuario por sucursal o reemplazo de asignaciones que le haga perder `users.manage`, dentro de una transacción y con pruebas.
2. **SEC-05: invalidar sesiones al restablecer contraseña o cambiar roles/accesos.** Se define un fingerprint de acceso por sesión, verificado en cada petición autenticada. Si cambia la contraseña, el rol global, las asignaciones por sucursal, la sucursal inicial o el estado, la sesión se invalida. Sin migraciones y compatible con la arquitectura de sesiones existente (`VerifySession`).
3. **PIN según `system_configs.pin_length`.** La longitud no es un rango fijo 4–8: se exige exactamente `pin_length` (predeterminado 4) dígitos, leído en servidor.
4. **`SoftDeletes` en `User`.** El modelo ya tiene la columna `deleted_at`; la implementación añadirá el trait y ajustará consultas/pruebas, sin crear migraciones.
5. **`max_users` limita usuarios activos.** El conteo para `license_states.max_users` considera únicamente usuarios con `is_active=1` y no eliminados lógicamente, conforme a la especificación v2.2.

## Qué / Por qué / No incluido

**Qué.** CRUD administrativo de usuarios con rol global o por sucursal, activación/desactivación, PIN de autorización (solo hash), topes propios de descuento y devolución, asignación de roles por sucursal y sucursal inicial. Incluye rutas, controlador, FormRequests, pantallas Vue/Inertia responsive en es-CL, validaciones, permisos server-side, auditoría transaccional y pruebas de aceptación.

**Por qué.** CORE-03 es P0 y hoy solo existen `admin:create`/`admin:setup` y usuarios de prueba creados por tests. No hay forma segura de administrar cuentas reales, asignar roles por sucursal ni desactivar accesos desde la aplicación.

**No incluido.**
- Autenticación, login/logout, cambio de contraseña propio y motor RBAC: ya cubiertos por AUTH-001.
- CRUD de roles personalizados: CORE-16 (P2), fuera de este packet. Los 5 roles de sistema son de solo lectura.
- Edición de la matriz de permisos (`role_permissions`) o de `config/modules.php`: fuera de alcance.
- MFA/TOTP (SEC-03, P1?), anonimización (PRV-09, P1), autorizaciones por PIN (CORE-11), vendedores (SEL-01), `PlanGuard` completo (CORE-07), onboarding (CORE-02), importación masiva, restablecimiento por correo y restauración de eliminados.
- Borrado físico de usuarios con historial y restauración: fuera de alcance.
- Vistas de auditoría (`audit.view`): se registra auditoría, pero no se construye el visor.

## Referencias precisas

- **CORE-03** (Parte 1 §8.1): CRUD de usuarios con rol global o por sucursal, activar/desactivar, PIN, tope de descuento y de devolución propios.
- **CORE-15** (Parte 1 §8.1): restablecimiento manual de contraseña por el Admin (SEC-04).
- **CORE-04** (Parte 1 §8.1): RBAC único de 5 roles de sistema; permisos por módulo; verificación en middleware y políticas.
- **RN-02**: el tope efectivo de descuento es el mínimo entre producto, cliente y operador (usuario, si no su rol). USERS-001 solo captura los topes del usuario.
- **SEC-01**: contraseñas de 15 a 64 caracteres, sin reglas artificiales de composición.
- **SEC-04**: restablecimiento por Admin de la contraseña, sin depender de correo en este packet.
- **SEC-05**: invalidar la sesión al cambiar contraseña o rol; inactividad 30/60 min y duración absoluta 12 h ya vigentes en AUTH-001.
- **SEC-06**: autorización en backend en cada acción y pruebas automáticas rol × permiso × ruta (403 e IDOR).
- **SEC-09**: eventos de seguridad en `audit_logs` (`is_security_event`): alta/baja de usuarios y cambios de rol, entre otros.
- **SEC-12**: PIN de autorización con hash, bloqueo por intentos y nunca en registros. USERS-001 solo almacena el hash; el flujo de autorización pertenece a CORE-11. La longitud se rige por `system_configs.pin_length` (predeterminado 4).
- **Parte 1 §6.1–6.3**: 5 roles (`seller`, `cashier`, `warehouse_keeper`, `supervisor`, `central_admin`), autoridad, usuario global (`users.global_role_id`) vs usuario por sucursal (`branch_user`), permisos efectivos por rol. No hay permisos directos a usuarios.
- **Parte 2 §4.7–4.11**: `roles`, `permissions`, `role_permissions`, `users` (SD, PII), `user_mfa_recovery_codes` (P1) y `branch_user` (PK `(user_id, branch_id)`).
- **DB-001**: esquema ya migrado; no se crean tablas ni columnas nuevas.
- **AUTH-001**: `users.manage` ya existe y `CheckPermission` lo verifica server-side; `VerifySession` invalida sesiones de cuentas desactivadas.
- **BRANCH-001**: las sucursales inactivas responden 403 en rutas operativas; la administración de sucursales no usa `branch.access`.

## Contratos / casos límite

- **Email:** obligatorio, válido, máximo 150, único sin distinguir mayúsculas/minúsculas. Se normaliza a minúsculas antes de guardar y de validar. La validación de unicidad consulta también usuarios eliminados lógicamente (`withTrashed`) y se prueba explícitamente; el índice UNIQUE conserva la reserva del email.
- **Contraseña:** en creación es obligatoria, 15–64 caracteres, sin composición artificial (SEC-01). El administrador define una contraseña temporal y se marca `must_change_password=1`. En edición es opcional; si se informa, se aplica la misma regla y se marca `must_change_password=1`. El restablecimiento por Admin (CORE-15/SEC-04) invalida las sesiones existentes del afectado mediante el fingerprint de acceso. Nunca se devuelve, registra ni audita en claro.
- **PIN:** opcional; si se informa, debe tener exactamente `system_configs.pin_length` dígitos (predeterminado 4; el valor se lee en servidor, no se fija en la UI). Se guarda solo `pin_hash`, se devuelve únicamente “configurado/no configurado” y puede limpiarse. No se implementa aquí la ventana de autorización por PIN.
- **Acceso global vs por sucursal:** `global_role_id` no nulo = usuario global (rol aplica en todas las sucursales) y **no** lleva asignaciones en `branch_user`. Sin rol global = usuario por sucursal, con una fila por sucursal asignada.
- **Rol global:** debe ser un rol de sistema existente. `central_admin` solo puede asignarse como rol global, no como rol por sucursal.
- **Roles por sucursal:** `seller`, `cashier`, `warehouse_keeper`, `supervisor`. Un usuario por sucursal sin filas en `branch_user` no tiene acceso operativo; la pantalla lo advierte.
- **Sucursal inicial:** opcional. Si se informa, debe existir, estar activa y —para usuario por sucursal— estar entre las sucursales asignadas. El usuario global puede tener sucursal inicial sin asignaciones.
- **Topes propios:** `max_discount_percent` nulo o 0–100; `max_return_amount` nulo o ≥ 0. Nulo = hereda el del rol (RN-02). No se exige que sean menores que el rol.
- **Activación/desactivación:** solo por acciones dedicadas, no desde la edición. No se puede desactivar ni eliminar la propia cuenta. No se puede desactivar ni eliminar al último usuario activo con `users.manage`, ni cambiar su rol global, convertirlo a usuario por sucursal o reemplazar sus asignaciones si eso le hace perder `users.manage`.
- **Protección del último administrador:** la comprobación cubre estado, rol global y asignaciones por sucursal. Dentro de la transacción se bloquean las filas de usuarios activos (`users` con `is_active=1`, vía `lockForUpdate()`) antes de contar y actualizar, de modo que dos degradaciones concurrentes se serialicen. Si el objetivo tiene `users.manage` (por rol global o por alguna asignación) y el cambio propuesto se lo quita, se cuenta cuántos otros usuarios activos y no eliminados conservarían `users.manage`; si el resultado es cero, se rechaza sin estado parcial. La auto-desactivación y la auto-eliminación están prohibidas siempre, aun si existe otro administrador. El auto-cambio de rol/acceso solo se permite cuando otro usuario activo conserva `users.manage`; de lo contrario se rechaza.
- **Eliminación:** borrado lógico (`soft delete`) únicamente. El modelo `User` debe usar `SoftDeletes` sobre la columna `deleted_at` ya existente; **sin migraciones**. El email queda reservado; no hay restauración en este packet. Antes de eliminar se deja la cuenta inactiva para invalidar sesiones en la siguiente petición.
- **Invalidación de sesiones (SEC-05):** sin migraciones ni cambios de driver. Al iniciar sesión se guarda en la sesión un **fingerprint de acceso** calculado con HMAC-SHA256 (clave de aplicación) o SHA-256 sobre componentes normalizados, nunca por concatenación cruda. Incluye id, hash de contraseña, `global_role_id`, `default_branch_id`, `is_active` y asignaciones `branch_id:role_id` ordenadas. El fingerprint resultante no se registra ni se expone. En cada petición autenticada, `VerifySession` recalcula el fingerprint desde la base de datos y lo compara; si difiere, cierra la sesión, la invalida y responde 403. Esto cubre restablecimiento de contraseña por Admin, cambio de rol global, cambio de rol/asignación por sucursal, cambio de sucursal inicial y desactivación. Si el fingerprint no existe (sesión previa al despliegue), se invalida y se exige un nuevo inicio de sesión.
- **Límite de plan mínimo:** `license_states.max_users` limita **usuarios activos** (con `is_active=1` y no eliminados lógicamente), conforme a la especificación v2.2. Al crear o activar, dentro de `DB::transaction` se bloquea la fila vigente de `license_states` con `lockForUpdate()` y luego se cuenta. Con una **fila de licencia registrada**, `max_users=0` significa ilimitado. **Sin fila**, bloquear crear/activar usuarios con error claro; jamás interpretar ausencia de licencia como ilimitado, ni bloquear por ello la consulta de datos existentes. Licencia suspendida fuera de gracia: respetar solo lectura RN-70; no inventar nueva política de vencimiento/gracia (ADR-05). El stub de licencia registrado en entorno DEV es válido para este control. La concurrencia real solo puede probarse en MariaDB local aislada y autorizada; en SQLite, prueba secuencial.
- **Auditoría:** eventos `user.created`, `user.updated`, `user.deactivated`, `user.activated`, `user.deleted`, `user.role_changed`, `user.branch_assigned`, `user.branch_unassigned`, `user.password_reset`, `user.pin_reset`, `user.access_revoked`. Se escriben **dentro de la misma transacción** de la operación; si la auditoría falla, todo se revierte. Nunca se auditan contraseñas, PIN, hashes ni secretos. Los eventos de alta/baja, cambio de rol/acceso, activación/desactivación y reseteos llevan `is_security_event=1`. **Lectura PII aprobada (PRV-06/CORE-10):** cada GET exitoso del listado y ficha de edición de usuarios genera un evento `user.list_viewed` / `user.record_viewed` ANTES de devolver datos, con `is_personal_data_access=1`, actor, fecha, IP, user-agent y solo ID del usuario consultado para ficha. No copiar nombres, correos ni listas de personas al evento. Los cambios de PII también marcan `is_personal_data_access=1`; si falla el log de lectura, no entregar datos personales. Un evento por consulta, no por fila.
- **IDOR:** todas las rutas de administración usan `users.manage` a nivel global; no hay gestión de usuarios por sucursal ni acceso de un usuario sin permiso a datos de otro.
- **Corrección acotada BRANCH-001 autorizada:** `BranchController::assertWithinBranchLimit()` debe bloquear crear/reactivar sucursales cuando NO existe `license_states`, con error claro; con fila registrada y `max_branches=0` permanece ilimitado. Sin variar el resto del CRUD. Añadir prueba de regresión y ajustar fixtures que asumían ausencia de licencia.
- **Concurrencia:** la protección del último administrador (incluidos cambios de rol/acceso) y el límite de usuarios se verifican con bloqueo de fila dentro de la transacción; prueba secuencial obligatoria en CI. La carrera real entre dos administradores se valida en MariaDB DEV.
- **SoftDeletes:** el trait se añade al modelo `User` reutilizando `deleted_at`; no se crean columnas ni migraciones. Las consultas de administración excluyen eliminados salvo que se necesite el email reservado; las pruebas cubren que un eliminado no puede autenticarse ni operar.


## Riesgos y controles

- **Sesiones previas al despliegue:** al no existir fingerprint, se invalidan y exigen nuevo login. Es un costo único aceptado y evita sesiones sin verificación SEC-05.
- **Cambios en la matriz de permisos:** el fingerprint cubre asignaciones de rol, no la edición de `role_permissions`; esa edición está fuera de USERS-001 y los roles son de sistema.
- **Concurrencia:** `lockForUpdate()` es no-op en SQLite. Las pruebas CI son secuenciales; la carrera real de dos administradores y la de creación/activación contra `max_users` se validan en MariaDB DEV. Para el último administrador se bloquean las filas de usuarios activos; es una serialización deliberada de escrituras administrativas de usuarios, aceptable para P0 y volúmenes objetivos.
- **Unicidad de email:** se normaliza y valida sin distinguir mayúsculas/minúsculas, incluyendo usuarios eliminados lógicamente (`withTrashed`), para no depender del collation del motor.
- **Regresión:** generalizar `AuditLogger`, tocar `VerifySession`/`LoginController` y añadir `SoftDeletes` exige regresión completa de AUTH-001 y BRANCH-001.
- **Credenciales:** el restablecimiento es manual y no envía correo; el administrador comunica la contraseña temporal por un canal seguro fuera del sistema.

## Aceptación (EARS)

- CUANDO el Admin Central abre `/users`, EL SISTEMA DEBE listar usuarios con nombre, email, tipo de acceso (global/sucursal), estado, rol principal y sucursales asignadas.
- CUANDO el Admin Central busca por nombre o email, EL SISTEMA DEBE filtrar server-side y mantener la paginación.
- CUANDO el Admin Central crea un usuario con email válido y contraseña de 15–64 caracteres, EL SISTEMA DEBE crear la cuenta, marcar cambio de contraseña obligatorio y auditar `user.created`.
- CUANDO se intenta crear o editar con un email ya existente ignorando mayúsculas/minúsculas, incluido un usuario eliminado lógicamente, EL SISTEMA DEBE rechazar con error de validación sin crear ni modificar nada.
- CUANDO se crea un usuario global con `central_admin`, EL SISTEMA DEBE guardar solo `global_role_id` y no aceptar asignaciones de sucursal.
- CUANDO se crea un usuario por sucursal, EL SISTEMA DEBE aceptar una o más filas únicas `(branch_id, role_id)` y rechazar sucursales duplicadas, inexistentes o inactivas.
- CUANDO un usuario por sucursal no tiene asignaciones, EL SISTEMA DEBE permitir guardarlo solo con advertencia explícita de que no tendrá acceso operativo.
- CUANDO se informa una sucursal inicial que no está asignada al usuario por sucursal, EL SISTEMA DEBE rechazar la operación.
- CUANDO se edita un usuario sin cambiar el email, EL SISTEMA DEBE permitir guardar sin colisionar consigo mismo.
- CUANDO se informa una contraseña nueva válida, EL SISTEMA DEBE rehasharla, marcarla como temporal, auditar `user.password_reset` sin registrar el valor e invalidar las sesiones existentes del afectado en su siguiente petición.
- CUANDO se informa o limpia un PIN válido, EL SISTEMA DEBE exigir exactamente `system_configs.pin_length` dígitos, actualizar solo `pin_hash` y auditar `user.pin_reset` sin exponer el valor.
- CUANDO se intenta modificar `is_active` desde la edición, EL SISTEMA DEBE rechazar la petición y exigir las acciones Activar/Desactivar.
- CUANDO el Admin Central desactiva a otro usuario, EL SISTEMA DEBE impedir el acceso en su siguiente petición y auditar `user.deactivated` con evento de seguridad.
- CUANDO el Admin Central intenta desactivarse o eliminarse a sí mismo, EL SISTEMA DEBE responder 403 y no cambiar nada.
- CUANDO se intenta desactivar o eliminar al último usuario activo con `users.manage`, EL SISTEMA DEBE rechazar la operación dentro de una transacción.
- CUANDO un cambio de rol global, conversión a usuario por sucursal o reemplazo de asignaciones quitaría `users.manage` al último usuario activo que lo posee, EL SISTEMA DEBE rechazar el cambio dentro de una transacción y sin estado parcial.
- CUANDO el Admin Central intenta cambiar su propio rol global o sus asignaciones y no queda otro usuario activo con `users.manage`, EL SISTEMA DEBE rechazar el cambio; si queda otro, DEBE permitirlo e invalidar sus sesiones en la siguiente petición.
- CUANDO el Admin Central cambia el rol global, las asignaciones por sucursal, la sucursal inicial o restablece la contraseña de un usuario, EL SISTEMA DEBE invalidar las sesiones existentes de ese usuario en su siguiente petición y auditar el cambio.
- CUANDO el Admin Central elimina lógicamente un usuario, EL SISTEMA DEBE conservarlo, reservar su email, dejarlo inactivo, auditar `user.deleted` y excluirlo de las consultas operativas por `SoftDeletes`.
- CUANDO se alcanza `license_states.max_users` contando solo usuarios activos y no eliminados, EL SISTEMA DEBE rechazar crear o activar otro usuario sin estado parcial; los usuarios inactivos no consumen cupo.
- CUANDO no existe fila `license_states`, EL SISTEMA DEBE impedir crear/activar usuarios y sucursales con mensaje claro, sin alterar registros ni impedir consultar los existentes; con fila y límite `0` DEBE respetar ilimitado.
- CUANDO la licencia está suspendida fuera de gracia, EL SISTEMA DEBE impedir nuevas altas/activaciones afectadas y permitir consulta según RN-70, sin implementar otras decisiones abiertas ADR-05.
- CUANDO el Admin Central abre el listado o la ficha de edición de un usuario, EL SISTEMA DEBE registrar un único evento de lectura PII por petición antes de mostrar datos, con identidad del consultante y sin copiar datos personales al evento; si la auditoría falla, no entregar datos.
- CUANDO se modifican datos personales de usuario, EL SISTEMA DEBE marcar el evento como cambio PII, manteniendo transacción.
- CUANDO falla la escritura de auditoría en cualquier operación, EL SISTEMA DEBE revertir la operación completa.
- CUANDO un usuario sin `users.manage` accede a cualquier ruta de usuarios o intenta mutar por ID, EL SISTEMA DEBE responder 403 en backend.
- CUANDO el Admin Central usa un dispositivo móvil, EL SISTEMA DEBE mostrar listado y formulario usables, responsive y en español de Chile.

## Plan técnico mínimo por OpenCode

- **Rutas** (`routes/web.php`): `/users` (index), `/users/create`, `POST /users`, `/users/{user}/edit`, `PUT/PATCH /users/{user}`, `POST|PATCH /users/{user}/activate`, `POST|PATCH /users/{user}/deactivate`, `DELETE /users/{user}`, y actualización transaccional de asignaciones. Todas bajo `auth`, `password.change`, `verify.session` y `can.permission:users.manage`. La ruta de prueba `/usuarios` de AUTH-001 se conserva en este packet para no romper su regresión; su reemplazo/limpieza se evaluará en un packet posterior.
- **Backend:** `app/Http/Controllers/UserController.php`, `app/Http/Requests/UserRequest.php`, `app/Http/Requests/UserBranchAssignmentRequest.php` y un servicio acotado (`app/Support/UserAccessManager.php` o equivalente) para las transacciones de último administrador (estado, rol global y asignaciones) y límite de plan. El servicio bloquea las filas de usuarios activos antes de contar administradores y la fila vigente de `license_states` antes de contar usuarios activos.
- **Sesiones SEC-05:** `app/Support/UserAccessFingerprint.php` para calcular/verificar el fingerprint; `LoginController` lo guarda al iniciar sesión y `VerifySession` lo recalcula en cada petición autenticada. Sin migraciones ni cambio de driver; fingerprint ausente = sesión invalidada.
- **Auditoría:** generalizar `app/Support/AuditLogger.php` para admitir `users` y `branches` sin duplicar lógica, con envoltorio compatible que preserve el comportamiento de BRANCH-001. Misma transacción y filtro de secretos existente. Log síncrono de lecturas PII de listado/ficha antes de responder; fallar cerrado sin entregar datos, sin PII en payload.
- **Licencia y sucursales:** comprobar existencia de fila de licencia en alta/activación de usuarios y su suspensión fuera de gracia conforme RN-70; corregir solo el caso sin fila de `BranchController::assertWithinBranchLimit()` para altas/reactivaciones de sucursales. Tests para sin fila, `0` ilimitado con fila, límite alcanzado, suspensión y regresiones; sin `PlanGuard` completo.
- **Modelos:** usar `User`, `Role`, `BranchUser` y `Branch` de DB-001. Añadir `use SoftDeletes` a `User` reutilizando `deleted_at` (sin migración) y las relaciones/consultas estrictamente necesarias; no crear tablas ni columnas.
- **UI Inertia/Vue:** `resources/js/Pages/Users/Index.vue` y `resources/js/Pages/Users/Form.vue`; filtros, paginación, badges de estado, editor de asignaciones por sucursal, confirmaciones y advertencias; enlace de navegación visible solo con `users.manage`.
- **Compartición de props:** extender `HandleInertiaRequests` solo con lo necesario para mostrar el enlace y los catálogos de roles/sucursales, sin exponer datos sensibles ni autorizar desde la UI.
- **Pruebas:** `tests/Feature/Users/UserCrudTest.php`, `UserAuthorizationTest.php`, `UserBranchAssignmentTest.php`, `UserStateTransitionTest.php`, `UserLastAdminTest.php`, `UserSessionInvalidationTest.php`, `UserAuditTest.php`, `UserLimitTest.php`, `tests/Feature/Branches/BranchLicenseGuardTest.php` y regresión de AUTH-001/BRANCH-001. `UserAuditTest` incluye lectura PII y fallo de log; `UserLimitTest` incluye licencia ausente/cero/suspendida. QA independiente deberá mapear D01–D16 a pruebas verificadas.
- **Documentación:** `documentacion/modulos/usuarios/administracion-usuarios.md` con los 10 puntos obligatorios y actualización de `documentacion/README.md`.
- **Sin cambios** en `docs/product/*`, constitución, branding, `AGENTS.md`, `opencode.json`, permisos globales, migraciones ni despliegues.

## Decisiones aprobadas para implementación única — 2026-11-13

1. **Permiso:** usar `users.manage` (solo Admin Central) ya existente; no crear permisos nuevos ni tocar la matriz/DB-001.
2. **Roles de sistema de solo lectura:** USERS-001 asigna roles existentes y muestra su referencia; no edita `roles`, `permissions` ni `role_permissions` (CORE-16 queda P2).
3. **Global XOR sucursal:** un usuario no puede tener `global_role_id` y filas en `branch_user` a la vez. Al pasar a global se eliminan las asignaciones; al pasar a por sucursal se limpia `global_role_id`.
4. **`central_admin` solo global:** no se permite como rol por sucursal.
5. **Cero asignaciones:** un usuario por sucursal puede guardarse sin asignaciones, con advertencia de que no tendrá acceso operativo.
6. **Sucursal inicial:** debe estar activa y, para usuario por sucursal, estar asignada.
7. **Contraseña temporal y sesiones:** el administrador define la contraseña inicial/reseteada (15–64); se marca `must_change_password=1`; no se envía correo en este packet. El restablecimiento invalida las sesiones existentes del afectado en su siguiente petición mediante el fingerprint SEC-05.
8. **PIN:** opcional, exactamente `system_configs.pin_length` dígitos (predeterminado 4), solo hash, sin recuperación ni visualización; el flujo de autorización por PIN es CORE-11.
9. **Topes propios:** 0–100 % de descuento y monto de devolución ≥ 0; nulos heredan del rol (RN-02).
10. **Estado y baja:** `is_active` solo por acciones dedicadas; no auto-desactivación ni auto-eliminación; no desactivar/eliminar al último usuario activo con `users.manage`; eliminación solo lógica con `SoftDeletes`.
11. **Límite de plan mínimo:** validar `license_states.max_users` sobre **usuarios activos y no eliminados** en crear/activar, bloqueando la fila vigente de `license_states` con `lockForUpdate()` dentro de la transacción; `0` significa ilimitado SOLO con licencia registrada; sin fila se bloquean altas/activaciones de usuarios y sucursales; licencia suspendida fuera de gracia = solo lectura RN-70. `PlanGuard` completo queda para CORE-07.
12. **Auditoría transaccional:** eventos de usuario con `is_security_event=1` en alta/baja, rol/acceso, estado y credenciales; misma transacción y reversión si falla; sin secretos. Incluir auditoría de lectura PII y de cambios personales con `is_personal_data_access=1` sin copiar PII; si falla el log de lectura, no entregar datos. Generalizar `AuditLogger` con regresión de BRANCH-001.
13. **Alcance de datos:** el listado no muestra PIN, contraseña, hashes ni secretos; muestra “PIN configurado” y metadatos no sensibles.
14. **Protección del último administrador ampliada:** además de estado y baja, se rechazan cambios de rol global, conversión a usuario por sucursal o reemplazo de asignaciones que quiten `users.manage` al último usuario activo que lo posee. La transacción bloquea las filas de usuarios activos antes de contar y actualizar, y tiene pruebas propias; el auto-cambio se permite solo si otro administrador activo permanece.
15. **Invalidación de sesiones SEC-05:** fingerprint de acceso en sesión calculado con HMAC-SHA256 (clave de aplicación) o SHA-256 sobre componentes normalizados, recalculado en `VerifySession` en cada petición; cubre contraseña, rol global, asignaciones por sucursal, sucursal inicial y estado. Sin migraciones ni cambio de driver; fingerprint ausente = sesión invalidada. El fingerprint no se registra ni se expone.
16. **SoftDeletes en `User`:** añadir el trait reutilizando `deleted_at`; sin migraciones. Los usuarios eliminados quedan fuera de listados y login, conservan el email reservado y no se restauran en este packet. La validación de unicidad de email consulta también eliminados (`withTrashed`) y tiene prueba explícita.

## Gates obligatorios — `GATES_BY_PACKET` en `scripts/sf_verify.py`

- [ ] packet `APPROVED` y activo en `docs/work/CURRENT.md`.
- [ ] archivos de backend/UI/documentación presentes.
- [ ] rutas bajo `users.manage`, sin `branch.access`, y guard de auto-desactivación/último administrador (estado, rol global y asignaciones).
- [ ] fingerprint SEC-05 en `LoginController`/`VerifySession` y pruebas de invalidación por contraseña, rol global, asignaciones, sucursal inicial y estado.
- [ ] PIN validado contra `system_configs.pin_length` (sin rango fijo 4–8).
- [ ] `User` con `SoftDeletes` y pruebas de exclusión de eliminados, sin migraciones nuevas.
- [ ] auditoría transaccional verificable (helper y pruebas de reversión); lecturas PII de listado/fichas registradas antes de mostrar datos y sin PII en el log.
- [ ] licencia registrada obligatoria para altas/activaciones de usuarios y sucursales; `0` ilimitado solo con fila, suspensión RN-70 y regresión BRANCH-001.
- [ ] tests de usuarios: CRUD, validaciones, email case-insensitive incluyendo eliminados, global XOR sucursal, asignaciones, topes, PIN sin exposición, 403/IDOR, estado, último administrador (incluido auto-cambio de rol), invalidación de sesiones, límite de plan sobre activos, soft delete, auditoría.
- [ ] regresión AUTH-001 (sesiones/RBAC) y BRANCH-001 (AuditLogger y sucursales) verde.
- [ ] suite completa, `npm run typecheck` y `npm run build`.
- [ ] reviewer independiente `APROBADO` sobre el estado exacto, D01–D16/P1–P4 con cero bloqueantes; publicación Git automática tras gates+QA. CI remota y revisión externa ChatGPT del SHA para eventual VERIFIED.
- [ ] documentación funcional en `/documentacion/modulos/usuarios/` e índice actualizado.

## Documentación funcional de usuario (obligatorio al implementar)

- Guía `documentacion/modulos/usuarios/administracion-usuarios.md` con los 10 puntos: nombre, para qué sirve, quién puede usarla, cómo acceder, cómo utilizarla, opciones, reglas y limitaciones, resultados esperados, errores frecuentes y ejemplos prácticos.
- Actualización de `documentacion/README.md` y enlaces internos válidos.
- Si alguna parte fuera puramente técnica, registrar `NO_APLICA` con justificación.

## Estado de funciones: implementado vs propuesto

- **Implementado y disponible como base:** tablas `users`, `roles`, `permissions`, `role_permissions` y `branch_user` (DB-001); login/logout, cambio de contraseña, sesiones, RBAC server-side y `admin:create` (AUTH-001); CRUD de sucursales, `EnsureBranchAccess` con sucursal activa y `AuditLogger` transaccional específico de sucursales (BRANCH-001); `users.manage` en `config/modules.php`. El modelo `User` **aún no usa `SoftDeletes`** aunque la columna `deleted_at` existe; USERS-001 añadirá el trait sin migración.
- **Implementado y verificado en USERS-001:** rutas, controlador, FormRequests, servicio de guardas (último administrador y límite de plan), fingerprint SEC-05 en `LoginController`/`VerifySession`, `SoftDeletes` en `User`, pantallas Vue/Inertia, generalización del `AuditLogger`, pruebas y documentación descritas arriba.
- **Diferido:** MFA/TOTP, anonimización, autorización por PIN (CORE-11), roles personalizados (CORE-16), vendedores (SEL-01), `PlanGuard` completo (CORE-07), importación masiva, restablecimiento por correo, restauración de eliminados y visor de auditoría.

## Cierre técnico — 2026-11-16

status: VERIFIED
verificador_externo: ChatGPT, sobre aprobación original del propietario (sin segunda firma)
registro: `docs/work/USERS-001-CLOSURE-20261116.md`
evidencia: 11/11 gates PASS, QA APROBADO, CI 2/2 SUCCESS, código SHA `2aec03893c78bc7180544930e0b6f741599df9ed` y R1 privacidad corregido
no_ejecutado: concurrencia real MariaDB aislada y verificación visual responsive con navegador; quedan pendientes predespliegue y no se declaran ejecutadas
