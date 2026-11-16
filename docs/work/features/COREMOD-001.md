# COREMOD-001 — Registro técnico y control de dependencias de módulos (CORE-06 / RN-67–68)

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Contexto y límites del alcance

CORE-08 verificado. Esta feature implementa el motor interno de módulos de CORE-06, antes de construir módulos de Bodega/POS. No crea permisos comerciales ni menús visibles: una habilitación técnica **no equivale** a comprar ni habilitar capacidades de StockFlash POS/Bodega/Fiscal.

Contrato vigente: Parte 1 §4.1–4.3, §7.10 RN-67–68 y §8.1 CORE-06; Parte 2 §4.3 `installation_modules` (module_code UNIQUE, is_enabled, enabled_at, disabled_at, settings). Se usa `license_states.modules_enabled` solo como inclusión **técnica**, no como prueba de `products_enabled`. Los derechos comerciales y permisos RBAC se aplicarán al consumidor de cada operación; jamás exponer un helper `canSell/canUseProduct` que los omita.

## Registro canónico (sin editar contratos o seeders)

Definir un único catálogo de códigos y **dependencias duras** idénticas a Parte 1 §4.2 (núcleo siempre disponible como infraestructura técnica): `core`, `catalog → core`, `inventory → catalog`, `purchasing → inventory`, `sales → catalog`, `pos → sales`, `cash_control → sales`, `customers → core`, `sellers → core`, `promotions/combos/price_lists → catalog`, `fiscal → sales`, `channels → catalog`, `woo_sync → channels`, `reports_basic → core`, `reports_advanced → reports_basic`, `audit_log → core`, `customer_credit/loyalty → customers + sales`, `offline → pos`, `lots_serials → inventory`. Dependencias blandas NO bloquean.

## Implementación

1. Servicio interno acotado `ModuleManager` o equivalente, sin endpoints, nuevas tablas ni librerías. Ofrecer listado/consulta del estado técnico, `enable` y `disable` para un código contractual. `core` no se puede desactivar; tratarlo como servicio base, sin inventar venta ni derechos comerciales. Validar módulo conocido y filas `installation_modules`; crear solo la fila necesaria cuando habilita, jamás registrar módulo no declarado.
2. Habilitar solo dentro de transacción y si existe fila local de licencia válida en cuanto a `modules_enabled` (lista o `["*"]`), sin exceder el período de gracia RN-70. Si falta licencia, JSON inválido o no incluye el módulo, FALLAR cerrado sin modificar datos. Revisar dependencias DURAS activas. El contrato exige expresamente que activar `woo_sync` active `channels` automáticamente: solo hacerlo si `channels` está permitido por licencia, y si `catalog` también está activo; en caso contrario, rechazar operación completa. Para otras dependencias no activas, informar bloqueo y exigir activación previa, sin autoactivar silenciosamente.
3. Desactivar solamente si no existen otros módulos habilitados que dependan duramente del objetivo (incluidos indirectos). No borrar datos, permisos ni `settings`. Registrar timestamps `enabled_at`/`disabled_at`. Reutilizar `LicenseReadOnlyRule` (en suspensión fuera de gracia no permitir cambios), permisos solo para futuros consumidores; no agregar rutas UI, no permitir que un módulo habilitado por técnica conceda permisos RBAC/entitlements por sí solo.
4. Atomicidad: actualizar módulo(s) y auditoría `core.module_enabled/disabled` dentro de la misma transacción, con `changed_modules`/nombres/actor únicamente, sin secretos ni copias del JSON de licencia. Rollback completo si falla cualquier paso. Operaciones idempotentes sin log duplicado; serializar modificaciones con locks DB mínimos y orden estable sobre las filas relevantes, evitando race conditions (concurrencia real MariaDB pendiente si no hay instancia aislada).
5. Tests: catálogo exacto/duro-blando, habilitación/estado/deshabilitación, cadena de dependencias, `woo_sync → channels` automática y rollback, licencia faltante/JSON inválido/módulo excluido, `["*"]`, suspensión/gracia, `core` indeactivable, idempotencia, auditoría atómica sin secretos, settings preservados, módulos no declarados rechazados, regresión licencias/usuarios/sucursales/CFG y suite PHP/TS/build.
6. Reutilizar `scripts/sf_verify.py`, añadir gates declarativos COREMOD-001 sin scripts/infraestructura nuevos; QA independiente y publicación AUTO_GIT tras PASS, CI exacta SHA. Servicio sin UI; **NO** nueva página en guía, solo metadatos si procede.

## Aceptación EARS

- CUANDO se habilita un módulo permitido y sus dependencias duras están activas, EL SISTEMA DEBE registrar su estado técnico y auditoría atómicamente.
- CUANDO se habilita `woo_sync` y `channels` no está activo pero está licenciado, EL SISTEMA DEBE activar ambos dentro de una transacción; si falta catalog u otro requisito, NO DEBE alterar nada.
- CUANDO se intenta desactivar un módulo del que depende otro activo, EL SISTEMA DEBE bloquear el cambio y preservar los datos.
- CUANDO licencia no existe/no habilita el módulo/está suspendida fuera de gracia, o la auditoría falla, EL SISTEMA NO DEBE permitir nuevas activaciones ni cambios parciales.
- CUANDO un módulo técnico se activa, EL SISTEMA NO DEBE asumir que existe derecho comercial, permiso RBAC, endpoint, menú o funcionalidad de negocio.

## Exclusiones estrictas

Sin editar contrato v2.2 ni `config/modules.php` (matriz RBAC existente), sin resembrar/migrar DB, sin cambios remotos/secretos/deploy, sin gestionar suscripciones ni marcar productos comerciales comprados, sin clientes/POS/inventario real, sin módulo fiscal integrado, sin UI ni rutas públicas nuevas, sin plan comercial nuevo, sin agentes/índices/protocolos. Si aparece una decisión nueva material, informar pregunta concreta con opciones y bloquear solo lo afectado. No pedir aprobación genérica.
