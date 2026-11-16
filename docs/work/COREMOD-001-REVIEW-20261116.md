# COREMOD-001 — Revisión QA independiente (2026-11-16)

- **Revisado por:** `stockflash-reviewer` (QA independiente)
- **Fecha:** 2026-11-16
- **SHA base revisado:** `f0855a782687be818ad0443836abe6165ab0bbb4`
- **Feature Packet:** `docs/work/features/COREMOD-001.md` (APPROVED, CORE-06 / RN-67–68)
- **Modo:** revisión de solo lectura sobre el árbol de trabajo; sin implementar, corregir, commit, push, migraciones ni declarar VERIFIED.
- **Veredicto:** **APROBADO** (0 bloqueantes)

## Archivos revisados y hashes de contenido de trabajo

Hashes con `git hash-object` sobre el contenido actual del árbol (no sobre el índice).

| Archivo | Estado git | Blob |
|---|---|---|
| `docs/work/features/COREMOD-001.md` | rastreado, sin cambios | `37f873e13d8d4d9260c52c18a15a00bdecc69626` |
| `docs/work/CURRENT.md` | rastreado, sin cambios | `1ebe1c4c74b8b80b5c2c3c3e53a3e845f0dbd78d` |
| `app/Support/Modules/ModuleCatalog.php` | nuevo (untracked) | `07be793b745e2774e6cb528679f00f86fbc93f19` |
| `app/Support/Modules/ModuleManager.php` | nuevo (untracked) | `ce69b952a00e803398ca37163da21d05558145c8` |
| `tests/Feature/Core/ModuleManagerTest.php` | nuevo (untracked) | `a2d4fa9c2f327a753fe1ec55a4a8859f97eefdd7` |
| `docs/work/COREMOD-001-NOTES-20261116.md` | nuevo (untracked) | `4fa6217ab8a59eb681f89d7d24bb599b0fecd1ab` |
| `docs/work/COREMOD-001-VERIFY-20261116-194700.md` | nuevo (untracked) | `26513c1db60c931d377561a76ff9f746b06efe9e` |
| `scripts/sf_verify.py` | modificado | `a3e6f50605b69945cc3e6d844c1c715678d2f4c6` |
| `docs/work/GUIA-HTML-ESTADO.md` | modificado | `c83ed648384099005829ba0be03e8a2ddc6fc2fe` |

Árbol de trabajo exacto al iniciar y cerrar la revisión: 2 archivos modificados (`scripts/sf_verify.py`, `docs/work/GUIA-HTML-ESTADO.md`) y los 4 untracked del entregable (más este artefacto de revisión). `git diff --stat HEAD` = `2 files changed, 57 insertions(+), 1 deletion(-)`.

## Comandos ejecutados y resultados reales

1. `git rev-parse HEAD` → `f0855a782687be818ad0443836abe6165ab0bbb4`.
2. `git status --short` → únicamente los 2 modificados y 4 untracked esperados; `git ls-files --others --exclude-standard` → `app/Support/Modules/ModuleCatalog.php`, `app/Support/Modules/ModuleManager.php`, `docs/work/COREMOD-001-NOTES-20261116.md`, `docs/work/COREMOD-001-VERIFY-20261116-194700.md`, `tests/Feature/Core/ModuleManagerTest.php`.
3. `git diff -- docs/work/GUIA-HTML-ESTADO.md scripts/sf_verify.py` → solo metadato `internal_only_features` y bloque declarativo `COREMOD-001` de gates; sin otros cambios.
4. `git diff --quiet HEAD -- AGENTS.md opencode.json docs/governance/CONSTITUTION.md docs/product docs/branding docs/harness/CONTEXTO-CHATGPT.md public/docs/index.html resources/roadmap/index.html docs/work/ROADMAP-HTML-ESTADO.md` → exit 0 (protegidos intactos).
5. `git status --porcelain -- app/Http routes resources/js public database config/modules.php` → salida vacía (sin rutas, UI, tablas, migraciones, seeders ni `config/modules.php`).
6. `php -l app/Support/Modules/ModuleCatalog.php` y `php -l app/Support/Modules/ModuleManager.php` → `No syntax errors detected` en ambos.
7. `php -r "require 'vendor/autoload.php'; ..."` → `22` códigos y mapa de dependencias duras exactamente igual al contrato §4.2.
8. `py -3 scripts/check_contracts.py` → `OK DOC-001: 362 referencias, 6 hashes y reglas contractuales comprobadas`.
9. `php artisan test tests/Feature/Core` → **66 passed (409 assertions)**, 4.22 s.
10. `php artisan test` (suite completa) → **328 passed (2256 assertions)**, 52.64 s.
11. `npm run typecheck` → exit 0, sin errores.
12. `npm run build` → exit 0, Vite 7.3.7, 790 módulos transformados, 2.47 s.
13. `git diff --check HEAD` → sin salida, exit 0.

No se re-ejecutó `scripts/sf_verify.py` completo; se reprodujeron de forma independiente los controles críticos anteriores (catálogo, contratos, suite, typecheck, build, protegidos, ausencia de cambios prohibidos).

## Hallazgos por severidad

### Bloqueantes
Ninguno.

### Altos
Ninguno.

### Medios
Ninguno.

### Bajos / observaciones no bloqueantes

1. **`woo_sync` con `channels` ya habilitado y no licenciado:** la implementación exige que `channels` esté licenciado incluso cuando `channels` ya está activo y no habría autoactivación. Es más estricto que la letra mínima del packet, pero falla cerrado, no escribe nada y el test lo fija (`test_woo_sync_rechaza_si_channels_esta_activo_pero_no_licenciado`). No rompe el contrato ni la aceptación EARS.
2. **Concurrencia real MariaDB no ejecutada:** límite ya declarado por el packet y las notas. Los locks (`lockForUpdate`), el orden licencia → módulos y el orden estable por `module_code` se validaron estáticamente y funcionalmente sobre SQLite in-memory (`RefreshDatabase`); no se declara verificación concurrente real.
3. **`enable('core')` retorna no-op exitoso sin consultar licencia/solo lectura:** no escribe filas ni auditoría y `core` se trata como infraestructura base siempre disponible. Consistente con el packet, pero es una ruta que evita los gates de licencia por no ser mutante.
4. **Sin test dedicado de `disable` bajo suspensión fuera de gracia:** la ruta existe y llama a `LicenseReadOnlyRule` antes de mutar; la cobertura de suspensión/gracia se ejerce por `enable`. No es un defecto funcional observado.
5. **Lock de licencia serializa globalmente:** el bloqueo de la fila de `license_states` ordena todas las mutaciones de módulos a través de una sola fila. Es seguro y de orden estable, aunque no es granular por módulo; no se observa riesgo funcional.

## Verificación por requisito

1. **Contrato §4.2 — catálogo y dependencias:** 22 códigos exactos y dependencias duras idénticas al contrato. Dependencias blandas coinciden con la tabla contractual; `reports_basic` representa «(todos)» como `['*']`, solo informativo. Las blandas nunca se usan para bloquear. Evidencia: `ModuleCatalog::HARD_DEPENDENCIES`/`SOFT_DEPENDENCIES`, comando PHP de recuento y test `test_catalogo_exacto_de_codigos_y_dependencias_duras_blandas`.
2. **`ModuleManager`:** `states()`, `state()`, `enable()`, `disable()`; código desconocido lanza `DomainException` sin escrituras; `core` no se desactiva y se reporta siempre habilitado sin crear fila; solo se insertan filas de códigos del catálogo (y `channels` en la autoactivación exigida). Evidencia: implementación y tests de estado, `core` y módulo desconocido.
3. **Habilitación fail-closed:** sin licencia, JSON inválido, módulo no incluido ni `["*"]`, suspensión fuera de gracia y dependencias duras faltantes → `DomainException` antes de cualquier escritura. `woo_sync` autoactiva `channels` solo con `channels` licenciado y `catalog` activo; si falta algo, rechazo completo sin cambios. No hay otra autoactivación. Evidencia: tests de licencia, JSON, exclusión, comodín, suspensión/gracia, cadena de dependencias y los tres escenarios `woo_sync`.
4. **Deshabilitación:** bloqueo por dependientes habilitados directos e indirectos (caso `catalog` con `inventory` y `purchasing`); conserva `settings` y `enabled_at`, fija `disabled_at` y `updated_at`; respeta `LicenseReadOnlyRule` antes de mutar. Evidencia: tests de cadena e idempotencia/timestamps, test de `settings`, lectura de código de `disable()`.
5. **Atomicidad:** módulos y auditoría `core.module_enabled`/`core.module_disabled` dentro del mismo `DB::transaction`; rollback completo si la auditoría falla; eventos idempotentes sin duplicado; auditoría sin secretos ni JSON de licencia (solo `module`, `changed_modules`, `actor` y `user_id`); orden de locks licencia primero y filas de módulos por `module_code`. Concurrencia real MariaDB no ejecutada, como límite declarado.
6. **Pruebas exigidas:** catálogo exacto/duro-blando, habilitación/estado/deshabilitación, cadena de dependencias, `woo_sync → channels` y rollback, licencia faltante/JSON inválido/módulo excluido, `["*"]`, suspensión/gracia, `core` no desactivable, idempotencia, auditoría sin secretos, `settings`, módulo no declarado; regresión completa cubierta por la suite (328 pruebas), contratos, typecheck y build.
7. **Cambios prohibidos:** ninguno. Sin rutas/UI/endpoints, sin tablas/campos/migraciones/seeders, sin `config/modules.php`, sin RBAC/entitlements nuevos, sin `canSell/canUseProduct`, y sin tocar `public/docs/index.html`, `resources/roadmap/index.html`, `docs/harness/CONTEXTO-CHATGPT.md`, contratos v2.2, agentes, índices o protocolos.

## Bloqueantes

Ninguno. No hay bloqueantes que impidan `APROBADO`.

## Límites de esta revisión

- No se declara `VERIFIED`; la decisión de verificación corresponde a revisión técnica externa de ChatGPT.
- No se ejecutaron migraciones, seeders, despliegue ni operaciones sobre BD remota.
- No se ejecutó CI GitHub (sin commit/push).
- No se ejecutó concurrencia real MariaDB ni prueba visual de navegador (la feature no tiene UI).