# BRANCH-001 — Administración de sucursales (CRUD)

status: DRAFT
owner_approval: Pendiente de aprobación expresa del propietario. No implementar hasta pasar a APPROVED.
etapa: R3_CORE (CORE-05)
dependencias: AUTH-001 (implementado y con QA APROBADO; VERIFIED pendiente de decisión externa), DB-001 (tabla `branches` y permisos sembrados), `config/modules.php`.
responsable_implementacion: OpenCode conforme al harness vigente; owner + ChatGPT delimitan alcance y declaran VERIFIED.

## Qué / Por qué / No incluido

**Propósito concreto:** administrar las sucursales de la instalación desde una pantalla de backoffice responsive en español de Chile: listar, crear, editar, activar/desactivar y eliminar lógicamente (`soft delete`) sucursales, con validación y autorización server-side.

**Por qué:** `branches` ya existe en DB-001 y AUTH-001 ya resuelve identidad, sesiones y RBAC. CORE-05 es el CRUD base que habilita la operación multi-sucursal del resto de R3 (usuarios por sucursal, estaciones POS, caja, inventario y canales). Sin sucursales administrables, el resto del dominio no tiene contexto operativo.

**No incluido (fuera de alcance de BRANCH-001):**
- Asignación de usuarios a sucursales (`branch_user`) y CRUD de usuarios: CORE-03/CORE-04, paquete posterior.
- Estaciones POS (`pos_stations`), caja (`cash_sessions`), inventario por sucursal y canales online.
- Configuración de sucursal de cumplimiento por canal (RN-31): depende de `sales_channels`, lote posterior.
- Gestión completa de licencias, planes o módulos (`PlanGuard` CORE-07 completo, CORE-06, CORE-08).
- Roles personalizados por cliente (P2) y cambios a la matriz de permisos.
- Borrado físico de sucursales, restauración de registros eliminados o migración de datos entre sucursales.
- Despliegue a staging/producción y migraciones de base de datos (no se requieren migraciones nuevas).

## Referencias precisas

- Parte 1 CORE-05: CRUD de sucursales con tipo (`store`, `warehouse`, `online`), ajustes por sucursal y control de caja opcional.
- Parte 1 CORE-07 / RN-69: límites por plan; `max_branches` cuenta sucursales activas y `0` = ilimitado. BRANCH-001 solo aplica la verificación mínima de sucursales; el `PlanGuard` completo queda para CORE-07.
- Parte 1 §6.2–6.3: RBAC único, usuario global vs. por sucursal y matriz de permisos. No existe un permiso `branches.*` en la matriz aprobada.
- Parte 1 RN-15: `sales_flow_mode` por sucursal (`direct`, `two_step`, `both`); `NULL` hereda de `system_configs`.
- Parte 1 RN-74: numeración visible única por sucursal; el código de sucursal es prefijo por defecto.
- Parte 2 §4.6 `branches`: `code` UNIQUE VARCHAR(30), `name` VARCHAR(150), `type` ENUM, datos de dirección/comuna/ciudad/región/teléfono, `is_active`, `requires_cash_control`, `sales_flow_mode`, `seller_code_policy`, `allow_negative_stock`, `settings` JSON y borrado lógico (SD).
- Parte 2 §4.4 `license_states.max_branches`: fuente de verdad del límite de sucursales activas (`0` = ilimitado).
- Parte 2 §4.19 `audit_logs`: registro inmutable de eventos, sin contraseñas, PIN, tokens ni RUT completo.
- `docs/work/features/AUTH-001.md`: autenticación, sesiones, RBAC server-side, middleware `can.permission` y `branch.access`.
- `docs/governance/DECISIONES-ABIERTAS-v2.2.md`: no hay decisión abierta que bloquee este CRUD; el límite mensual de ventas (ADR-01) no aplica aquí.

## Contratos / casos límite

- **Autorización:** todas las rutas exigen sesión válida y el permiso `settings.manage` (Admin Central), que ya existe en la matriz y en las semillas. No se crea un permiso nuevo ni se modifica DB-001. Si el propietario quisiera un permiso dedicado `branches.manage`, requeriría ADR, cambio de matriz y semilla; no forma parte de este packet.
- **Sin sucursal válida no hay operación:** el middleware `branch.access` ya exige sucursal existente incluso para usuario global. BRANCH-001 no relaja esa regla.
- **Código único e inmutable:** `code` se normaliza a mayúsculas, se valida único y no se permite cambiarlo después de crear la sucursal, porque es prefijo de numeración (RN-74). Si se requiere edición de código, debe definirse antes una política de renumeración; queda fuera de alcance.
- **Tipo de sucursal:** solo `store`, `warehouse`, `online`.
- **Herencia de ajustes:** `sales_flow_mode`, `seller_code_policy` y `allow_negative_stock` aceptan `NULL` (= heredar de `system_configs`). La UI debe ofrecer explícitamente "Heredar" y no inventar un valor por defecto distinto del contrato.
- **Control de caja:** `requires_cash_control` es booleano, por defecto `1` según DB-001.
- **`settings` JSON:** opcional; si se informa, debe ser un objeto JSON válido. No almacenar secretos, credenciales, tokens ni PII innecesaria.
- **Eliminación:** solo borrado lógico (`SoftDeletes`) con confirmación. Nunca borrado físico. Una sucursal eliminada deja de estar disponible para nuevas operaciones, pero conserva historia. No se implementa restauración en este packet.
- **Desactivación:** `is_active=0` impide nuevas operaciones en la sucursal, pero no borra datos. Se permite desactivar aunque sea la última sucursal activa; las operaciones que requieren sucursal responderán 403, mientras el dashboard global sigue accesible (coherente con AUTH-001).
- **Límite de plan:** al crear o reactivar, si existe fila en `license_states` y `max_branches > 0`, la cantidad de sucursales activas no puede superar ese límite. Si `max_branches = 0` o no hay fila de licencia, no se aplica límite en este packet; la aplicación completa de `PlanGuard` queda para CORE-07.
- **Auditoría:** crear, editar, desactivar, reactivar y eliminar registran un evento en `audit_logs` con `module_code='core'`, `auditable_type='branches'`, `auditable_id`, valores anteriores/nuevos sin secretos, IP y user-agent. No se registran contraseñas ni datos personales innecesarios.
- **Datos de prueba:** sintéticos (`SUC-A`, `SUC-B`, direcciones ficticias). Sin RUT ni datos reales.
- **Concurrencia:** la unicidad de `code` se garantiza en base de datos; la UI debe manejar el error de duplicado sin perder lo ingresado. El control de límite de plan se valida en servidor dentro de la misma operación; si dos creaciones simultáneas superan el límite, la segunda debe rechazarse de forma segura (sin estado parcial).

## Aceptación (EARS)

- CUANDO un Admin Central autenticado abre la administración de sucursales, EL SISTEMA DEBE listar las sucursales con código, nombre, tipo, comuna/ciudad, estado activo y control de caja.
- CUANDO un usuario sin `settings.manage` intenta listar, crear, editar, desactivar o eliminar una sucursal, EL SISTEMA DEBE responder 403 en servidor, con independencia de la interfaz.
- CUANDO el Admin Central crea una sucursal con datos válidos, EL SISTEMA DEBE guardarla, respetar el límite `max_branches` de `license_states` cuando corresponda, registrar auditoría y mostrarla en el listado.
- CUANDO el código de sucursal ya existe o no cumple el formato/longitud, EL SISTEMA DEBE rechazar la operación con errores de validación y no crear ni modificar el registro.
- CUANDO el Admin Central edita una sucursal, EL SISTEMA DEBE validar los campos, impedir el cambio de `code` y registrar los valores anteriores y nuevos en auditoría sin secretos.
- CUANDO el Admin Central desactiva una sucursal, EL SISTEMA DEBE establecer `is_active=0`, conservar los datos, impedir nuevas operaciones en esa sucursal y registrar auditoría.
- CUANDO el Admin Central reactiva una sucursal, EL SISTEMA DEBE verificar el límite de plan y registrar auditoría.
- CUANDO el Admin Central elimina lógicamente una sucursal, EL SISTEMA DEBE aplicar `SoftDeletes` con confirmación explícita, conservar la historia y registrar auditoría; NO DEBE borrar físicamente la fila.
- CUANDO la sucursal solicitada no existe o está eliminada lógicamente, EL SISTEMA DEBE responder 403/404 sin filtrar datos de otras instalaciones.
- CUANDO el Admin Central usa un dispositivo móvil, EL SISTEMA DEBE mostrar el listado y el formulario de forma usable, responsive y en español de Chile.

## Plan técnico mínimo por OpenCode

1. Añadir rutas protegidas en `routes/web.php` bajo `/branches` con middleware `auth`, `password.change`, `verify.session` y `can.permission:settings.manage`.
2. Implementar `BranchController` con `index`, `create` (o formulario integrado), `store`, `edit`, `update`, `deactivate`, `activate` y `destroy` (soft delete).
3. Implementar `BranchRequest` (FormRequest) con validaciones de `code` (requerido, string, máx. 30, único, formato `[A-Z0-9-]`, normalizado a mayúsculas), `name` (requerido, máx. 150), `type` (enum), longitudes de dirección/comuna/ciudad/región/teléfono, booleanos y enums/nullables de ajustes, y `settings` como objeto JSON válido.
4. Impedir la modificación de `code` en `update` (ignorar o rechazar el cambio) y documentar la regla en la pantalla.
5. Aplicar la verificación mínima de `license_states.max_branches` al crear/reactivar (0 = ilimitado), sin implementar el `PlanGuard` completo.
6. Registrar auditoría en `audit_logs` para create/update/deactivate/activate/destroy, con `old_values`/`new_values` filtrados y sin secretos.
7. Crear páginas Inertia/Vue responsive: `resources/js/Pages/Branches/Index.vue` y `resources/js/Pages/Branches/Form.vue` (o equivalente), en es-CL, con estados vacío/carga/error, confirmación de desactivación/eliminación y navegación de retorno al dashboard.
8. Añadir el enlace de navegación solo cuando el usuario tenga `settings.manage`; la UI no es fuente de autorización.
9. Pruebas de feature: CRUD feliz, validaciones, unicidad, inmutabilidad de código, 403 por permiso, límite de plan, desactivación/reactivación, soft delete, auditoría y sucursal inexistente.
10. Documentación funcional en `/documentacion/modulos/sucursales/administracion-sucursales.md` y actualización de `/documentacion/README.md`.

**Archivos previstos:** `routes/web.php`, `app/Http/Controllers/BranchController.php`, `app/Http/Requests/BranchRequest.php`, `app/Models/Branch.php` (si requiere reglas/relaciones), `resources/js/Pages/Branches/Index.vue`, `resources/js/Pages/Branches/Form.vue`, `resources/js/Pages/Dashboard.vue` (enlace condicionado), pruebas en `tests/Feature/Branches/`, documentación en `documentacion/`.

**Tests y evidencia:** `php artisan test`, `php artisan test tests/Feature/Branches`, `npm run typecheck`, `npm run build`, gates declarativos de BRANCH-001 en `scripts/sf_verify.py` (se añadirán al aprobar el packet, sin duplicar lógica), QA independiente y revisión de documentación.

## Decisiones incluidas en esta propuesta (una sola aprobación)

1. **Guard de autorización:** usar `settings.manage` (Admin Central) ya existente; no crear `branches.manage` ni tocar la matriz/DB-001. Si el propietario prefiere un permiso dedicado, debe indicarlo antes de aprobar y se requerirá ADR + semilla.
2. **Código inmutable:** `branches.code` no se edita después de crear la sucursal (prefijo de numeración RN-74).
3. **Eliminación lógica:** solo `soft delete`; sin borrado físico ni restauración en este packet.
4. **Límite de plan mínimo:** validar `license_states.max_branches` en crear/reactivar; `PlanGuard` completo queda para CORE-07.
5. **Auditoría básica:** usar `audit_logs` existente para los eventos del CRUD.

## Gates

- [ ] tests: CRUD, validaciones, unicidad, inmutabilidad de código, 403 por permiso, límite de plan, soft delete, auditoría.
- [ ] seguridad/permisos: RBAC server-side con `settings.manage`; sin permisos directos a usuarios; auditoría sin secretos; sin IDOR entre sucursales.
- [ ] responsive/táctil (si UI): listado y formulario usables en móvil y desktop, es-CL.
- [ ] reviewer independiente.
- [ ] documentación funcional: guía en `/documentacion/modulos/sucursales/` e índice actualizado.
- [ ] release de staging: NO aplica (desarrollo local-first; despliegue solo por instrucción del propietario).

## Documentación funcional de usuario (obligatorio)

- Guía `documentacion/modulos/sucursales/administracion-sucursales.md` con los 10 puntos obligatorios (nombre, para qué sirve, quién puede usarla, cómo acceder, cómo utilizarla, opciones, reglas y limitaciones, resultados esperados, errores frecuentes, ejemplos prácticos).
- Actualización de `documentacion/README.md`.
- Enlaces internos válidos y sin duplicaciones.
- Si alguna parte fuera puramente técnica, registrar `NO_APLICA` con justificación; no se prevé para este CRUD.

## Estado de funciones: implementado vs previsto

- **Implementado y disponible como base:** tabla `branches` (DB-001), RBAC y middleware de permisos/sesión (AUTH-001), `config/modules.php` con `settings.manage`, `audit_logs` y `license_states.max_branches` en DB-001.
- **Previsto en BRANCH-001 (DRAFT, sin implementar):** rutas, controlador, FormRequest, pantallas Vue/Inertia, verificación mínima de límite, auditoría del CRUD y pruebas de aceptación.
- **Diferido:** asignación de usuarios a sucursales, estaciones POS, caja, canales de cumplimiento, PlanGuard completo, restauración de eliminados y edición de código.
