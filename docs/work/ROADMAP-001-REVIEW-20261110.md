# ROADMAP-001 — Revisión QA independiente

- **Fecha:** 2026-11-10
- **Revisor:** QA independiente (OpenCode reviewer), solo lectura
- **Base publicada:** `3fde69148fa8306eaa2e5a9d2da57ae7450c676b` (cierre DOCS-001 VERIFIED)
- **PR revisado:** `refs/remotes/origin/pr-3` = `07eb72ee8432e84f8aa8cd6fbf5caeb66fdf4829`
- **Estado revisado:** working tree sobre `3fde691`; PR #3 no fusionado; 14 archivos rastreados modificados y 8 rutas no rastreadas.
- **Evidencia de gates:** `docs/work/ROADMAP-001-VERIFY-20261110-192333.md` = `GATES_PASS` (RM1–RM15), emitido por `scripts/sf_verify.py`; no re-ejecutado por QA.
- **Veredicto:** **APROBADO** (QA independiente). No se declara VERIFIED.

## 1. Resumen ejecutivo

La integración selectiva del PR #3 está correctamente aplicada:

- No se fusionó el PR #3 completo.
- Los archivos de contenido de ChatGPT, `AGENTS.md` y los cinco comandos del harness son idénticos al PR #3.
- La bitácora `ROADMAP-HTML-ESTADO.md` solo difiere en metadatos técnicos autorizados.
- La ruta `/roadmap` está protegida en servidor y el HTML vive fuera de `public/`.
- El botón «Avance del proyecto» está condicionado a `settings.manage` y usa `rel="noopener noreferrer"`.
- DOCS-001 permanece VERIFIED y USERS-001 permanece DRAFT e intacto.
- No hay cambios de permisos, migraciones, deploy, `docs/product/*` ni secretos.

No hay hallazgos bloqueantes. Las observaciones restantes son documentales u operativas y no impiden la aprobación QA.

## 2. Verificación por puntos

| Punto | Resultado | Evidencia |
|---|---|---|
| Hashes de archivos ChatGPT/AGENTS/comandos vs PR #3 | PASS | 10/10 blobs Git idénticos; ver §3 |
| `ROADMAP-HTML-ESTADO.md` solo metadatos | PASS | Diff limitado a estado, SHA, fecha, cobertura, pendiente y `report_state`; texto de mantenimiento intacto |
| OpenCode no redactó ni alteró manual, roadmap, protocolo ni contexto | PASS | `public/docs/index.html`, `CONTEXTO-CHATGPT.md`, `resources/roadmap/index.html` y `ROADMAP-HTML-PROYECTO.md` con blob idéntico al PR #3 |
| Invitado en `/roadmap` → login | PASS por inspección y test | Grupo `auth` + test `test_guest_is_redirected_to_login`; gate RM6 reporta 7/7 |
| Autenticado sin `settings.manage` → 403 | PASS por inspección y test | `can.permission:settings.manage` + test `test_authenticated_user_without_settings_manage_gets_403` |
| Admin Central con `settings.manage` → 200 HTML | PASS por inspección y test | Closure de ruta + test `test_admin_with_settings_manage_can_view_the_roadmap` |
| HTML fuera de `public/` y no público | PASS | `resources/roadmap/index.html` existe; `public/roadmap/index.html` no existe |
| Smoke CI no acepta 200 en `/roadmap` | PASS | `.github/workflows/ci.yml` rechaza 200 y acepta solo 302/403 |
| Botón «Avance del proyecto» visible solo con permiso | PASS | `v-if="canViewRoadmap"` derivado de `settings.manage` |
| Botón accesible y con `rel` seguro | PASS | `target="_blank"`, `rel="noopener noreferrer"`, `aria-label` correcto |
| Roadmap R0–R10 y R7 primera versión comercial | PASS | `resources/roadmap/index.html` contiene R0–R10 y R7 “Primera versión vendible” |
| Estados, acordeones, expandir/contraer, responsive y accesibilidad | PASS | `details.step`, botones `expand`/`collapse`, `@media(max-width:640px)`, `prefers-reduced-motion`, foco visible y etiquetas ARIA |
| Sin recursos externos ni secretos | PASS | Sin `http://`, `https://`, `fetch(`, `XMLHttpRequest`, `<script src=`, formularios ni contraseñas en el HTML |
| Automatización `GUIA_*` y `ROADMAP_*` en reporte/consola | PASS | `scripts/sf_verify.py` incluye `roadmap_handoff`; reporte con secciones GUIA y ROADMAP; consola imprime ambos estados |
| Bitácoras declaran pendientes reales | PASS con observación | GUIA pendiente del botón; ROADMAP pendiente de actualización DOCS-001; ver O-01 |
| DOCS-001 VERIFIED no revertido | PASS | `features/DOCS-001.md` sigue `status: VERIFIED`; cierre presente; CURRENT conserva VERIFIED; PR #3 no aplicado en esos archivos |
| USERS-001 intacto | PASS | Cuatro hashes sin cambios; `status: DRAFT`; sin diff vs base |
| Sin cambios de permisos, migraciones, deploy, `docs/product/*` o secretos | PASS | `git status` no muestra esas rutas; búsqueda sin secretos/PII reales |
| Consistencia packet/CURRENT/gates/reporte/bitácoras | PASS con observaciones | `ROADMAP-001.md` APPROVED, CURRENT integrado, reporte GATES_PASS, bitácoras en `*_PENDIENTE_CHATGPT`; ver O-02 y O-03 |
| Reportes DOCS locales superados excluidos del commit | PASS operativo | Ambos están untracked; no deben agregarse al staging |
| Branding | PASS | Fallback textual StockFlash; sin PNG oficiales declarados; etiquetas POS/Bodega/SyncWP/Fiscal/Compras/Analítica correctas |
| Seguridad/privacidad | PASS | Permiso en backend, sin PII, sin secretos, `Cache-Control: no-store`, sin entrada de usuario en la ruta |

## 3. Hashes comparados

Método: `git hash-object -- <archivo>` contra `git rev-parse origin/pr-3:<archivo>`. El repositorio usa `core.autocrlf=true`; el working tree Windows tiene CRLF, pero el blob que Git commitearía se normaliza a LF y coincide con el PR. La comparación es a nivel de contenido de commit, no de bytes crudos del checkout.

### 3.1 Archivos de contenido ChatGPT, AGENTS y comandos

| Archivo | Blob PR #3 | Blob working tree | Resultado |
|---|---|---|---|
| `public/docs/index.html` | `c6e43e75455f988536022e759588301a48344ee4` | `c6e43e75455f988536022e759588301a48344ee4` | Idéntico |
| `docs/harness/CONTEXTO-CHATGPT.md` | `03a088d0d7c86caf694a102ed5a1081b427989c4` | `03a088d0d7c86caf694a102ed5a1081b427989c4` | Idéntico |
| `resources/roadmap/index.html` | `6a5c71941d0bf6293351e64deb3c34e461a4b29c` | `6a5c71941d0bf6293351e64deb3c34e461a4b29c` | Idéntico |
| `docs/harness/ROADMAP-HTML-PROYECTO.md` | `c3d0204c0365263372716db3f3feb1a8e0a32312` | `c3d0204c0365263372716db3f3feb1a8e0a32312` | Idéntico |
| `AGENTS.md` | `dca7df418e3eebb1a3f6ad71de94ffdee05d4e28` | `dca7df418e3eebb1a3f6ad71de94ffdee05d4e28` | Idéntico |
| `.opencode/commands/sf-run.md` | `f99a8751e85da56edcd92d897c8f9fbafa90a18b` | `f99a8751e85da56edcd92d897c8f9fbafa90a18b` | Idéntico |
| `.opencode/commands/sf-feature.md` | `945afc462ea38306e2fc10d3c6ce0316304246fa` | `945afc462ea38306e2fc10d3c6ce0316304246fa` | Idéntico |
| `.opencode/commands/sf-review.md` | `e1d2d17237eb7450b13b547dc33754554f679b10` | `e1d2d17237eb7450b13b547dc33754554f679b10` | Idéntico |
| `.opencode/commands/sf-publish.md` | `f33f4dfe708e052680433cc7ea458f7ba88be94d` | `f33f4dfe708e052680433cc7ea458f7ba88be94d` | Idéntico |
| `.opencode/commands/sf-handoff.md` | `fc92429e5063e1558ed7fedab5394d84690862e3` | `fc92429e5063e1558ed7fedab5394d84690862e3` | Idéntico |

### 3.2 Bitácora técnica

| Archivo | Blob PR #3 | Blob working tree | Resultado |
|---|---|---|---|
| `docs/work/ROADMAP-HTML-ESTADO.md` | `ef6ecac03829029650f00fd1c94b467cf373283e` | `5d4ebc9ba45e6de43d81b76aa67ae87d910ae047` | Difiere solo en metadatos autorizados |

Metadatos modificados: `roadmap_content_status`, `roadmap_integration_sha`, `roadmap_integration_date`, `covered_features`, `pending` y `report_state`. El bloque “Mantenimiento por OpenCode” y el resto del texto permanecen intactos.

### 3.3 USERS-001

| Archivo | Blob Git | Coincide con hash esperado |
|---|---|---|
| `docs/work/features/USERS-001.md` | `b08875c7da8b9747c1da5eeadc0f158478ee063a` | Sí (`b08875c…`) |
| `docs/work/USERS-001-DRAFT-REVIEW-20261110-2.md` | `e234e0da2a8d8db81f913c90adede8956a24503b` | Sí (`e234e0d…`) |
| `docs/work/USERS-001-DRAFT-QA-CONFIRM-20261110.md` | `69b8b28b3ce151481b1cd9a2586f73fb8b78f459` | Sí (`69b8b28…`) |
| `docs/work/USERS-001-DRAFT-REVIEW-20261110.md` | `33e8ace36e3f17928cadabf2bbb9fcf946f25f9d` | Sí (`33e8ace…`) |

Sin diferencias respecto de `3fde691`.

## 4. Evidencia de no reversión de DOCS-001 y de no fusión del PR #3

- `docs/work/features/DOCS-001.md` conserva `status: VERIFIED`.
- `docs/work/DOCS-001-CLOSURE-20261110.md` existe en el working tree y no fue borrado.
- `docs/work/CURRENT.md` mantiene DOCS-001 como VERIFIED.
- El PR #3, por haber nacido antes de `3fde691`, contiene `docs/work/features/DOCS-001.md` en `APPROVED` y no contiene el cierre; esas dos rutas **no** se aplicaron.
- `git diff --diff-filter=D 3fde691` no reporta eliminaciones.
- `git merge-base --is-ancestor 07eb72e HEAD` devuelve exit 1: el PR #3 no está fusionado en la base.

## 5. Riesgos y observaciones no bloqueantes

**O-01 — CONTEXTO-CHATGPT y roadmap HTML desactualizados respecto de DOCS-001.**  
`docs/harness/CONTEXTO-CHATGPT.md` (integrado verbatim del PR #3) sigue describiendo DOCS-001 como `APPROVED`/pendiente de VERIFIED y el PR #3 como pendiente de integración. `resources/roadmap/index.html` también indica DOCS-001 pendiente. No es un defecto de OpenCode: el archivo es de autoría exclusiva de ChatGPT y el packet declara la actualización editorial como pendiente. **Acción:** incluir ambos textos en el handoff a ChatGPT. No editar por OpenCode.

**O-02 — SHA de referencia en `sf_verify`.**  
`roadmap_handoff` prioriza `baseline_code_sha` (90254e5) sobre `roadmap_integration_sha` (07eb72e) para el reporte. Es un SHA válido, pero el handoff sería más claro mostrando el SHA de integración. No afecta gates.

**O-03 — Redacción “verbatim” en `ROADMAP-001.md`.**  
El packet dice que `ROADMAP-HTML-ESTADO.md` se integra verbatim, aunque ese archivo tiene cambios de metadatos autorizados. Ajustar la redacción ayuda a evitar una lectura literal incorrecta.

**O-04 — Exclusión de reportes DOCS superados.**  
`docs/work/DOCS-001-VERIFY-20261110-183917.md` (GATES_FAIL) y `docs/work/DOCS-001-VERIFY-20261110-184036.md` (GATES_PASS, superado por `184136`) siguen untracked. Deben quedar fuera del staging/commit. Incluir `ROADMAP-001-VERIFY-20261110-192333.md` y este review.

**O-05 — Endurecimiento opcional del smoke CI.**  
El smoke acepta cualquier 302/403; no comprueba que el 302 apunte a `/login`. El test de feature sí cubre el redirect a login. No es bloqueante.

**O-06 — CRLF en working tree.**  
Los archivos de texto se ven con CRLF por el checkout Windows. Con `core.autocrlf=true`, el blob de commit se normaliza a LF y coincide con el PR. No hay alteración de contenido; solo una diferencia de bytes crudos del working tree.

## 6. Resultados no ejecutados

No se ejecutaron, por la restricción de solo lectura y prohibición de migraciones:

- `php artisan test tests/Feature/Roadmap`
- `php artisan test` (suite completa)
- Regresiones `tests/Feature/Auth`, `tests/Feature/Branches` y `tests/Feature/Docs`
- `npm run typecheck`
- `npm run build`
- `py -3 scripts/check_contracts.py`
- Smoke HTTP real de CI
- Migraciones, seeders, deploy o staging

La evidencia de esos resultados proviene del reporte `ROADMAP-001-VERIFY-20261110-192333.md` (GATES_PASS; RM6 7/7, RM7 113/586, regresiones verdes). Esta QA no los re-ejecutó.

Comprobaciones estáticas sí realizadas: `php -l` sobre `routes/web.php` y el test; compilación en memoria de `scripts/sf_verify.py`; `git diff --check`; lectura de archivos untracked con `git ls-files --others --exclude-standard`; comparación de blobs con `git hash-object`; inspección de rutas, middleware, Dashboard y HTML.

## 7. Constancia de no implementación indebida

- QA actuó en solo lectura: no modificó archivos, no creó archivos, no hizo commit, push ni deploy.
- No ejecutó migraciones, seeders, staging ni acceso a producción.
- No editó `public/docs/index.html`, `resources/roadmap/index.html`, `docs/harness/CONTEXTO-CHATGPT.md` ni `docs/harness/ROADMAP-HTML-PROYECTO.md`.
- No fusionó el PR #3; no aplicó sus cambios sobre `DOCS-001-CLOSURE` ni `features/DOCS-001.md`.
- No cambió permisos, RBAC, contratos v2.2 ni `docs/product/*`.
- No leyó `.env`, dumps, claves ni secretos; no registró PII.
- No ejecutó `scripts/safe_git_pull.py`; usó los refs locales existentes.

## 8. Veredicto

**APROBADO** por QA independiente sobre el estado exacto revisado.

Esta revisión **no declara VERIFIED** ni cierra ROADMAP-001. La decisión VERIFIED corresponde al propietario y queda fuera de este artefacto. Las observaciones O-01 a O-06 son no bloqueantes y deben trasladarse al handoff de ChatGPT/publicación según corresponda.
