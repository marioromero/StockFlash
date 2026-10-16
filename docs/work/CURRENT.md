# Estado operativo — StockFlash (2026-10-14)

## Hitos cerrados y estado vigente
- **R0 DOC-001:** base documental y contrato v2.2 preparado, sujeto a ADR-01..06 abiertos y controles pendientes antes de dominio.
- **R1 BOOT-001: VERIFIED** el 2026-10-14; Laravel 13.35.0 + Vue 3 + Inertia 3.5.1 + TypeScript/Vite. Gates `BOOT-001-VERIFY-20261013-234609.md` PASS; QA `BOOT-001-REVIEW-20261013-235005.md` APROBADO; scaffold remoto `bcbf16f`. No deploy ni migraciones.
- **HARNESS-001: VERIFIED del entregable** (2026-10-14), comandos `/sf-run`, `/sf-verify`, `/sf-review`, `/sf-publish` y segregación implementer/reviewer/publisher. No se atribuye ejecución E2E completa si la evidencia no la demuestra. La invocación directa del coordinador encontró una restricción Task durante BRAND-001; comprobar delegación nativa real en CI-001, sin relajar verificaciones.
- **BRAND-001: VERIFIED del prototipo SVG provisional** (2026-10-14). Ocho assets publicados, última corrección `cdc993f`; GATES_PASS `VERIFY-20261014-173659`, QA APROBADO `REVIEW-20261014-174500`, conformidad del propietario al avanzar. Los SVG NO son oficiales ni se usan como fuente primaria: mantener fallback textual; PNG oficiales prevalecen.

## Preparación actual
- **CI-001: VERIFIED** el 2026-10-14 tras ejecución remota sobre `218de3ad2a51be6f7b46102b8a9b73569d53cf0b`: GitHub Actions CI `38655297831` **SUCCESS** (PHP 8.3 / Node 24 / Smoke GET `/` es-CL); contratos `38655297947` **SUCCESS**. Falencias anteriores de política de acciones y pruebas efímeras corregidas y comprobadas. No deploy ni staging.
- **STAGING-001: APPROVED y ACTIVA para ejecución R2** (2026-10-15), por autorización expresa del propietario para FTPS exclusivamente hacia `/home/grupoint/stockflash-dev-app`. Document Root a `.../stockflash-dev-app/public` informado como cambiado, pero requiere comprobar mediante marcador público y smoke. OpenCode implementa/verifica según `docs/work/features/STAGING-001.md`; aún NO hay deploy demostrado ni VERIFIED.
- Próximo hito formal: completar staging seguro R2 cuando sus prerequisitos estén satisfechos; luego preparar R3 con los ADR/Feature Packets pertinentes. La solicitud previa del propietario (guía reutilizable del harness y revisión Claude v2) ya fue atendida; no volver a preguntar de qué se trataba.
- R3 Core (usuarios/RBAC, sucursales, licencias técnicas, catálogo y BD) empieza después de puertas R2 correspondientes; ADR de dominio continúan abiertos.
- **Feature Packet activa: STAGING-001.** Próxima instrucción local: sincronizar `origin/main` y ejecutar `/sf-run STAGING-001`, con preflight FTPS y comprobación por marcador antes de subir Laravel. Ningún despliegue se ha verificado todavía.

## Reglas operativas
- `main` y `origin/main` con sincronización fast-forward mediante `scripts/safe_git_pull.py`; ante `SYNC_BLOCKED` no descartar trabajo local. Al cargar comandos actualizados reiniciar OpenCode si la sesión conserva una versión anterior.
- Agentes: `default_agent: build`, autorización Task exacta `stockflash-orchestrator`, `subagent_depth: 2` para permitir Build → coordinador → implementer/reviewer/publisher. Implementer y reviewer conservan prohibición de invocar coordinador y publisher. Comprobar E2E en feature futura; no declarar probado solo por configurar.
- Feature Packet `APPROVED` + activa → implementación → `GATES_PASS` → QA independiente `APROBADO` → publisher selectivo sin force → verificación SHA. `VERIFIED` únicamente por decisión externa, no por el agente. Autorización del propietario 2026-10-16: `opencode.json` sin `ask` (rigen solo `deny`); ejecutar directo. (AGENTS.md aún menciona `ask`: pendiente alinear en revisión del propietario, no lo toca el implementador.)
- Prohibido leer/publicar secretos, sobrescribir contratos protegidos, ejecutar migraciones o desplegar sin autorización específica. Mantener `tmp-boot-001/` ignorado, sin borrado automático.
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
