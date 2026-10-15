# StockFlash — Estado y evolución de la autonomía OpenCode (2026-10-14)

## Diagnóstico
La ejecución **por Feature Packet** está automatizada en gran parte:
`sf-run` define implementación por `stockflash-implementer`, gates en `sf_verify.py`, revisor independiente, reintentos limitados (3), publisher con commit y push selectivo, fingerprints y checkpoints. `stockflash-auto.cmd` se informó como lanzador **local no versionado**, que inicia `opencode --auto`; no se puede verificar su ejecución desde GitHub.

En `opencode.json`, `default_agent: build`, `subagent_depth: 2` y `permission.task` permiten la delegación `Build → stockflash-orchestrator → implementer/reviewer/publisher` en principio. **No hay evidencia completa de que `/sf-run` haya realizado esa cadena nativa sin intervención**: en CI-001 el contexto reportó `task deny` para llamada directa al coordinador y se utilizó coordinación manual. Probar con una Feature Packet adecuada sin simulación, log de sesiones y rol efectivo. No asumir éxito.

La CI GitHub Actions se dispara automáticamente por push/PR y ya pasa en SHA `218de3a`. Es **verificación automática**, no lanzamiento de un nuevo trabajo de programación.

## Capacidades oficiales investigadas
- OpenCode admite `subagent_depth: 2`, agentes en modo `all`/`subagent`, reglas `permission.task` y `default_agent`. Referencias: https://docs.opencode.ai/docs/config/ y https://opencode.ai/docs/agents/.
- CLI no interactiva `opencode run` con `--command`, `--attach`; servidor `opencode serve` para sesiones y comandos por API. Fuentes: https://dev.opencode.ai/docs/cli/ y https://dev.opencode.ai/docs/server/.
- Plugins `.opencode/plugins/` admiten eventos `session.idle`, `session.error`, `command.executed`, `file.edited`; pueden notificar o alimentar un planificador. Fuente: https://docs.opencode.ai/docs/plugins/.
- Integración oficial OpenCode GitHub Action admite `issues`, `pull_request`, `schedule` y `workflow_dispatch` para ejecutar tareas en runners de GitHub. Requiere configuración de credenciales/servicio de modelo y permisos de acciones, además de compatibilidad con la política SHA-pin del repo. Fuente: https://opencode.ai/docs/github/.

## Diferencia decisiva: ejecución en sesión vs trabajo desatendido
- **Actual:** al invocar `/sf-run PACKET` en un OpenCode iniciado y con packet APPROVED/activo se solicita ejecutar automáticamente la cadena de agentes. Una instrucción por feature. No existe una cola que elija y ejecute sola la siguiente feature.
- **Opción A local:** Windows Task Scheduler o servicio local + `opencode run --command sf-run <ID>`, con script despachador `sf-autopilot`. Solo procesa packets aprobados y activos; bloqueo si worktree no limpio, mismo paquete ya ejecutado, CI no verde, colisión, credenciales ausentes o decisiones no aprobadas; mutex y max 1 feature a la vez. Exige equipo encendido, herramienta y modelo accesibles, credenciales Git locales. Sin servidor público.
- **Opción B nube:** GitHub Actions OpenCode sobre eventos/cron/Issues con modelo y credenciales configuradas; funciona sin PC encendido, pero consume cuotas y **no hereda** automáticamente el entorno local ni el proveedor/modelo gratuito de escritorio. Preferir PR y revisión CI previa a merge; no crear workflows que publiquen de forma ilimitada en main por simple evento de push.
- **Plugins/hooks:** sirven para observabilidad, notificaciones y recuperación, pero un hook `session.idle` por sí solo NO convierte OpenCode en un planificador duradero con cola persistente y prevención de loops.

## Recomendación concreta y orden
1. Cerrar CI-001 por evidencia remota real y preparar STAGING-001 documentalmente (sin deploy).
2. En la primera feature futura, validar **de verdad** `/sf-run` con agente principal, delegación y gates/QA/publisher; si falla, registrar regla exacta y corregir una sola vez. Evitar rediseños especulativos del harness.
3. Si se busca disparo sin escribir comandos, preferir **dispatcher local supervisado** para StockFlash en etapa de desarrollo, con una cola de Feature Packets previamente APPROVED y sin implementar automáticamente planes DRAFT.
4. Decidir previamente si el propietario espera ejecuciones **solo cuando su Windows está encendido** (sin credenciales externas) o **24/7 en GitHub** (requiere proveedor, costes y secretos). No instalar ninguna de las dos opciones hasta conocer esta preferencia.
5. Notificaciones por `session.idle` y `session.error` como mejora de experiencia de bajo costo, pero no habilitar reintentos infinitos ni que la CI genere automáticamente nuevas features.

## Gate de autonomía verificable
La madurez máxima se comprueba mediante una ejecución real: selección de packet aprobado→lock→sincronización→implementación→gates→QA→publicación→CI remota verde→registro de estado y alerta si falla, con reanudación idempotente, sin intervención manual en operaciones rutinarias. La IA sigue sin autoridad para crear por sí sola nuevos requisitos comerciales/legales ni declarar aprobaciones externas. No sobrevalorar progreso por la mera existencia de agentes.
