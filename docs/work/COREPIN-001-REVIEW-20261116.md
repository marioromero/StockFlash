# COREPIN-001 — Revisión QA independiente

reviewer: stockflash-reviewer
fecha: 2026-11-16
feature_packet: `docs/work/features/COREPIN-001.md` (APPROVED, CORE-11 / SEC-12 / RN-04–05)
base_sha: `a5b875448be1164afeda58c508a91ce9405aceb5`
estado_revisado: worktree local sobre la base indicada (sin commit ni push)
veredicto: **APROBADO**
bloqueantes: 0
no_bloqueantes: 3
observaciones: 4

---

## 1. Estado exacto verificado

`git status --porcelain`:

```
 M docs/work/GUIA-HTML-ESTADO.md
 M scripts/sf_verify.py
?? app/Support/Authorization/
?? docs/work/COREPIN-001-NOTES-20261116.md
?? docs/work/COREPIN-001-VERIFY-20261116-200922.md
?? docs/work/COREPIN-001-VERIFY-20261116-201156.md
?? docs/work/COREPIN-001-VERIFY-20261116-201419.md
?? docs/work/COREPIN-001-VERIFY-20261116-201840.md
?? tests/Feature/Core/PinAuthorizationServiceTest.php
```

`git diff --stat HEAD`: solo `docs/work/GUIA-HTML-ESTADO.md` (2 líneas) y `scripts/sf_verify.py` (53 líneas), ambos dentro del alcance del packet. `git ls-files --others --exclude-standard` confirma que no hay archivos no rastreados bajo `app/Http`, `routes`, `database/migrations`, `config`, `public`, `resources/roadmap`, `docs/product`, `docs/branding` ni `docs/harness`.

Los hashes declarados en el encargo coinciden exactamente con `git hash-object` (ver §2). Los reportes `-200922.md` y `-201156.md` son ruido no entregable; se ignoraron. `-201419.md` es el reporte de gates del implementador y se contrastó con mi ejecución propia. `-201840.md` lo generó mi propia ejecución de gates (§5); es un artefacto de entorno, no un cambio de código.

---

## 2. Archivos y hashes revisados

| Archivo | SHA-1 (git hash-object) | Rol |
|---|---|---|
| `app/Support/Authorization/PinAuthorizationService.php` | `92478e40197c61fcbbbe91fbf50b54e5a4dcd196` | Nuevo — servicio |
| `app/Support/Authorization/AuthorizationDeniedException.php` | `d200f49b00dc4ea0e35f05064b3c36e9607e35f2` | Nuevo — excepción |
| `tests/Feature/Core/PinAuthorizationServiceTest.php` | `6f4310c5925cbf3d85f92ab303b45a73b039333b` | Nuevo — pruebas |
| `docs/work/COREPIN-001-NOTES-20261116.md` | `b14efeeb34b4a1ffd08c95ac2f39fd1892c95e2c` | Nuevo — notas |
| `scripts/sf_verify.py` | `36f6232791c163a6c6a5e2c74bbd1758ab2cb5a9` | Modificado — gates |
| `docs/work/GUIA-HTML-ESTADO.md` | `5433abdd66842ceda6977a6f5f374f4a4b397c65` | Modificado — metadato |
| `docs/work/features/COREPIN-001.md` | `ab60330f908af3cb9b038d0aa2e9b5f1198e9ce0` | Packet |
| `docs/work/CURRENT.md` | `b10e926a085f6a15184697292feee6ff1f0b86de` | Activación |
| `docs/work/COREPIN-001-VERIFY-20261116-201419.md` | `246305d3a5b8dffa1d45aafe1e0dc8f1eed5f737` | Reporte del implementador |
| `app/Support/AuditLogger.php` | `61fedb4b822ed50612abeecb66043cce40dda7de` | Contexto de auditoría |
| `app/Models/User.php` | `c71c8d7a032f617e08416dc999f765d80776917b` | Modelo de bloqueo/login |
| `app/Http/Controllers/Auth/LoginController.php` | `d23e2a33d430840f231f806e4d4e7560e606d333` | Convención de bloqueo vigente |
| `app/Http/Middleware/CheckPermission.php` | `695b9ac65315dd481bd248ee95b8d2920a6abc0c` | Convención RBAC |
| `app/Http/Middleware/EnsureBranchAccess.php` | `8eec36a0a61fbb8d8afc6a2ddc72aa858f0e5e86` | Convención de acceso a sucursal |
| `database/migrations/2026_10_16_000018_create_authorization_overrides_table.php` | `5092c7d0d237bbd1a6a7521803d6e2df50e58d33` | Esquema AO |
| `database/migrations/2026_10_16_000009_create_users_table.php` | `d1d6469f292d6d441c1ecb088274148db24d507c` | Esquema de PIN |

---

## 3. Resultados por criterio

### C1 — Servicio interno único, sin endpoint/ruta/UI; solicitante desde sesión; nunca PIN/hash — PASS

- Solicitante exclusivamente desde sesión: `PinAuthorizationService.php:102-104` (`Auth::id()`), sin parámetro de solicitante en `authorize()` (`:74-86`).
- Sin rutas/HTTP/UI: `grep` sobre `routes/` no encuentra referencias al servicio; el servicio no importa `Illuminate\Http` ni usa `Route::`, `response`, `view`, `redirect` ni `->json(`. `git status` no muestra cambios en `app/Http`, `routes`, `resources/js`, `public` (CP3/CP5 PASS).
- PIN/hash no salen del método: `$pin` solo se usa en la validación de formato (`:260-282`), en `Hash::check` (`:182`) y como argumento cerrado de la transacción (`:132`); no hay `Log::`, `logger()`, `dump`, `var_dump` ni escritura de PIN/hash. Las auditorías (`:542-654`) no incluyen `pin` ni `pin_hash`. `AuthorizationDeniedException.php:15-22` solo transporta `reason` y mensaje es-CL. Prueba específica: `PinAuthorizationServiceTest.php:481-507` (no aparecen `1357`, `2468` ni el hash en `audit_logs`).

### C2 — Política de solicitante/sucursal/autorizador/rol/permiso/autoridad — PASS

- Solicitante autenticado, activo y no eliminado con acceso: `PinAuthorizationService.php:284-318`; la sucursal debe existir, no estar eliminada y estar activa: `:320-348`.
- Autorizador activo/no eliminado: `:350-380`, revalidado dentro de la transacción sobre la fila bloqueada: `:142-164`.
- Rol aplicable solo global o `branch_user` de ESA sucursal: `:382-400`. No hay permiso cruzado: el rol de otra sucursal no se consulta si el usuario es local a la sucursal solicitada; prueba `PinAuthorizationServiceTest.php:231-264`.
- Permiso real `override.authorize` vía `role_permissions`/`permissions`: `:417-421`.
- Autoridad `>= system_configs.override_min_authority` (fallback 50): `:434-448` y `:679-692`; semilla contractual en `database/seeders/SystemConfigsSeeder.php:29-30` (`override_min_authority=50`, `pin_length=4`, `pin_max_attempts=5`).
- No se atribuye permiso por habilitación de módulo: no hay consulta a `installation_modules` ni `config('modules')`.
- No se inventa prohibición de autoautorización: no existe comparación `requesterId === authorizerId`; se acepta cualquier autorizador identificado (`:118-124`). Coherente con packet §2 y Notes §Decisiones.
- La convención global/sucursal coincide con la vigente: `CheckPermission.php:45-59` y `EnsureBranchAccess.php:61-77`.

### C3 — PIN: hash, formato, lock serializado, lockout persistente, reset y bloqueo — PASS

- `Hash::check`: `:182`.
- Solo dígitos y longitud exacta `pin_length` (fallback 4): `:266-281`.
- `lockForUpdate` del autorizador dentro de `DB::transaction`: `:128-152`.
- `pin_max_attempts` (fallback 5): `:183-185`; al alcanzar el máximo fija `pin_locked_until = now() + 30 min`: `:192-194`; misma ventana de 30 min que el login vigente (`User.php:138-146`, `LoginController.php:55-65`).
- Persistencia al rechazar: los contadores, `pin_locked_until` y la auditoría se escriben dentro de la transacción (`:196-202`); el resultado `denied` retorna para confirmar el commit y **la excepción se lanza después**, fuera de la transacción (`:206-208` y `:250-255`). Verificado por `test_wrong_hash_increments_attempts_sequentially` (`:326-345`) y `test_five_failures_lock_authorizer...` (`:347-373`).
- Éxito resetea contadores y bloqueo: `:211-215`; probado en `:401-424`.
- Bloqueado no permite éxito aunque el PIN sea correcto: `:166-172`; probado en `:375-399`.
- Evaluación de PIN con formato inválido sin contar intento (Notes §Decisiones): **defendible**. Un PIN no numérico o de longitud distinta no puede coincidir con el hash de un PIN numérico de la longitud configurada, por lo que no habilita ninguna vía de aprobación; solo evita el lockout ante entradas malformadas. No se detectó bypass real hacia éxito ni hacia autorización.

### C4 — Éxito: una fila AO append-only, motivo, auditoría segura, atomicidad — PASS

- Una sola inserción en `authorization_overrides` con `type`, solicitante, autorizador, sucursal, estación, referencia, `old_value`/`new_value`, `reason_code_id` y `reason`: `:217-230`. No hay `UPDATE`/`DELETE` sobre AO ni reutilización: cada éxito inserta una fila nueva y retorna su ID (`:247`, `:257`).
- Tipo contractual validado contra los 11 valores del enum de Parte 2 §4.18: `:33-45` y `:89-91`; coincide con `2026_10_16_000018_...:14`.
- `reason_code` existente, activo y scope `override`; `requires_note` exige nota: `:475-515`; probado en `:550-612`.
- Auditoría con `is_security_event=1` y sin PIN/hash: `:556-577` y `AuditLogger.php:60-75`; la aprobación no contiene secretos (`:562-573`).
- Atomicidad AO + auditoría: ambas dentro de la misma transacción (`:128-248`); si la auditoría falla, revierte todo. Prueba significativa con `AuditLogger` que lanza excepción inyectada: `PinAuthorizationServiceTest.php:509-548`; verifica `authorization_overrides == 0` (`:545`) y contadores intactos (`:546-547`).

### C5 — Alcance: sin bypass general, sin venta/descuento/caja, sin rutas/UI, sin migraciones/RBAC — PASS

- No implementa elegibilidad de venta, descuento, caja ni devolución; solo verifica PIN/política y persiste la aprobación. El único valor de retorno es el ID (`:257`).
- Sin rutas, controladores, endpoints ni componentes de interfaz (grep sin resultados en `routes/`; CP3 y CP5 PASS).
- Sin migraciones, tablas, columnas, seeders, RBAC, `config/modules.php` ni cambios de contrato: `git status` y `git diff --stat` lo confirman; CP5 y CP6 PASS.
- No toca `public/docs/index.html`, `resources/roadmap/index.html`, `CONTEXTO-CHATGPT.md`, `AGENTS.md`, `opencode.json`, `docs/product` ni `docs/branding`: CP6 PASS y `git ls-files --others` filtrado sin coincidencias.
- `docs/work/GUIA-HTML-ESTADO.md` solo agrega la marca de feature interna invisible (`internal_only_features`); no modifica contenido editorial de la guía.

### C6 — Cobertura y significancia de pruebas — PASS

`tests/Feature/Core/PinAuthorizationServiceTest.php`: **28 pruebas, 103 aserciones** (coincide con Notes §Pruebas). Mapeo exigido:

- Permisos/autoridad global y por sucursal: `:21-52` (global), `:54-90` (rol de sucursal), `:92-110` (autoridad insuficiente), `:112-129` (sin permiso), `:231-264` (rol cruzado).
- Usuario desactivado: autorizador `:131-148`; solicitante `:651-671`; autorizador eliminado `:150-168`.
- Sucursal no asignada/inactiva: `:192-209` y `:211-229`.
- PIN faltante/no numérico/longitud: `:266-324`.
- Hash erróneo e intentos secuenciales: `:326-345`.
- Cinco fallos y bloqueo: `:347-373` (verifica 29–31 minutos).
- Expiración y reset: `:401-424`.
- Éxito con AO y contexto: `:21-52` y `:426-457`.
- Auditoría sin PIN/hash: `:481-507`.
- Rollback de AO si falla auditoría: `:509-548` (fallo realmente forzado por logger inyectado, no simulado por aserción).
- `reason_code` inválido/inactivo/scope/nota: `:550-612`.
- Tipo inválido: `:614-631`.
- Sin sesión: `:633-649`.
- Otras configuraciones intactas: `:673-702`.

Las pruebas no son triviales: usan el servicio real, base migrada y sembrada (`UserTestCase`), y verifican efectos persistidos, no solo códigos de retorno.

### C7 — Gates CP1–CP17, sin scripts/agentes nuevos ni debilitamiento — PASS

- `scripts/sf_verify.py` solo agrega la entrada `COREPIN-001` a `GATES_BY_PACKET` (diff de 53 líneas); no se crean scripts, agentes ni índices nuevos.
- Los gates incluyen verificaciones reales de suite, contratos, typecheck, build, diff hygiene y metadatos; no son trivialidades.
- Ejecución propia: **17/17 PASS**, `Veredicto: GATES_PASS` (§5).

### C8 — Riesgo de seguridad y coherencia con login — PASS (con hallazgos no bloqueantes)

- TOCTOU: ver NB-1.
- Filtración de información: mensajes y auditorías no contienen PIN/hash; códigos de rechazo distintos permiten enumeración limitada de estado del autorizador (NB-3).
- `audit_logs` usa `is_security_event=1` en aprobación, fallo de PIN, lockout y denegaciones: `:575-577`, `:601-603`, `:626-628`, `:651-653`.
- Coherencia con bloqueo de login: ambos usan 5 intentos y 30 minutos (`User.php:138-146`); el servicio usa `pin_max_attempts` y `pin_locked_until` (`:183-194`), sin tocar `failed_login_attempts`/`locked_until` de login.

---

## 4. Hallazgos

### Bloqueantes

Ninguno.

### No bloqueantes

**NB-1 — TOCTOU entre política y transacción.**
La política de rol, permiso, autoridad, acceso del solicitante y estado de sucursal se evalúa antes de abrir la transacción (`:122-126`). Dentro de la transacción solo se revalidan `is_active`, `deleted_at`, `pin_locked_until` y `pin_hash` del autorizador (`:142-180`). Si un administrador revoca `override.authorize` o cambia el rol entre ambos momentos, la aprobación podría crearse con la política previa. Ventana mínima y sin endpoint que la exponga hoy; no contradice la atomicidad AO+auditoría exigida. Recomendación para integración POS: revalidar rol/permiso/autoridad dentro de la transacción bloqueada o ejecutar política+inserción en una sola transacción.

**NB-2 — CP6 no detecta archivos no rastreados en directorios protegidos.**
`CP6-protegidos-intactos` usa `git diff --quiet HEAD`, que ignora untracked. CP5 cubre con `git status --porcelain` `public`, `routes`, `app/Http`, `database/migrations`, `resources/js` y `config/modules.php`, pero no `resources/roadmap`, `docs/product`, `docs/branding` ni `docs/harness`. En el estado revisado no existe ninguna adición untracked en esos directorios (verificado con `git ls-files --others --exclude-standard`), por lo que no hay violación actual. Recomendación: complementar CP6 con `git status --porcelain -- <rutas protegidas>`.

**NB-3 — Códigos de rechazo distinguibles para el solicitante autenticado.**
El servicio devuelve códigos estables y distintos (`authorizer_not_found`, `authorizer_inactive`, `authorizer_role_missing`, `authorizer_permission_denied`, `authorizer_authority_insufficient`; `:360-447` y `:669-677`). Un usuario autenticado puede inferir estado/permisos de otro usuario. Impacto bajo en contexto interno y con autorizador habitualmente conocido; se recomienda conservar el código interno pero exponer al exterior un mensaje genérico cuando se construya el endpoint/UI.

### Observaciones

**OB-1 — `pos_station_id` no exige `is_active=1`.** Se valida existencia, pertenencia a la sucursal y no eliminación (`:451-472`), pero no que esté activa. El packet no lo exigió; no bloquea.

**OB-2 — Formato de PIN inválido no incrementa intentos.** Evaluado como defendible: no permite acertar el hash y no abre bypass hacia aprobación; solo evita lockout por entradas malformadas. Recomendación: dejar explícita esta decisión en la futura guía/API.

**OB-3 — Autoauthorización permitida.** No se prohíbe, conforme al packet; no hay prueba específica de autoautorización, pero la ruta de código es la misma y el contrato no exige probarla por separado.

**OB-4 — Actor de auditoría.** `AuditLogger::record` fija `user_id` con `Auth::id()` (solicitante) (`AuditLogger.php:61`); el autorizador queda en `new_values.authorized_by_user_id`. Es trazable, pero conviene que los consumidores de auditoría sepan que el actor de sesión es el solicitante, no el autorizador.

---

## 5. Comandos ejecutados y resultados

| Comando | Resultado |
|---|---|
| `git status --porcelain` | Coincide con el estado declarado; solo los 6 archivos del entregable + reportes de gates |
| `git hash-object <archivos>` | Coinciden exactamente los 6 hashes declarados (§2) |
| `py -3 scripts/sf_verify.py COREPIN-001` | **GATES_PASS — 17/17 PASS** (CP1–CP17, exit=0). Reporte generado: `docs/work/COREPIN-001-VERIFY-20261116-201840.md` |
| `php artisan test tests/Feature/Core/PinAuthorizationServiceTest.php` | **28 passed (103 assertions)**, exit=0 |
| `php artisan test tests/Feature/Core` | **94 passed (512 assertions)**, exit=0 |
| `php artisan test` | **356 passed (2359 assertions)**, exit=0 |

Desglose de gates ejecutados: CP1 packet/activo PASS; CP2 archivos PASS; CP3 sin rutas/UI/HTTP PASS; CP4 invariantes PASS; CP5 sin cambios de esquema/UI/config PASS; CP6 protegidos intactos PASS; CP7 contratos PASS; CP8 Core PASS; CP9 Auth PASS; CP10 Branches PASS; CP11 Users PASS; CP12 Settings PASS; CP13 suite completa PASS; CP14 typecheck PASS; CP15 build PASS; CP16 metadatos internos PASS; CP17 diff check PASS.

---

## 6. Límites de esta revisión

- **Concurrencia MariaDB real no ejecutada.** La suite usa SQLite en memoria, donde `lockForUpdate` no impone bloqueo real. El bloqueo concurrente entre solicitudes del mismo autorizador queda pendiente de validación predespliegue contra una instancia MariaDB aislada autorizada, tal como declara `docs/work/COREPIN-001-NOTES-20261116.md:43`.
- No se ejecutaron migraciones, seeders, despliegues ni operaciones sobre BD remota.
- No se verificó CI de GitHub (no hubo commit/push en esta revisión).
- No se evaluó comportamiento bajo endpoint/UI porque el packet no los incluye.

---

## 7. Veredicto final

**APROBADO** — 0 bloqueantes, 3 hallazgos no bloqueantes y 4 observaciones.

Habilita la publicación selectiva por `stockflash-publisher` según la regla 15, sobre idéntico estado y con `GATES_PASS` vigente. Este dictamen **no es `VERIFIED`**, no autoriza despliegue y no constituye aprobación de staging/producción ni de BD remota.