# CORESEQ-001 — Revisión QA independiente (2026-11-16)

Autor: `stockflash-reviewer` (revisión independiente, sin autoaprobación).
Feature Packet: `docs/work/features/CORESEQ-001.md` (CORE-13 / RN-74), status APPROVED, `AUTO_GIT`, `ONE_SMALL_WAVE`.
Base revisada: `92f217d7058a45a40c2eab6115c47690deeaa8eb` (HEAD sin commit adicional).
Alcance: servicio interno de numeración transaccional sobre la tabla ya migrada `document_sequences`.

## 1. Estado exacto verificado (git hash-object)

| Archivo | Hash revisado | Coincide |
|---|---|---|
| `app/Support/DocumentSequenceAllocator.php` | `b363bbbc5d1e9e89cad4767fa372a257a70b17a7` | Sí |
| `tests/Feature/Core/DocumentSequenceAllocatorTest.php` | `ef57b0cb57da677280b693e03d3c6cb6275fec5f` | Sí |
| `scripts/sf_verify.py` | `d3377fea8d63c61910a6f3679240f912b1334c02` | Sí |
| `docs/work/GUIA-HTML-ESTADO.md` | `a5244f8cad6f7f09c47f44af1eac095e84c2a647` | Sí |
| `docs/work/CORESEQ-001-NOTES-20261116.md` | `437e0c0c729c974734fed21213ce48278abe692e` | Sí |
| `docs/work/CORESEQ-001-VERIFY-20261116-151032.md` | `a311458b17f5ee210b388cf02488be9795d0833a` | Sí |

`git status --short`: 2 modificados (`docs/work/GUIA-HTML-ESTADO.md`, `scripts/sf_verify.py`) y 4 nuevos (`app/Support/DocumentSequenceAllocator.php`, `tests/Feature/Core/DocumentSequenceAllocatorTest.php`, `docs/work/CORESEQ-001-NOTES-20261116.md`, `docs/work/CORESEQ-001-VERIFY-20261116-151032.md`). Sin otros untracked fuera de artefactos de esta revisión.

## 2. Verificación de alcance

- **Servicio único, sin capa pública**: un solo archivo en `app/Support`; sin rutas, controladores ni UI (`git status` vacío en `app/Http`, `routes`, `resources/js`, `public`). Cero consumidores/inyecciones en `app/` y `routes/` (búsqueda de `DocumentSequenceAllocator` sin resultados fuera de sí mismo).
- **Tipos/ámbitos**: los 7 tipos por sucursal y `purchase_order` instalación (`scope_type=installation`, `scope_id=0`) coinciden con Parte 2 §4.14 y migración `2026_10_16_000014`. `transfer` recibe la sucursal de origen del llamador; no se infiere origen/destino.
- **Sucursales existentes**: `Branch::whereKey()` (SoftDeletes excluye eliminadas) antes de abrir transacción; no crea filas para sucursal inexistente, tipo inválido, `branch_id` ausente/≤0, ni `purchase_order` con sucursal.
- **Atomicidad**: `insertOrIgnore` (creación idempotente protegida por UNIQUE `(doc_type, scope_type, scope_id)`) + `lockForUpdate()` + UPDATE de `next_number` dentro de `DB::transaction(..., 3)`. No hay locks/retry propios (`beginTransaction`/`commit`/`rollBack`/`Route::`/HTTP ausentes; comprobado también por CQ3). Sin DELETE de secuencias.
- **Transacción exterior**: `DB::transaction` anidado usa savepoint; rollback exterior revierte fila nueva y contador existente (probado).
- **Prefix/padding**: fila existente conserva `prefix`/`padding`/correlativo (solo se actualiza `next_number` y `updated_at`); al crear: código de sucursal si ≤10, padding 6, next_number 1. Código >10 (branches.code admite 30) exige `prefixOverride` explícito, sin truncar y sin crear fila; override validado 1..10 y sin whitespace/control. No se modifica `branches`.
- **Overflow/corrupción**: rango validado (`padding` 1..18, `next_number` 1..10^padding−1) **antes** del UPDATE; errores `DomainException`/`InvalidArgumentException` sin mutación parcial (probado para padding, prefix, next_number=0 y capacidad agotada). Tras consumir el último número el contador queda en `10^padding` y siguientes llamadas fallan con error controlado (secuencia agotada).
- **Prohibiciones**: sin tablas/campos/migraciones (`database/` sin cambios), sin DB remota/`.env`/secretos, sin staging/deploy, sin ticket/venta/stock/caja, sin librerías/scripts/agentes nuevos, sin composer/package nuevos. Protegidos intactos (`git diff --quiet HEAD` exit 0 sobre `AGENTS.md`, `opencode.json`, `docs/product`, `docs/governance/CONSTITUTION.md`, `docs/branding`, `CONTEXTO-CHATGPT.md`, `public/docs/index.html`, `resources/roadmap/index.html`, `docs/work/ROADMAP-HTML-ESTADO.md`).
- **Documentación**: sin entrada en manual HTML (correcto, servicio interno invisible); `GUIA-HTML-ESTADO.md` solo metadatos de seguimiento; `CONTEXTO-CHATGPT.md` y roadmap HTML intactos.
- **Gates**: `GATES_BY_PACKET["CORESEQ-001"]` reutiliza `sf_verify.py` con 16 gates (CQ1–CQ16), sin lógica duplicada; cubre packet activo, archivos, patrón transaccional, prohibición de UI/migraciones, protegidos, contratos, Core, regresiones Auth/Branches/Users/Settings (CFG), suite completa, typecheck, build y diff-check. Reporte `CORESEQ-001-VERIFY-20261116-151032.md` registra 16/16 PASS coherente con el bloque real del script.

## 3. Evidencia ejecutada por el reviewer

| Comprobación | Resultado |
|---|---|
| `php artisan test tests/Feature/Core` | 21 passed, 94 assertions (1.72 s) |
| `php artisan test` | 282 passed, 1936 assertions (49.11 s) — coincide con el reporte de gates |
| `npm run typecheck` | OK (`vue-tsc --noEmit` sin errores) |
| `php -l` servicio y test | Sin errores de sintaxis |
| `py -3 scripts/check_contracts.py` | OK (362 referencias, 6 hashes, reglas contractuales) |
| `git hash-object` de los 6 archivos | 6/6 coinciden con el estado solicitado |
| `git diff --quiet HEAD` sobre protegidos | exit 0 (intactos) |
| Conteo gates CQ en script vs reporte | 16 vs 16 |

**No ejecutado por el reviewer** (no se afirma): gates oficiales (los ejecutó el orquestador; aquí solo se contrastó su resultado), `npm run build` (evité regenerar artefactos; consta PASS en el reporte de gates), concurrencia real MariaDB (sin instancia DEV autorizada; declarada pendiente en NOTES), navegador/UI (no aplica), CI GitHub, deploy y migraciones. **Esta revisión no declara VERIFIED.**

## 4. Hallazgos

### Bloqueantes (0)
Ninguno. No se encontró defecto de integridad/atomicidad, mutación parcial, truncamiento silencioso, aislamiento por ámbito incorrecto ni scope creep.

### Mayores (0)

### Menores (4)
1. `existingPrefix()` (líneas 192–199) valida tipo y longitud, pero no rechaza whitespace/control en una fila preexistente; un `prefix` corrupto con espacios se conservaría y aparecería en el número visible. No puede originarse por este servicio (el override sí se valida), por lo que es endurecimiento, no fuga de datos ni colisión de esquema.
2. `prefixOverride` se ignora en silencio si la fila ya existe (líneas 94 y 164–166). Es coherente con "conservar la fila existente" y está documentado en NOTES, pero un llamador que espere aplicarlo no recibe señal; convendría documentar/explicitar en la API del futuro llamador (no requiere cambio para este packet).
3. Cobertura de tests: no hay caso para override con whitespace/control, ni para `allocate()` (delegación trivial), ni para comprobar que un override pasado sobre fila existente no altera el prefix. Las ramas existen en código; es cobertura menor, no test simulado (los 21 tests restantes son significativos y verifican estado de BD).
4. Gates: CQ16 `git diff --check HEAD` no examina archivos untracked, por lo que no detectaría problemas de whitespace en los archivos nuevos; CQ4/CQ5/CQ6 no cubren `composer.json`/`package.json` como control anti-scope-creep (mitigado: en este estado no hay cambios ahí, verificado manualmente).

### Observaciones (5)
1. Ramas con `code` de 11–30 caracteres (válidas según `BranchRequest`) quedan sin numeración por defecto y exigen override en cada llamada; está previsto y documentado en el packet, pero es una decisión operativa a resolver por los módulos consumidores (no bloquea CORE-13).
2. Al agotarse la capacidad, `next_number` queda en `10^padding` (fuera del rango aceptado) y la fila queda permanentemente en error hasta intervención; no hay política de recuperación (correcto no inventarla).
3. Retry `DB::transaction(..., 3)` es el de Laravel: en MariaDB un deadlock revierte la transacción completa, no solo el savepoint, por lo que el reintento anidado dentro de una transacción exterior tiene valor limitado. Diferido a la prueba MariaDB pendiente; no hay locks propios.
4. `strlen` mide bytes frente a `VARCHAR(10)` que cuenta caracteres: códigos/overrides multibyte válidos de 10 caracteres pueden rechazarse (falso negativo conservador; nunca truncamiento silencioso). Los códigos de sucursal reales son ASCII `[A-Z0-9-]`.
5. La comprobación de longitud del visible (líneas 98–102) es tautológica dado el rango validado; la protección efectiva es la validación de rango/ tipo previa. Además, `GUIA-HTML-ESTADO.md` dice "pendiente de decisión del propietario", mientras la priorización está delegada a ChatGPT (regla 23); es metadato editorial sin efecto técnico.

## 5. Veredicto

**APROBADO**

Bloqueantes: **0**. Mayores: 0. Menores: 4. Observaciones: 5.
Los menores/observaciones son endurecimiento y cobertura; no impiden `APROBADO` ni requieren corrección para este packet. No se modifica código, no se hace commit/push y **no se declara VERIFIED** (atribución externa, CI sobre SHA publicado y gates oficiales fuera de esta revisión).