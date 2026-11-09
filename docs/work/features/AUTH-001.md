# AUTH-001 — Autenticación, sesiones y RBAC server-side

status: VERIFIED
owner_approval: Aprobación expresa del propietario (2026-10-19) para implementar la Feature Packet completa de autenticación, sesiones y RBAC server-side.
verified_decision: Decisión externa del propietario (2026-11-09): AUTH-001 **VERIFIED**, respaldada por dictamen técnico externo, gates `GATES_PASS`, QA independiente `APROBADO` y CI remota completamente verde sobre el código publicado en `55b7f16e761adef306957904f78b20a9effbe6e7`. El cierre es documental; no re-ejecuta gates funcionales porque el código publicado no cambia.
etapa: R3_AUTH

## Qué / Por qué / No incluido

**Propósito concreto:** Proveer la capa de identidad y acceso de StockFlash para el entorno de desarrollo local con BD DEV remota: login/logout con sesiones Laravel, cambio de contraseña, creación inicial del administrador en DEV sin credenciales predefinidas, adaptación del modelo `User` al esquema de DB-001, RBAC server-side sobre tablas y permisos ya sembrados, dashboard inicial protegido en Vue 3/Inertia responsive, y limitación de intentos + manejo de sesiones con pruebas de autorización.

**Por qué:** DB-001 ya publicó las tablas `users`, `roles`, `permissions`, `role_permissions`, `branch_user`, `system_configs` y sus semillas. AUTH-001 es la primera funcionalidad comercial que consume ese esquema y habilita el acceso al sistema.

**No incluido (fuera de alcance AUTH-001):**
- CRUD completo de usuarios, sucursales, licencias ni productos.
- MFA obligatorio para Admin Central (diferido a producción; SEC-03).
- PIN de autorización (SEC-12) y `authorization_overrides` (ya existen en DB-001 pero su uso queda para R3 posterior).
- Restablecimiento de contraseña por email (no hay envío de correo real en MVP); solo restablecimiento manual por Admin (CORE-15).
- Despliegue a staging ni producción.
- API pública general de negocio (el contrato v2.2 la excluye en el MVP).
- Implementación completa de `/setup` (se define la excepción DEV para comando local, pero no se construye el instalador).

## Referencias precisas

- Parte 1 §6.1–6.3: RBAC único, 5 roles de sistema, matriz de permisos por módulo, usuario global vs por sucursal.
- Parte 1 §6.2: tablas propias `roles`, `permissions`, `role_permissions`, `branch_user`.
- Parte 1 CORE-01: inicio de sesión con correo y contraseña; bloqueo temporal tras 5 intentos fallidos.
- Parte 1 CORE-02: onboarding de instalación (`/setup` bloquea operación normal hasta completar onboarding; excepción DEV para comando local en esta feature).
- Parte 1 CORE-04: RBAC único; verificación en middleware y políticas.
- Parte 1 CORE-15: restablecimiento manual de contraseña por el Admin.
- Parte 1 SEC-01: contraseñas mínimo 15 caracteres, máximo ≥64, sin reglas artificiales de composición, lista de comunes bloqueadas.
- Parte 1 SEC-02: límite de intentos en login, PIN y recuperación: bloqueo temporal tras 5 fallos.
- Parte 1 SEC-03: MFA obligatorio para roles definidos en `mfa_required_roles` (diferido a producción).
- Parte 1 SEC-05: sesiones: cookie `Secure`, `HttpOnly`, `SameSite=Lax`; regenerar al iniciar sesión; inactividad 30 min (POS) / 60 min (administración); duración absoluta 12 h; invalidar al cambiar contraseña o rol.
- Parte 1 SEC-14: `/install` se bloquea al terminar; `/system/update` exige Admin con MFA y reconfirmación de contraseña (diferido).
- Parte 1 SEC-15: registros de error y auditoría sin contraseñas, PIN, tokens ni RUT completo.
- Parte 2 §4.9 `users`: campos `email` (UNIQUE), `password`, `password_changed_at`, `global_role_id`, `default_branch_id`, `pin_hash`, `pin_failed_attempts`, `pin_locked_until`, `mfa_enabled`, `mfa_secret_encrypted`, `mfa_confirmed_at`, `max_discount_percent`, `max_return_amount`, `is_active`, `failed_login_attempts`, `locked_until`, `must_change_password`, `last_login_at`, `last_login_ip`.
- Parte 2 §4.7 `roles`, §4.8 `permissions` y `role_permissions`, §4.11 `branch_user`.
- Parte 2 §13.1: semillas del sistema (5 roles, permisos con `module_code`).
- Parte 2 §3: claves de `system_configs` relevantes: `session_idle_minutes_pos` (30), `session_idle_minutes_admin` (60), `session_absolute_hours` (12), `password_min_length` (15), `pin_length` (4), `pin_max_attempts` (5).

## Contratos/casos límite

- Un usuario sin filas en `branch_user` y con `global_role_id` NULL no tiene acceso a ninguna sucursal.
- Usuario global: `global_role_id` no nulo; su rol aplica en todas las sucursales. Si no existe ninguna sucursal creada, el administrador global sigue pudiendo autenticarse y acceder al dashboard, pero no puede operar en sucursal inexistente.
- Usuario por sucursal: una fila `branch_user(user_id, branch_id, role_id)` por sucursal; sin filas no tiene acceso.
- Permisos efectivos = permisos del rol correspondiente a la sucursal actual. No existen permisos asignados directamente a usuarios.
- Acceso a otra sucursal sin asignación: denegado (403), verificación server-side independiente de la interfaz.
- Las columnas de auditoría (`old_values`, `new_values` en `audit_logs`) nunca contienen contraseñas, PIN, tokens ni RUT completo.
- `is_active = 0` impide iniciar sesión y invalida la sesión vigente.
- `locked_until` con fecha futura impide el login (bloqueo tras 5 intentos fallidos).
- `must_change_password = 1` obliga a cambiar contraseña en el próximo ingreso.
- En entorno local de desarrollo (http://127.0.0.1) la cookie `Secure` no aplica; en producción sí.

## Bootstrap DEV y CORE-02

La especificación exige que `/setup` bloquee la operación normal hasta completar el onboarding.

- AUTH-001 no implementa `/setup` completo.
- Para desarrollo local, se permite crear un administrador inicial mediante comando artisan interactivo (`php artisan sf:admin:create` o similar), solicitando correo y contraseña por consola, creando el usuario con `global_role_id` = `central_admin` y `is_active = 1`, sin credenciales predefinidas y sin guardar secretos en código ni repositorio.
- Este comando es una excepción de desarrollo: no marca el onboarding como completado, no desactiva `/setup`, no instala semillas de producción, y no se usa en entornos que no sean DEV local.
- La creación del administrador inicial no crea sucursales; el administrador global autenticado sin sucursal creada accede al dashboard pero no puede operar en sucursal inexistente.

## Persistencia y seguridad de sesiones

- Guard de autenticación: Laravel `web` (sesión). No se instala paquete externo de auth (Breeze/Jetstream) sin ADR.
- Controlador de sesiones compatible con entorno local sin introducir migraciones innecesarias (se usan tablas existentes de Laravel: `users`, `sessions` si existe, o almacenamiento de sesión por archivo/cookie según `SESSION_DRIVER`).
- Criterios verificables:
  - Login con correo y contraseña correctos: iniciar sesión, regenerar identificador de sesión (`session()->regenerate()`), registrar `last_login_at` y `last_login_ip`, redirigir al dashboard protegido.
  - Logout: invalidar sesión, redirigir al formulario de login.
  - Expiración por inactividad: 30 min (POS) / 60 min (administración), leída de `system_configs` (`session_idle_minutes_pos`, `session_idle_minutes_admin`).
  - Duración absoluta de sesión: 12 horas (`session_absolute_hours`).
  - Invalidación tras cambio de contraseña: actualizar `password` y `password_changed_at`, invalidar la sesión actual, requerir nuevo login.
  - Revalidación de autorización tras cambios de rol o desactivación de cuenta: `is_active = 0` invalida sesión vigente; cambios en `branch_user` o `global_role_id` se evalúan en cada request mediante middleware de autorización.

## RBAC y sucursales

- Administrador global sin sucursal creada: puede autenticarse y acceder al dashboard, pero no puede operar en sucursal inexistente (no hay filas en `branch_user` ni sucursales creadas).
- Usuario asignado a una sucursal: sus permisos efectivos son los del rol en `branch_user` para esa sucursal. `default_branch_id` define la sucursal inicial.
- Denegación de acceso a otras sucursales: si un usuario intenta operar en una sucursal donde no tiene fila en `branch_user`, el sistema responde 403.
- Verificación server-side independiente: cada request protegido consulta `roles`, `permissions`, `role_permissions`, `branch_user` y `users.global_role_id` en el servidor; la interfaz nunca es fuente de verdad de autorización.
- Pruebas 403 y acceso indebido entre sucursales: un usuario de sucursal A no puede acceder a recursos de sucursal B.

## Aceptación (EARS)

- CUANDO un usuario envía correo y contraseña correctos en el formulario de login, EL SISTEMA DEBE iniciar la sesión, regenerar el identificador de sesión, registrar `last_login_at` y `last_login_ip`, y redirigir al dashboard protegido.
- CUANDO un usuario acumula 5 intentos fallidos de login, EL SISTEMA DEBE bloquear temporalmente la cuenta y establecer `locked_until`.
- CUANDO un usuario autenticado solicita cambio de contraseña con una contraseña nueva válida (mínimo 15 caracteres, no en lista de comunes), EL SISTEMA DEBE actualizar el hash de `password`, establecer `password_changed_at`, invalidar la sesión actual y requerir nuevo login.
- CUANDO un usuario no autenticado intenta acceder al dashboard protegido, EL SISTEMA DEBE redirigirlo al formulario de login.
- CUANDO el propietario ejecuta la creación inicial del administrador en DEV, EL SISTEMA DEBE solicitar correo y contraseña por consola/interactivo, crear el usuario con `global_role_id` = `central_admin` y `is_active = 1`, y NO guardar credenciales predefinidas ni en código ni en repositorio.
- CUANDO un usuario con rol `cashier` intenta acceder a una ruta de administración de usuarios, EL SISTEMA DEBE responder 403 y no renderizar la vista.
- CUANDO un usuario de sucursal A intenta acceder a recursos de sucursal B sin asignación en `branch_user`, EL SISTEMA DEBE responder 403.
- CUANDO una cuenta se desactiva (`is_active = 0`) o se desasigna de su sucursal, EL SISTEMA DEBE invalidar su sesión vigente en el siguiente request.

## Plan técnico mínimo por OpenCode

1. Adaptar el modelo Eloquent `User` a la tabla `users` existente (campos reales, casts de `pin_hash`, `mfa_secret_encrypted`, fechas; `password_changed_at` incluido).
2. Configurar el guard de autenticación web de Laravel sobre el modelo `User` y proveedor Eloquent.
3. Implementar controlador de login: validación, rate limiting (5 fallos → bloqueo temporal), autenticación, `session()->regenerate()`, registro de `last_login_at`/`last_login_ip`, redirección a dashboard.
4. Implementar controlador de logout: invalidar sesión y redirección a login.
5. Implementar controlador de cambio de contraseña del usuario autenticado: validación contra `password_min_length` y lista de comunes bloqueadas, actualización de `password` y `password_changed_at`, invalidez de sesión.
6. Implementar comando artisan interactivo para creación inicial del administrador en DEV (sin credenciales predefinidas, sin escribir secretos en logs ni repositorio, sin marcar onboarding como completado).
7. Crear middleware + políticas de autorización server-side sobre `permissions`/`role_permissions`/`branch_user`/`global_role_id`.
8. Crear dashboard protegido (Vue 3 + Inertia, responsive) accesible solo tras login.
9. Pruebas: login exitoso, login fallido ×5 y bloqueo, cambio de contraseña con `password_changed_at`, acceso denegado a ruta ajena por rol, acceso indebido entre sucursales (403), redirección de no autenticado a login, invalidez de sesión tras desactivación de cuenta.
10. Documentación funcional en `/documentacion/general/` (login, cambio de contraseña, acceso al dashboard) y actualización de `/documentacion/README.md`.

## Dependencias y decisiones estrictamente necesarias

- **DB-001 publicado y verificado** (tablas y semillas del Lote 01). Es prerequisito técnico.
- **config/modules.php publicado** (matriz de permisos por módulo, Parte 1 §6.3).
- Guard de autenticación: Laravel `web` (sesión). No se instala paquete externo de auth (Breeze/Jetstream) sin ADR, por contrato de RBAC propio y mínimo de dependencias.
- Registro de usuarios: NO hay registro público. La primera cuenta de administrador se crea una sola vez en DEV vía comando artisan interactivo; en producción el flujo de onboarding lo define el instalador (fuera de este alcance).
- MFA: los campos existen en el esquema (`mfa_enabled`, `mfa_secret_encrypted`, `mfa_confirmed_at`) pero **no se implementan en AUTH-001**. Se diferían a producción (MFA obligatorio para Admin Central, SEC-03).
- PIN de autorización (SEC-12): los campos existen (`pin_hash`, etc.) pero su uso queda para R3 posterior.
- Cookie `Secure`: se configura según entorno; en local (http) se permite sin `Secure`, en producción se exige.

## Criterios diferidos hasta producción

- MFA obligatorio para roles en `mfa_required_roles` (actualmente `["central_admin"]`, SEC-03).
- Bloqueo de `/install` al terminar y `/system/update` con MFA + reconfirmación de contraseña (SEC-14).
- Restablecimiento de contraseña por canal seguro con token de un solo uso (no hay correo en MVP).
- Registro de usuarios públicos y onboarding de comercio.
- `/setup` completo (se define excepción DEV para comando local; el instalador completo queda fuera de este alcance).

## Cierre VERIFIED (2026-11-09)

- **Decisión externa:** el propietario confirmó AUTH-001 **VERIFIED**, respaldado por dictamen técnico externo, gates, QA independiente y CI remota completamente verde.
- **Código publicado y verificado:** `55b7f16e761adef306957904f78b20a9effbe6e7` en `origin/main`; sin cambios de código en este cierre.
- **Gates:** `docs/work/AUTH-001-VERIFY-20261107-213531.md` → `GATES_PASS` (reportes posteriores `...-215309` y `...-220148` también `GATES_PASS`).
- **QA independiente:** `docs/work/AUTH-001-REVIEW-20261107-2.md` → `APROBADO` con addendum; suite completa 47 passed / 112 assertions.
- **CI remota:** runs `42430883629` y `42430883632` SUCCESS, con 4 check-runs SUCCESS sobre `55b7f16`.
- **PR #1:** cerrado como obsoleto, no fusionado; su head `dc4e018` no debe fusionarse.
- **Alcance del cierre:** exclusivamente documental (estado, evidencias y registro de cierre); no declara deploy ni staging.
- **Artefacto:** `docs/work/AUTH-001-CLOSURE-20261109.md`.
- Publicar este cierre no modifica el estado VERIFIED; queda registrado como decisión externa.

## Gates

- [ ] tests: login exitoso, login fallido ×5 y bloqueo, cambio de contraseña con `password_changed_at`, acceso denegado a ruta ajena por rol, acceso indebido entre sucursales (403), redirección de no autenticado a login, invalidez de sesión tras desactivación de cuenta, expiración por inactividad (configurable).
- [ ] seguridad/permisos: RBAC server-side sobre tablas de DB-001; sin permisos asignados directamente a usuarios; auditoría sin secretos; verificación de `is_active` y `locked_until`.
- [ ] responsive/táctil (si UI): dashboard en Vue 3/Inertia, usable en móvil y desktop.
- [ ] reviewer independiente.
- [ ] documentación funcional: guías en `/documentacion/general/` e índice actualizado.
- [ ] release de staging: NO aplica en esta fase (desarrollo local-first; despliegue solo por instrucción del propietario vía `/sf-deploy-staging`).

## Documentación funcional de usuario (obligatorio)

- Guía de acceso (login/logout) en `/documentacion/general/acceso.md`.
- Guía de cambio de contraseña en `/documentacion/general/cambio-contrasena.md`.
- Actualización de `/documentacion/README.md` con las nuevas guías.
- Registrar `NO_APLICA` solo para partes puramente de infraestructura de esta feature (ninguna; toda AUTH-001 es usable por el usuario).

## Estado de funciones: implementado vs previsto

- **Implementado (DB-001, ya publicado):** tablas `users`, `roles`, `permissions`, `role_permissions`, `branch_user`; semillas de 5 roles, permisos, planes, módulos, configs del sistema, reason codes.
- **Implementado en AUTH-001 (APPROVED, 2026-10-19):** controladores de login/logout/cambio de contraseña, middleware de sesión/autorización/acceso a sucursal, comando admin inicial DEV, dashboard protegido y pruebas de aceptación. Correcciones de cierre aplicadas: duración absoluta por sesión (12 h), flujo `must_change_password` sin redirección circular, `EnsureBranchAccess` sin bypass y alineación del modelo `User`/documentación.
- **VERIFIED (decisión externa, 2026-11-09):** cierre formal aprobado tras dictamen técnico externo, gates `GATES_PASS`, QA independiente `APROBADO` y CI remota verde sobre `55b7f16`.
