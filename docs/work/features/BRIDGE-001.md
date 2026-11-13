# BRIDGE-001 — Puente local Codex CLI / OpenCode

status: APPROVED
owner_approval: Aprobación expresa del propietario en sesión Codex, 2026-11-12.
execution_status: BLOCKED_UAC

environment_approval: Decisión definitiva posterior: cuenta local estándar StockFlashAgent; Docker/WSL descartados.
environment_gate: Aceptación de UAC para aprovisionamiento autorizado; ver BRIDGE-001-WINDOWS-SETUP.md.
environment: Solo worktree stockflash-codex-pilot, rama pilot/codex-bridge-20261112.
base_sha: d103120f6e45dcb10a999c99f3d6f2faff5f466c
publication: FORBIDDEN para código/automatización; excepción posterior solo Markdown en rama piloto.

## Excepción documental posterior — 2026-11-12

El propietario solicitó publicar los Markdown para revisarlos en GitHub. Se
autoriza esta entrega documental selectiva en `pilot/codex-bridge-20261112`,
por Codex mediante Git sin force; el publisher existente solo admite main.
No incluye scripts/pruebas, integración a main, activación ni aceptación técnica.
La prohibición inicial de commits/push subsiste para el resto del piloto.
GATES_FAIL y falta de QA independiente se mantienen visibles.

## Alcance aprobado

Reutilizar handshake Python y harness OpenCode para transporte persistente,
mensajes estructurados, sesiones recuperables, checkpoints, deduplicación,
presupuesto de tres ciclos y reconciliación tras interrupciones. Codex asume
revisión externa y autoría editorial; OpenCode conserva coordinador, gates,
implementer, reviewer y publisher. Sin nuevo coordinador ni gates duplicados.

No modificar el repositorio original, permisos/credenciales del SO, contratos
de producto, dependencias comerciales, BD, migraciones ni despliegues.
USERS-001 sigue DRAFT. Automatización normal y publicación requieren decisión
posterior; APPROVED no concede VERIFIED.

## Subtareas y aceptación

1. Comprobar identidad Git, conservar repositorio original y cambios ajenos.
2. Demostrar aislamiento efectivo antes de invocar agentes sobre código.
3. Implementar ledger externo y validación estricta; probar reinicio, duplicados,
   conflictos, bloqueo por proyecto, DRAFT, evidencia alterada y decisiones.
4. Integrar sesiones CLI y modo piloto en el coordinador existente únicamente
   después de superar 2; mantener QA independiente y máximo tres ciclos totales.
5. Una aceptación real: Codex → OpenCode → validador técnico del propio puente
   → gates existentes → reviewer → Codex. Corrección y segunda revisión solo
   si existe defecto reproducible. Probar interrupción, duplicado, DRAFT,
   bloqueo de publicación y decisión simulada. Sin commits ni push.
6. Entregar evidencia reproducible y diff; solicitar activación normal aparte.

El núcleo local puede desarrollarse y probarse sin modelos. Los tests unitarios
no acreditan sesiones CLI recuperadas, QA independiente ni el ciclo integral.
El bloqueo de aislamiento detiene 4 y 5, no autoriza rebajar la protección.

## Gates

Ejecutor único: `py -3 -B scripts/sf_verify.py BRIDGE-001`.
BR1: pruebas deterministas del núcleo. BR2: aislamiento requerido.
Mientras no exista launcher aislado demostrado, BR2 falla de forma deliberada.
No presentar BR1 PASS como GATES_PASS de BRIDGE-001.

## Estado de entrega parcial

Núcleo local implementado; CLI dispatch deshabilitado. Ver
`docs/work/BRIDGE-001-EVIDENCE.md` y `docs/work/BRIDGE-001-ISOLATION-DECISION.md`.
