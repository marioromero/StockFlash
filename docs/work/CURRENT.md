# Estado operativo — StockFlash (2026-10-14)

## Trabajo piloto autorizado — 2026-11-12

bridge_active_packet: BRIDGE-001

Actualización de diagnóstico: primer intento de escritorio creó StockFlashAgent
y aplicó ACL, pero falló en receipt por File.Replace con `$null` en PowerShell 5.1.
Corregido con null string tipado; regresión real pasa, preflight de estado parcial
pasa y lock está libre. No se relanzó aprovisionamiento. Próximo paso: reanudar
Start-Provision.ps1 desde PowerShell normal. Ver BRIDGE-001-PROVISION-RECEIPT-FIX.md.
Cuenta existente no equivale a aislamiento aprobado; probe bajo esa identidad pendiente.

El propietario autorizó publicación continuada de los Markdown de BRIDGE-001 en
`pilot/codex-bridge-20261112` para consulta desde GPT. No incluye código ni main.

Decisión definitiva posterior: cuenta estándar StockFlashAgent, Docker/WSL
descartados. Cuenta y ACL ya creadas en el intento de escritorio; falta reanudar
el recibo final corregido y probar aislamiento real. Agentes sobre código aún
bloqueados; no tarea permanente. Ver BRIDGE-001-WINDOWS-SETUP.md. Las referencias
a instalación de contenedor inferiores conservan trazabilidad y no están activas.

Histórico anterior al primer intento de escritorio: scripts administrativos revisados/corregidos y preflight
sin cambios aprobado. Aprovisionamiento preparado para ejecutar Start-Provision.ps1
desde PowerShell normal del escritorio, con UAC visible, espera y recibo verificado.
Después ejecuta pruebas reales con StockFlashAgent. La cuenta aún no existe;
pendiente esa ejecución, sin ISOLATION_PASS ni aceptación integral demostrada.
Comando exacto y cambios de seguridad: BRIDGE-001-WINDOWS-SETUP.md.

- Propietario aprobó BRIDGE-001 en worktree `stockflash-codex-pilot`, rama
  `pilot/codex-bridge-20261112`, base `d103120f6e45dcb10a999c99f3d6f2faff5f466c`.
- Estado: BLOCKED_UAC, núcleo local implementado y probado; aceptación
  integral NO realizada, agentes sobre código y publicación deshabilitados.
- Excepción posterior solicitada por el propietario: entrega de Markdown mediante
  commit/push selectivo por Codex en la rama piloto, sin código ni integración a
  main. Sin cambios al original, permisos/credenciales SO, migraciones ni deploy.
  No reactivar STAGING ni USERS-001 por referencias históricas abajo.
- Evidencia: `BRIDGE-001-EVIDENCE.md`. Decisión de aislamiento resuelta en
  `BRIDGE-001-ISOLATION-DECISION.md`; interacción UAC pendiente según
  `BRIDGE-001-WINDOWS-SETUP.md`. No declarar GATES_PASS global ni VERIFIED.
- Codex asume revisión externa y autoría editorial conforme a adenda en AGENTS.
  Harness y separación de QA/publicación se conservan.
- Histórico sustituido: se evaluó Docker Desktop / WSL 2 en
  `BRIDGE-001-CONTAINER-SETUP.md`, ahora descartado. No ejecutar DISM ni instalador.


## Hitos cerrados y estado vigente
- **R0 DOC-001:** base documental y contrato v2.2 preparado, sujeto a ADR-01..06 abiertos y controles pendientes antes de dominio.
- **R1 BOOT-001: VERIFIED** el 2026-10-14; Laravel 13.35.0 + Vue 3 + Inertia 3.5.1 + TypeScript/Vite. Gates `BOOT-001-VERIFY-20261013-234609.md` PASS; QA `BOOT-001-REVIEW-20261013-235005.md` APROBADO; scaffold remoto `bcbf16f`. No deploy ni migraciones.
- **HARNESS-001: VERIFIED del entregable** (2026-10-14), comandos `/sf-run`, `/sf-verify`, `/sf-review`, `/sf-publish` y segregación implementer/reviewer/publisher. No se atribuye ejecución E2E completa si la evidencia no la demuestra. La invocación directa del coordinador encontró una restricción Task durante BRAND-001; comprobar delegación nativa real en CI-001, sin relajar verificaciones.
- **BRAND-001: VERIFIED del prototipo SVG provisional** (2026-10-14). Ocho assets publicados, última corrección `cdc993f`; GATES_PASS `VERIFY-20261014-173659`, QA APROBADO `REVIEW-20261014-174500`, conformidad del propietario al avanzar. Los SVG NO son oficiales ni se usan como fuente primaria: mantener fallback textual; PNG oficiales prevalecen.
- **AUTH-001: VERIFIED** (decisión externa del propietario, 2026-11-09). Código publicado en `55b7f16`; gates `GATES_PASS`, QA independiente `APROBADO` y CI remota verde. Cierre documental en `docs/work/AUTH-001-CLOSURE-20261109.md`. Publicar no es VERIFIED; el registro documenta la decisión externa.
- **BRANCH-001: VERIFIED** (decisión externa del propietario, 2026-11-10). Código publicado en `4f81457`; CI PHP/Node/smoke/contratos verde, gates `GATES_PASS` y QA `APROBADO`. Cierre documental en `docs/work/BRANCH-001-CLOSURE-20261110.md`.
- **DOCS-001: VERIFIED** (decisión externa del propietario, 2026-11-10). Implementado en `90254e5`; gates `GATES_PASS`, QA `APROBADO` y CI PHP/Node/smoke/contratos SUCCESS. Cierre documental en `docs/work/DOCS-001-CLOSURE-20261110.md`.
- **ROADMAP-001: VERIFIED** (decisión externa del propietario, 2026-11-10). Implementado en `a9c19d4`; gates `GATES_PASS`, QA `APROBADO`, CI PHP/Node/smoke/contratos SUCCESS y PR #4 integrado. Cierre documental en `docs/work/ROADMAP-001-CLOSURE-20261110.md`.

## Preparación actual
- **CI-001: VERIFIED** el 2026-10-14 tras ejecución remota sobre `218de3ad2a51be6f7b46102b8a9b73569d53cf0b`: GitHub Actions CI `38655297831` **SUCCESS** (PHP 8.3 / Node 24 / Smoke GET `/` es-CL); contratos `38655297947` **SUCCESS**. Falencias anteriores de política de acciones y pruebas efímeras corregidas y comprobadas. No deploy ni staging.
- **STAGING-001: APPROVED y ACTIVA para ejecución R2** (2026-10-15), por autorización expresa del propietario para FTPS exclusivamente hacia `/home/grupoint/stockflash-dev-app`. Document Root a `.../stockflash-dev-app/public` informado como cambiado, pero requiere comprobar mediante marcador público y smoke. OpenCode implementa/verifica según `docs/work/features/STAGING-001.md`; aún NO hay deploy demostrado ni VERIFIED.
- Próximo hito formal: completar staging seguro R2 cuando sus prerequisitos estén satisfechos; luego preparar R3 con los ADR/Feature Packets pertinentes. La solicitud previa del propietario (guía reutilizable del harness y revisión Claude v2) ya fue atendida; no volver a preguntar de qué se trataba.
- R3 Core (usuarios/RBAC, sucursales, licencias técnicas, catálogo y BD) empieza después de puertas R2 correspondientes; ADR de dominio continúan abiertos.
- **Feature Packet AUTH-001: VERIFIED por decisión externa del propietario (2026-11-09)** (R3_AUTH, aprobada 2026-10-19; autenticación, sesiones, RBAC server-side, login/logout, cambio de contraseña, admin inicial DEV, dashboard protegido). Desarrollo local-first con BD DEV remota. Controles pendientes corregidos: duración absoluta 12 h por sesión, flujo `must_change_password` sin redirección circular, `EnsureBranchAccess` sin bypass, modelo `User` y documentación alineados. Corrección posterior aplicada: máximo de contraseña 64 (SEC-01) y sucursal validada también para usuario global, sin bypass. Publicado en `55b7f16e761adef306957904f78b20a9effbe6e7`; CI verde en ese commit (runs `42430883629` y `42430883632`; 4 check-runs SUCCESS). Gates `AUTH-001-VERIFY-20261107-213531.md` = GATES_PASS; QA independiente `AUTH-001-REVIEW-20261107-2.md` = APROBADO (más addendum); suite completa 47 passed / 112 assertions. PR #1 cerrado como obsoleto, no fusionado (su head `dc4e018` no debe fusionarse). Cierre documental registrado en `docs/work/AUTH-001-CLOSURE-20261109.md`. STAGING-001 queda en espera de confirmación visual del propietario para su cierre formal.
- **BRANCH-001: VERIFIED por decisión externa del propietario (2026-11-10)** (`docs/work/features/BRANCH-001.md`). CRUD de sucursales implementado y publicado en `eefd2ff`; corrección de CI por case-sensitivity de Inertia publicada en `4f81457` (`config/inertia.php` con `js/Pages` y `ensure_pages_exist`, regresión `InertiaPagesPathTest`). CI remota verde en `4f81457` (PHP, Node, smoke, contratos); gates `BRANCH-001-VERIFY-20261109-194843.md` = GATES_PASS (BN1–BN10); QA independiente `BRANCH-001-REVIEW-20261109-195125.md` = APROBADO. Cierre documental en `docs/work/BRANCH-001-CLOSURE-20261110.md`.
- **DOCS-001: VERIFIED por decisión externa del propietario (2026-11-10)** (`docs/work/features/DOCS-001.md`). Implementado y publicado en `90254e59bf61bc0252a579c076886fc500eb0119`; gates `DOCS-001-VERIFY-20261110-184136.md` = GATES_PASS, QA `DOCS-001-REVIEW-20261110.md` = APROBADO y CI remota PHP/Node/smoke (run `42908696754`) y contratos (run `42908696677`) SUCCESS, verificada externamente. Cierre documental en `docs/work/DOCS-001-CLOSURE-20261110.md`. El manual `/docs/`, el enlace «Ayuda», la ruta protegida y la automatización `GUIA_*` quedaron integrados; PR #3 aporta la actualización editorial de ChatGPT sobre Ayuda y el contexto. No implementa USERS-001.
- **ROADMAP-001: VERIFIED por decisión externa del propietario (2026-11-10)** (`docs/work/features/ROADMAP-001.md`). Implementado y publicado en `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41`; gates `ROADMAP-001-VERIFY-20261110-194620.md` = GATES_PASS (RM1–RM15), QA `ROADMAP-001-DOCS-REVIEW-20261110-2.md` = APROBADO y CI remota PHP/Node/smoke (run `42915292666`) y contratos (run `42915292580`) SUCCESS, verificada externamente. Cierre documental en `docs/work/ROADMAP-001-CLOSURE-20261110.md`; PR #4 y PR #5 integrados selectivamente (manual, roadmap y contexto). CI remota del cierre `3735c147`: PHP/Node/smoke (run `42918708678`) y contratos (run `42918708636`) SUCCESS, verificada externamente. **GUIA_ACTUALIZADA** y **ROADMAP_ACTUALIZADO**; sin pendientes editoriales. No implementa USERS-001.
- **USERS-001: DRAFT con QA documental APROBADO, pendiente de aprobación del propietario** (`docs/work/features/USERS-001.md`, `status: DRAFT`). Propuesta de administración de usuarios, roles de sistema y asignación a sucursales (CORE-03), respetando DB-001, AUTH-001 y BRANCH-001. Incorpora los ajustes de la revisión externa del 2026-11-10: protección del último administrador también frente a cambios de rol/tipo de acceso, invalidación de sesiones SEC-05 por contraseña/roles/accesos, PIN según `system_configs.pin_length`, `SoftDeletes` en `User` sin migración y aclaración de que `max_users` limita usuarios activos. QA documental: ronda 1 `CAMBIOS NECESARIOS` (corregido) y ronda 2 **APROBADO** (`docs/work/USERS-001-DRAFT-REVIEW-20261110-2.md`, 0 bloqueantes). Observaciones no bloqueantes a resolver antes de implementar: cobertura de `VerifySession` en todas las rutas autenticadas, auditoría de lectura PII (PRV-06/CORE-10) y precisiones editoriales. **No implementada, no activa, no VERIFIED.** No se inicia hasta aprobación expresa; sin despliegues ni migraciones.

## Reglas operativas
- `main` y `origin/main` con sincronización condicional mediante `scripts/safe_git_pull.py`: `fetch` + comparar SHA; `pull --ff-only` solo si el remoto está adelantado y el árbol local está limpio. Ante `SYNC_BLOCKED` no descartar trabajo local. Al cargar comandos actualizados reiniciar OpenCode si la sesión conserva una versión anterior.
- Agentes: `default_agent: build`, autorización Task exacta `stockflash-orchestrator`, `subagent_depth: 2` para permitir Build → coordinador → implementer/reviewer/publisher. Implementer y reviewer conservan prohibición de invocar coordinador y publisher. Comprobar E2E en feature futura; no declarar probado solo por configurar.
- Feature Packet `APPROVED` + activa → implementación → `GATES_PASS` → QA independiente `APROBADO` → publisher selectivo sin force → verificación SHA. `VERIFIED` únicamente por decisión externa, no por el agente. Autorización del propietario 2026-10-16: `opencode.json` sin `ask` (rigen solo `deny`); ejecutar directo. (AGENTS.md aún menciona `ask`: pendiente alinear en revisión del propietario, no lo toca el implementador.)
- Prohibido leer/publicar secretos, sobrescribir contratos protegidos, ejecutar migraciones o desplegar sin autorización específica. **Desarrollo local-first (2026-10-16): todo el desarrollo es local con BD remota DEV; despliegue a staging SOLO cuando el propietario lo indique (`/sf-deploy-staging` no es automático).** Migraciones contra BD remota solo con instrucción expresa por caso (sin red de git en BD). Mantener `tmp-boot-001/` ignorado, sin borrado automático.
- Contractos vigentes: `docs/product/especificacion-v2.2-*-CONSOLIDADO-EN-REVISION.md` (Parte 1 > Parte 2 > Anexo); `docs/branding/brand-contract.md` y `docs/branding/module-identity-map.md` preceden al inventario SVG.

## Infraestructura y bloqueos reales
- CI contractual `.github/workflows/contract-check.yml` y CI PHP/Node `.github/workflows/ci.yml` implementadas y verdes en GitHub Actions sobre `218de3a` (runs `38655297947` y `38655297831`). Diagnóstico SSH manual `.github/workflows/staging-ssh-check.yml` no constituye pipeline de despliegue.
- Staging HTTPS y DB DEV reportados, **no utilizados** para BOOT ni BRAND. Versión exacta de MariaDB no verificada.
- **Restricción SSH aún vigente:** no usar la credencial SSH general `grupoint`. El propietario autorizó una vía separada: cuenta FTPS creada para la aplicación privada, sin publicar secretos. El despliegue por esa vía queda condicionado a comprobar la raíz pública, preparar reversión y pasar gates/revisión STAGING-001.
- Roadmap canónico: `docs/harness/ROADMAP-STOCKFLASH.md`. La CI de R2 está verificada; resta staging seguro para cerrar R2. Propuesta de autonomía adicional documentada en `docs/harness/AUTONOMIA-OPENCODE.md`. No iniciar R3 antes de la solicitud del propietario.
## Mejoras de continuidad preparatorias — 2026-10-15
- Protocolo Claude v2 **revisado selectivamente**, sin sustituir la constitución, los contratos ni los comandos actuales. Criterios y decisiones: `docs/harness/ADOPCION-PROTOCOLO-V2.md`.
- Traspaso al nuevo chat: `docs/work/handoffs/HANDOFF-2026-10-15.md`; comenzar leyendo este CURRENT y luego el handoff, roadmap, ADR y Feature Packet activos. Comprobar HEAD remoto.
- Registro de preguntas/convenciones propuestas: `docs/work/DECISIONS-LOG.md`; validaciones diferidas con puertas precisas: `docs/quality/DEFERRED-VALIDATIONS.md`.
- Contrato propuesto `RUN-REPORT`: `docs/harness/RUN-REPORT-CONTRACT.md` (DOCUMENTADO, no implementado ni probado en OpenCode). No atribuir automatización que aún no existe.
- **Antes de empezar R3:** la solicitud previa del propietario sobre el manual del harness y revisión Claude v2 **ya está atendida**. Resta staging R2 y decisiones de dominio ADR pertinentes; no declarar `SPEC_READY` retroactivamente mientras falten decisiones bloqueantes.
- Para evitar reabrir un harness operativo, no modificar permisos, agentes ni añadir cola/AutoPilot antes de realizar un smoke de delegación y una decisión específica de alcance.
