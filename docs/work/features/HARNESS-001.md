# HARNESS-001 — Orquestación automática y permisos acotados

status: APPROVED
owner_approval: Autorización del propietario y ChatGPT en la conversación StockFlash del 2026-10-14 para automatizar implementación → gates → QA independiente → publicación y reducir aprobaciones rutinarias.
etapa: HERRAMIENTAS_OPERATIVAS
responsable_implementacion: OpenCode
revision: Reviewer independiente antes de publicar.
roadmap: No constituye nueva etapa; R2 (CI/staging seguro) continúa siendo el siguiente hito formal.

## Objetivo
Consolidar las mejoras del harness ya preparadas localmente para que una Feature Packet APPROVED y activa se ejecute con una sola instrucción `/sf-run FEATURE-ID`. No alterar BOOT-001 (VERIFIED), contratos v2.2 ni BRAND-001 (DRAFT).

## Alcance autorizado
1. Instalar `.opencode/commands/sf-run.md` y `.opencode/agents/stockflash-orchestrator.md`, respetando precedencia de permisos.
2. Validar una sola sincronización segura al inicio, orquestación implementer → gates deterministas → reviewer independiente → publisher, reintentos limitados a tres, sin permitir autoaprobación.
3. Checkpoints ignorados en `docs/work/.run/` con fingerprint y reanudación solo si el estado coincide; excluir secretos.
4. Mantener segregación real: orchestrator no hace implementación, QA ni publicación; implementer/reviewer no llaman publisher; solamente orchestrator puede delegar al publisher vía Task. `permission.task` no sustituye los controles internos del publisher.
5. Minimizar aprobaciones con allow para comandos conocidos seguros; operaciones sensibles mantienen ask/deny. Mantener el bloqueo global de commit/push, despliegue, migraciones y operaciones destructivas.
6. Registrar evidencia determinista de validaciones del harness (incluyendo definición de gates para HARNESS-001 en `GATES_BY_PACKET`, si fuese necesaria) y artefacto de revisión independiente vinculados al estado exacto. No declarar ejecutadas pruebas E2E que no se hayan realizado.
7. Publicar mediante el flujo ordinario A de `/sf-publish HARNESS-001`, con staging explícito, verificación SHA local/remoto y BRAND-001 excluido.

## Criterios de aceptación
- `/sf-run` disponible y enlazado al agente correcto al reiniciar OpenCode; configuración efectiva compatible con la versión instalada.
- Gates HARNESS-001 reproducibles y `GATES_PASS`; QA independiente `APROBADO`, cero bloqueantes, artefactos persistentes.
- Verificación de reglas de mínimo privilegio, rutas protegidas, `permission.task`, rechazo de BRAND-001 DRAFT y manejo de checkpoint/fingerprint.
- No publicar ni desplegar código de negocio, secretos ni cambios fuera del inventario aprobado.
- La primera publicación real de HARNESS-001 puede servir para validar el tramo publisher; si no se ejecutó E2E completo del coordinador, documentar la limitación en vez de declararlo validado.
- No declarar HARNESS-001 VERIFIED ni liberar otra feature automáticamente.

## Exclusiones
Sin cambios a `docs/product/*`, `AGENTS.md`, `docs/governance/CONSTITUTION.md`, `docs/branding/brand-contract.md`, `docs/branding/module-identity-map.md`, contratos comerciales, schema, backend funcional, migraciones, staging ni producción. No borrar `tmp-boot-001/`. BRAND-001 permanece DRAFT y sus SVG no se generan durante HARNESS-001.

## Referencias
- `opencode.json` (permiso administrativo centralizado, actualizado por ChatGPT en GitHub).
- `.opencode/commands/sf-publish.md` y `.opencode/agents/stockflash-publisher.md`.
- `docs/harness/WORKFLOW-SDD-HIBRIDO.md`; `docs/work/CURRENT.md`.
- Evidencia local reportada: checkpoint write/read/delete, rechazo DRAFT, revisión independiente del harness APROBADO; revalidar el conjunto definitivo después de integrar el cambio administrativo.
