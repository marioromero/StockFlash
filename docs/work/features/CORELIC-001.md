# CORELIC-001 — Contrato local de licencia y avisos de estado (CORE-08)

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Objetivo y contrato aprobado

Completar CORE-08: interfaz local `LicenseValidator` con stub ilimitado para pruebas/desarrollo, banner de estado y modo solo lectura tras la gracia, respetando controles ya implementados. No es el servidor de licencias ni vende planes.

- Parte 1 §8.1 CORE-08; Parte 1 §7.10 RN-67–70; §9.6 contrato `LicenseValidator` con métodos `isValid`, `getLicenseInfo`, `getLimits`, `hasModule`, `getDaysRemaining`, `reportStatus(InstallationStatusReport)`. El stub devuelve límites ilimitados; NO concede derechos comerciales ni convierte una licencia real inválida en válida.
- Parte 2 §4.4: tabla `license_states` ya migrada: `status` = active/expiring_soon/suspended; `expires_at`, `grace_until`, `message`, `is_simulated`, límites, módulos. Clave `installation_key`, payload firmado y firma son secretos.
- LIC-02–05 (heartbeat, respuesta Control, offline, Ed25519) son fases posteriores y quedan FUERA.

## Implementación técnica acotada

1. Contrato tipado y stub local explícito, con estructura de datos mínima si se necesita para el método `reportStatus`; **sin solicitud HTTP, cron, clave central, heartbeat ni firmas ficticias**. El stub solo puede utilizarse como simulación; no sustituye el estado real `license_states` ni cambia las guardas de usuarios/sucursales.
2. Resolver **estado de solo lectura** desde fila local `license_states` conforme RN-70 y reutilizar patrón existente en CFG-001/002/003 y sucursales: si `status=suspended` y `grace_until` nulo o vencido, no permitir escrituras de negocio; si la gracia es futura, aviso sin bloquear todavía. Si no hay fila, estado informativo «sin licencia configurada», sin conceder permisos para crear usuarios/sucursales. No inferir suspensión de `expires_at` si `status` no está suspendido.
3. Mostrar banner claro en Dashboard (solo autenticado) para `expiring_soon`, `suspended` con o sin gracia o falta de estado; incluir fecha de término o gracia solo si existe. Para `active` sin aviso, no mostrar banner de error. No exponer `installation_key`, firma, payload, datos PII, límites confidenciales u otros campos crudos al frontend. La lectura de estado no es administración.
4. Evitar duplicar lógicas de guard en cuatro controladores si puede reutilizarse una abstracción pequeña SIN alterar comportamientos verificados: centralizar solo si es seguro y con regresión completa. No permitir que el stub «ilimitado» se convierta en bypass de `PlanGuard`, `RN-70` o `RN-67`.
5. Pruebas: contrato y stub, métodos permitidos, formato/días de expiración con fecha controlada, `active` sin banner; `expiring_soon` aviso; `suspended` gracia futura/vencida/sin gracia con estado correcto; sin fila; no exposición de secretos; usuario anónimo sin acceso; permisos existentes intactos; CFG-001/002/003 y Branch/Users no cambian; suite, typecheck, build, contratos. No enviar ninguna solicitud saliente.
6. Gates declarativos CORELIC-001 en `GATES_BY_PACKET` existente, QA independiente y publisher AUTO_GIT tras PASS; CI exacta SHA y resumen SFH1. OpenCode solo reporta `GUIA_PENDIENTE_CHATGPT` si hay texto nuevo de banner, sin editar HTML de autoría ChatGPT.

## Aceptación EARS

- CUANDO no hay licencia real, EL DASHBOARD DEBE indicar estado no configurado sin inventar habilitación ni revelar secretos.
- CUANDO el estado es `expiring_soon`, EL DASHBOARD DEBE advertirlo sin bloquear por una regla inexistente.
- CUANDO la licencia está `suspended` dentro de gracia, DEBE avisar sin bloquear anticipadamente.
- CUANDO la licencia está `suspended` fuera de gracia o sin gracia, DEBE indicar solo lectura, y las escrituras existentes DEBEN seguir bloqueadas.
- CUANDO el stub se usa en pruebas, DEBE cumplir el contrato declarado con límites ilimitados sin alterar controles de licencia real ni crear comunicación externa.

## Prohibiciones

No crear ni mutar licencias reales, planes comerciales, endpoints Control, heartbeats, llamadas salientes, claves/firma criptográfica, tokens, cron, migraciones, cambios a contratos v2.2, nuevos permisos, nuevas pantallas de administración completas, dependencias nuevas, despliegue, BD remota o secretos de `.env`. No interpretar sin licencia como comercialmente ilimitada. Si surge política comercial nueva no resuelta, bloquear solo ese punto y documentar pregunta material concreta con opciones/recomendación. Ninguna aprobación genérica adicional del propietario.
