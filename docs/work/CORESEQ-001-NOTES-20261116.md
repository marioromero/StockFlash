# CORESEQ-001 — Notas técnicas (2026-11-16)

Feature Packet: `docs/work/features/CORESEQ-001.md` (CORE-13 / RN-74), `APPROVED`, `ONE_SMALL_WAVE`.
Base: `92f217d7058a45a40c2eab6115c47690deeaa8eb`.

## Alcance

- Servicio interno `App\Support\DocumentSequenceAllocator` sobre la tabla ya migrada `document_sequences`.
- Sin rutas, controladores, UI, capa genérica, migraciones, dependencias nuevas ni cambios de esquema.
- Tipos por sucursal: `ticket`, `sale`, `return`, `transfer`, `count`, `goods_receipt`, `cash_session`.
- Tipo instalación: `purchase_order` (`scope_type=installation`, `scope_id=0`); rechaza sucursal.
- `transfer` usa la sucursal de origen que recibe el llamador.

## API

- `reserve(string $docType, ?int $branchId = null, ?string $prefixOverride = null): array`
  devuelve exactamente `doc_type`, `scope_type`, `scope_id`, `number` (int), `visible` (string).
- `allocate(string $docType, ?int $branchId = null, ?string $prefixOverride = null): string`
  delega en `reserve()` y devuelve `visible`.
- Entradas inválidas: `InvalidArgumentException`. Corrupción/overflow de la fila: `DomainException`.

## Decisiones técnicas

- Creación idempotente con `insertOrIgnore`; el UNIQUE `(doc_type, scope_type, scope_id)` resuelve la concurrencia de creación. Después se lee la fila con `lockForUpdate()` y se actualiza solo `next_number` y `updated_at`.
- Toda la operación ocurre en `DB::transaction($callback, 3)`: el retry limitado de deadlock es el de Laravel; no hay locks ni reintentos manuales. Anidada en una transacción exterior usa savepoint.
- Al crear: `prefix` = código de sucursal si mide ≤ 10, padding 6 y `next_number` 1. `prefixOverride` (1..10 caracteres, sin whitespace/control) solo aplica a la creación.
- Si el código de sucursal supera 10 caracteres y no hay override válido se lanza error controlado sin truncar y sin crear fila. No se inventa política comercial.
- Filas existentes conservan `prefix`, `padding` y correlativo. Antes de mutar se valida `prefix` (string, ≤ 10), `padding` (entero 1..18) y `next_number` (entero 1..`10^padding - 1`).
- `visible = prefix . str_pad(number, padding, '0', STR_PAD_LEFT)`; se verifica longitud `strlen(prefix) + padding`.
- Un rollback del receptor antes del commit exterior revierte fila y contador. Tras un commit consolidado no se garantiza ausencia total de saltos; la garantía es unicidad y ausencia de duplicados.

## Límite de pruebas

- Pruebas en SQLite in-memory (`RefreshDatabase`): secuencia, ámbitos, idempotencia, UNIQUE, rollback exterior y errores sin mutación.
- **Concurrencia real con MariaDB NO ejecutada**: no se confirmó una instancia DEV local autorizada. No se afirma prueba de concurrencia MariaDB ni garantía de ausencia total de saltos tras rollback consolidado. Sin acceso a BD remota, deploy ni secretos.
