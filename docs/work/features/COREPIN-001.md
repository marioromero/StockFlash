# COREPIN-001 — Autorizaciones reutilizables mediante PIN (CORE-11 / SEC-12)

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Contrato y propósito

Crear servicio interno reutilizable para autorizar acciones sensibles mediante **PIN de un autorizador identificado**, con bloqueo tras intentos fallidos y registro inmutable de aprobación. Es un prerrequisito para POS-05, cambios de precio, anulaciones y movimientos de caja. No construir todavía pantallas, endpoints, transacciones POS ni lógica de descuentos.

Fuentes **ya aprobadas**: Parte 1 §8.1 `CORE-11`; §9.3 `SEC-12` (PIN hasheado, usuario autorizador identificado, bloqueo de intentos, PIN nunca en registros), `SEC-02` (cinco fallos y bloqueo temporal), §7.2 `RN-04/RN-05` (permiso `override.authorize`, `authority_level >= override_min_authority`, motivo cuando corresponda); Parte 2 §4.18 `authorization_overrides` **AO/inmutable**, §4.9 `users.pin_hash/pin_failed_attempts/pin_locked_until`, §4.7 `roles.authority_level`, §4.12 `reason_codes`; Parte 2 §3.2 `pin_length`, `pin_max_attempts` (5), `override_min_authority` (50).

## Implementación acotada

1. Servicio interno único, p.ej. `App/Support/Authorization/PinAuthorizationService.php`, sin endpoint ni UI. Recibe acción/tipo del enum contractual y `branch_id` explícito, identificador del autorizador, PIN introducido y contexto mínimo opcional (referencia, motivo, reason_code, valores antiguos/nuevos cuando corresponda). El **solicitante** se toma de la sesión autenticada real; no aceptar su ID arbitrario en payload. Nunca retornar, registrar ni guardar PIN ni hash en auditoría.
2. Validar solicitante autenticado/activo con acceso a sucursal y autorizador activo/no eliminado, con rol global o asignación de esa sucursal. Autoridad/permiso `override.authorize` debe resultar de rol real y `role_permissions`; autoridad mínima desde `override_min_authority` con su valor contractual. Un usuario con múltiples sucursales NO puede usar un rol en otra sucursal como permiso cruzado. **No inventar prohibición de autoautorización** (el contrato no la especifica): aplicar siempre PIN y reglas de rol. No atribuir permiso a mera habilitación técnica de módulo.
3. Verificar hash con `Hash::check`; aceptar PIN con longitud configurada `pin_length` y solo dígitos, nunca escribirlo en mensajes/logs. Serializar intentos contra el mismo autorizador mediante `lockForUpdate` en transacción. Cinco fallos (o máximo existente configurado) producen bloqueo **temporal** usando `users.pin_locked_until`, con comportamiento temporal coherente con el bloqueo de login vigente (30 minutos), sin tabla/columna nueva. Intentos fallidos, lockouts y eventos de seguridad deben **persistir incluso al rechazar** la solicitud: no lanzar excepción dentro de la transacción de fallos que haga rollback. Tras éxito válido reiniciar contadores y bloqueo; no permitir éxito mientras esté bloqueado.
4. Solo cuando todas las verificaciones pasan, insertar **una fila append-only** en `authorization_overrides` con tipo contractual, solicitante, autorizador, sucursal, estación/referencia/motivo si aplica; fecha y contexto permitido. Validar `reason_code_id` existente, activo y de scope `override` si se usa; `requires_note` exige nota. Nunca actualizar, eliminar o reutilizar aprobación previa. Asociar registros de auditoría `is_security_event=1` sin guardar PIN, hash ni valor sensible; inserción de autorización y auditoría deben ser **atómicas** con rollback ante falla de auditoría.
5. **Alcance exacto del servicio:** verificación de PIN + política común de autorización + persistencia de aprobación. La elegibilidad de una venta concreta (descuento máximo, costo, caja, devolución) se valida por el módulo futuro, no por un bypass general ni mediante la sola existencia de un registro. Retornar ID de autorización aprobada para vincular en el futuro, no reutilizar automáticamente una aprobación para otra acción.
6. Pruebas negativas/positivas: permisos y autoridad por rol global/sucursal, usuario desactivado, sucursal no asignada/inactiva, PIN faltante/no numérico/longitud incorrecta, hash erróneo, intentos concurrentes simulados secuencialmente, cinco fallos y bloqueo, expiración y reset, éxito con inserción AO, audit_logs sin PIN, rollback de aprobación en error de auditoría, reason_code opcional/requerido, tipo inválido, sin sesión, otras configuraciones no alteradas, y regresión AUTH/USERS/BRANCH/CORELIC/COREMOD. Si validación MariaDB concurrente no está disponible sin tocar DB remota, registrarla pendiente predespliegue (no declarar probada).
7. Añadir gates COREPIN-001 al `GATES_BY_PACKET` del runner existente, sin scripts/índices/agentes nuevos. Ejecutar suite PHP, typecheck, build, contrato, diff hygiene, QA independiente y AUTO_GIT. Informar SHA y CI exactos por SFH1. **Servicio interno invisible:** no necesita otra página en `/docs/`; ChatGPT puede actualizar roadmap editorial si aporta avance.

## EARS

- CUANDO un solicitante activo pide autorización para una sucursal a la que tiene acceso y un autorizador válido acredita PIN correcto, permiso y autoridad suficiente, EL SISTEMA DEBE crear un registro inmutable de autorización dentro de transacción auditable.
- CUANDO se acumulan cinco intentos de PIN fallidos, EL SISTEMA DEBE bloquear temporalmente nuevas autorizaciones con ese PIN y registrar evento de seguridad **sin filtrar el secreto**.
- CUANDO el autorizador está bloqueado/inactivo, carece de permiso o autoridad, o no tiene rol aplicable a esa sucursal, EL SISTEMA DEBE rechazar sin conceder autorización.
- CUANDO falla auditoría al aprobar, NO DEBE quedar registro de aprobación parcial; CUANDO se rechaza por PIN incorrecto, el contador de fallos sí DEBE persistir.
- CUANDO se verifica un PIN, EL SISTEMA NO DEBE crear por sí mismo una venta, devolver derechos comerciales adicionales ni conceder autorizaciones entre sucursales.

## Límites

No construir POS-05 UI ni rutas públicas, no manejar DTE, no activar ventas/caja reales, no cambiar contrato v2.2, migraciones, RBAC, seeds o políticas de empresa, no enviar PIN por email/red ni colocarlo en logs, no editar `public/docs/index.html`/`resources/roadmap/index.html`/`CONTEXTO-CHATGPT.md` (exclusivos ChatGPT), no desplegar ni tocar BD remota, no crear nuevas dependencias ni protocolos. Si un caso exige una decisión **comercial nueva/material**, documentar la pregunta concreta y opciones/recomendación; continuar las partes independientes sin pedir aprobación genérica del packet.
