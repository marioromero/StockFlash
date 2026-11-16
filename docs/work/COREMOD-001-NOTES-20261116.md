# COREMOD-001 — Notas técnicas (2026-11-16)

Feature Packet: `docs/work/features/COREMOD-001.md` (CORE-06 / RN-67–68), `APPROVED`, `ONE_SMALL_WAVE`.
Base: `f0855a782687be818ad0443836abe6165ab0bbb4`.

## Alcance

- Servicio interno `App\Support\Modules\ModuleManager` + catálogo único `App\Support\Modules\ModuleCatalog`.
- Sin rutas, controladores, UI, HTTP saliente, migraciones, seeders, dependencias nuevas ni cambios de esquema.
- Opera sobre tablas ya migradas: `installation_modules` y `license_states`.
- `config/modules.php` (matriz RBAC) no se modifica: habilitar un módulo técnico no concede permisos ni derechos comerciales.

## Catálogo y dependencias

- 22 códigos exactos de Parte 1 §4.2: `core`, `catalog`, `inventory`, `purchasing`, `sales`, `pos`, `cash_control`, `customers`, `sellers`, `promotions`, `combos`, `price_lists`, `fiscal`, `channels`, `woo_sync`, `reports_basic`, `reports_advanced`, `audit_log`, `customer_credit`, `loyalty`, `offline`, `lots_serials`.
- Dependencias duras idénticas al packet; bloquean `enable`/`disable`.
- Dependencias blandas informativas, nunca bloqueantes. `reports_basic` usa `['*']` para representar «todos».
- `core` es infraestructura base: se reporta siempre habilitado, no crea fila y no se puede desactivar.

## API pública

- `states()`: listado del estado técnico de todos los códigos.
- `state(string $code)`: código, `is_enabled`, `enabled_at`, `disabled_at`, dependencias duras/blandas.
- `enable(string $code)`: alta técnica idempotente.
- `disable(string $code)`: baja técnica idempotente.
- Código desconocido: `DomainException` antes de cualquier escritura.

## Transacciones, locks y reglas

- `enable` y `disable` ejecutan todo dentro de `DB::transaction`.
- Orden de locks: primero fila local de `license_states` (`orderBy('id')`); después filas `installation_modules` de códigos conocidos con `orderBy('module_code')` y `lockForUpdate`.
- `enable` falla cerrado si no hay licencia local, si `modules_enabled` no es una lista JSON válida o si el módulo no está incluido ni cubierto por `["*"]`.
- `LicenseReadOnlyRule` se reutiliza sin duplicar: suspensión fuera de gracia bloquea cambios; dentro de gracia no.
- Dependencias duras: se verifica el cierre transitivo. Si falta algo, se informa el bloqueo sin autoactivar módulos distintos del caso especial.
- `woo_sync`: se exige que `channels` esté licenciado. Si `channels` no está activo, además se exige que `catalog` esté activo; en ese caso se habilitan `channels` y `woo_sync` en la misma transacción. Cualquier requisito faltante rechaza la operación completa sin cambios.
- `disable`: bloquea si existe cualquier dependiente habilitado directo o indirecto. Solo cambia `is_enabled=0`, `disabled_at` y `updated_at`; conserva `enabled_at` y `settings`.
- Idempotencia: repetir `enable`/`disable` no reescribe timestamps ni duplica auditoría.
- Auditoría en la misma transacción: `core.module_enabled` / `core.module_disabled` con `module`, `changed_modules` y actor (`user_id` del log + `actor`), sin JSON de licencia ni secretos. Si la auditoría falla, se revierte todo.

## Pruebas ejecutadas

- `php artisan test tests/Feature/Core` → 66 pruebas, 409 aserciones, todas PASS.
- `php artisan test` → 328 pruebas, 2256 aserciones, todas PASS.
- `npm run typecheck` → PASS (sin salida de error).
- `npm run build` → PASS (Vite 7.3.7, 790 módulos transformados).
- Cobertura específica en `tests/Feature/Core/ModuleManagerTest.php`: catálogo exacto, estado, cadena de dependencias, `woo_sync`, rollback por auditoría fallida, licencia faltante/JSON inválido/módulo excluido, `["*"]`, suspensión/gracia, `core`, idempotencia, auditoría sin secretos, `settings`, módulo desconocido.

## Límite declarado

- **Concurrencia real MariaDB NO ejecutada**: no existe instancia aislada autorizada. Los locks y el orden estable se verifican de forma estática y funcional en SQLite in-memory (`RefreshDatabase`), no bajo concurrencia real.
- Sin conexión a BD remota, sin migraciones, sin deploy, sin commit/push y sin declarar VERIFIED.
