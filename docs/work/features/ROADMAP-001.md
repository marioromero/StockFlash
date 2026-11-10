# ROADMAP-001 — Ruta administrativa para el roadmap HTML del propietario

status: VERIFIED
owner_approval: Decisión expresa del propietario (2026-11-10): autoriza la integración del PR #3 y la implementación de una ruta `/roadmap` restringida a administradores, con enlace «Avance del proyecto» en Dashboard, verificación, QA y publicación. ChatGPT mantiene la autoría exclusiva de `resources/roadmap/index.html` y `CONTEXTO-CHATGPT.md`; OpenCode no los redacta ni modifica.
verified_decision: Decisión externa del propietario (2026-11-10): ROADMAP-001 **VERIFIED**, respaldada por implementación en `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41`, gates `GATES_PASS` (RM1–RM15), QA independiente `APROBADO` y CI remota SUCCESS verificada externamente por ChatGPT.
etapa: HARNESS-DOCS / ROADMAP
dependencias: PR #3 (`refs/pull/3/head` `07eb72ee8432e84f8aa8cd6fbf5caeb66fdf4829`), PR #4 (`refs/pull/4/head` `6601a2a3cf230581f1fe733d1ea57d2060eb39ab`), `main` `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41`, DOCS-001 VERIFIED (2026-11-10), AUTH-001 VERIFIED, BRANCH-001 VERIFIED, USERS-001 DRAFT intacto.

## Qué / Por qué / No incluido

**Qué.** Servir el roadmap HTML redactado por ChatGPT desde `resources/roadmap/index.html` en `GET /roadmap`, protegido con `auth`, `password.change`, `verify.session` y `can.permission:settings.manage`. Añadir el botón «Avance del proyecto» al Dashboard, visible solo para administradores. Incorporar el protocolo de autoría, la bitácora técnica y el seguimiento automático `ROADMAP_ACTUALIZADA`/`ROADMAP_PENDIENTE_CHATGPT` al harness.

**Por qué.** El propietario aprobó el PR #3. El roadmap debe estar accesible para administración sin exponerse públicamente y sin que OpenCode altere los textos o estados de ChatGPT.

**No incluido.**
- Redactar, modificar, corregir o autogenerar `resources/roadmap/index.html` o `docs/harness/CONTEXTO-CHATGPT.md`: autoría exclusiva de ChatGPT.
- Cambiar hitos, estados, porcentajes, fechas o capacidades comerciales.
- Exponer el HTML en `public/` o mediante una ruta sin autorización.
- Implementar USERS-001, migraciones, deploy o staging.
- Cambiar permisos de OpenCode o reconstruir el harness.

## Archivos integrados del PR #3

Se integran verbatim desde `refs/pull/3/head` (`07eb72e`):

- `public/docs/index.html` — actualización editorial de ChatGPT: botón «Ayuda» y búsqueda.
- `docs/harness/CONTEXTO-CHATGPT.md` — contexto de continuidad de ChatGPT.
- `resources/roadmap/index.html` — roadmap R0–R10, responsive e interactivo.
- `docs/harness/ROADMAP-HTML-PROYECTO.md` — protocolo de autoría exclusiva.
- `docs/work/ROADMAP-HTML-ESTADO.md` — bitácora técnica (OpenCode actualiza metadatos, no el HTML).
- `AGENTS.md` — regla 19 de roadmap.
- `.opencode/commands/sf-run.md`, `sf-feature.md`, `sf-review.md`, `sf-publish.md`, `sf-handoff.md` — seguimiento roadmap.

OpenCode no modifica los textos redactados ni el contexto de ChatGPT. Solo actualiza metadatos técnicos en la bitácora.

## Acceso e interfaz

- `GET /roadmap` sirve el HTML desde `resources/roadmap/index.html` (fuera de `public/`).
- Middleware: `auth`, `password.change`, `verify.session`, `can.permission:settings.manage`.
- Sin autenticación: redirección a login. Autenticado sin `settings.manage`: 403. Con permiso: 200 HTML.
- Dashboard: botón «Avance del proyecto» visible solo si el usuario posee `settings.manage`; abre `/roadmap` en pestaña nueva con `rel="noopener noreferrer"` y etiqueta accesible.
- La guía pública `/docs/` permanece independiente y pública.

## Aceptación (EARS)

- CUANDO un visitante no autenticado solicita `GET /roadmap`, EL SISTEMA DEBE redirigir al login.
- CUANDO un usuario autenticado sin `settings.manage` solicita `GET /roadmap`, EL SISTEMA DEBE responder 403.
- CUANDO un Admin Central con `settings.manage` solicita `GET /roadmap`, EL SISTEMA DEBE responder 200 con el HTML del roadmap.
- CUANDO se inspecciona el despliegue, EL ARCHIVO `resources/roadmap/index.html` NO DEBE existir bajo `public/` ni servirse por una ruta pública.
- CUANDO un administrador abre el Dashboard, EL SISTEMA DEBE mostrar «Avance del proyecto» solo con permiso y el enlace DEBE apuntar a `/roadmap`.
- CUANDO se revisa el roadmap, EL SISTEMA DEBE mostrar R0–R10, la primera versión comercial R7, estados, acordeones, expandir/contraer, responsive y accesibilidad, sin recursos externos ni secretos.
- CUANDO se ejecuta `sf_verify`, EL REPORTE DEBE incluir `ROADMAP_ACTUALIZADA` o `ROADMAP_PENDIENTE_CHATGPT`, además de `GUIA_ACTUALIZADA`/`GUIA_PENDIENTE_CHATGPT`.
- CUANDO el contenido de ChatGPT está desactualizado respecto de hitos/estados, EL CIERRE DEBE informar `ROADMAP_PENDIENTE_CHATGPT` con SHA, packet, QA/CI y cambios, sin editar el HTML.
- CUANDO se ejecutan pruebas y gates, LA SUITE PHP, TYPECHECK, BUILD, CONTRATOS Y REGRESIONES DEBEN quedar verdes.
- CUANDO se publica, USERS-001 DEBE permanecer DRAFT, sin implementación.

## Plan técnico

- Ruta `roadmap.index` en `routes/web.php` dentro del grupo protegido, con `can.permission:settings.manage`, sirviendo `resource_path('roadmap/index.html')`.
- Botón «Avance del proyecto» en `resources/js/Pages/Dashboard.vue`, condicionado por `settings.manage`.
- `tests/Feature/Roadmap/RoadmapAccessTest.php`: invitado, sin permiso, autorizado, archivo no público, estructura R0–R10, interactividad/responsive, enlace Dashboard.
- Ampliar `.github/workflows/ci.yml`: `/roadmap` sin autenticación no debe responder 200.
- Extender `scripts/sf_verify.py`: `roadmap_handoff` y gates `ROADMAP-001`; estado en reporte y consola.
- Actualizar `docs/work/ROADMAP-HTML-ESTADO.md` (metadatos), `docs/work/GUIA-HTML-ESTADO.md` (pendiente del botón) y `docs/work/CURRENT.md`.
- No modificar `resources/roadmap/index.html`, `docs/harness/CONTEXTO-CHATGPT.md`, `docs/harness/ROADMAP-HTML-PROYECTO.md` ni `public/docs/index.html` respecto del PR #3.

## Gates

- [ ] packet `ROADMAP-001` APPROVED y activo en `CURRENT.md`.
- [ ] archivos del PR #3 y protocolo/bitácora presentes; autoría ChatGPT intacta byte a byte.
- [ ] `/roadmap` protegido con `auth` + `settings.manage`; sin archivo público.
- [ ] botón «Avance del proyecto» visible solo con permiso.
- [ ] HTML con R0–R10, R7, estados, acordeones, responsive, accesibilidad, sin recursos externos.
- [ ] tests de roadmap (invitado, 403, 200, no público, estructura, enlace).
- [ ] suite completa, regresiones AUTH-001/BRANCH-001/DOCS-001, typecheck y build.
- [ ] contratos OK.
- [ ] reporte `sf_verify` con estado `ROADMAP_*` y `GUIA_*`.
- [ ] reviewer independiente `APROBADO` sobre el estado exacto.

## Cierre VERIFIED (2026-11-10)

- **Decisión externa:** el propietario confirmó ROADMAP-001 **VERIFIED**.
- **Implementación:** `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41` en `origin/main`; sin cambios de código en este cierre.
- **Actualización editorial integrada:** PR #4 (`6601a2a`) — manual con «Avance del proyecto», roadmap con DOCS-001 VERIFIED y referencia `a9c19d4`, contexto actualizado. Integración selectiva, sin fusionar el PR.
- **Gates:** `docs/work/ROADMAP-001-VERIFY-20261110-194620.md` → `GATES_PASS` (RM1–RM15).
- **QA independiente:** `docs/work/ROADMAP-001-DOCS-REVIEW-20261110-2.md` → `APROBADO` (0 bloqueantes); ronda 1 `CAMBIOS NECESARIOS` corregida.
- **CI remota:** PHP/Node/smoke SUCCESS (run `42915292666`) y contratos SUCCESS (run `42915292580`) sobre `a9c19d4`, verificados externamente por ChatGPT.
- **Pendiente de autoría ChatGPT:** actualizar `resources/roadmap/index.html` y `docs/harness/CONTEXTO-CHATGPT.md` para reflejar ROADMAP-001 VERIFIED y el gate vigente (`194620`). El manual ya no tiene pendientes (`GUIA_ACTUALIZADA`); el roadmap queda `ROADMAP_PENDIENTE_CHATGPT`.
- **Alcance del cierre:** exclusivamente documental. No declara deploy, staging ni migraciones.
- **Artefacto:** `docs/work/ROADMAP-001-CLOSURE-20261110.md`.

## Estado de funciones

- **Base:** AUTH-001, BRANCH-001 y DOCS-001 VERIFIED; USERS-001 DRAFT; manual `/docs/`, Dashboard con Ayuda y automatización `GUIA_*`.
- **Esta integración:** roadmap HTML de ChatGPT, protocolo y bitácora, ruta `/roadmap` protegida, botón «Avance del proyecto», pruebas, smoke de CI y automatización `ROADMAP_*`.
- **Pendiente de autoría ChatGPT:** actualizar `resources/roadmap/index.html` y `docs/harness/CONTEXTO-CHATGPT.md` para reflejar ROADMAP-001 VERIFIED y el gate vigente `194620`. El manual ya documenta «Avance del proyecto». OpenCode no redacta ni modifica esos contenidos.
