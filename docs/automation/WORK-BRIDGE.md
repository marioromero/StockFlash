# StockFlash — puente ChatGPT Work ↔ GitHub ↔ OpenCode (v1, piloto)

**Estado: PILOTO / NO AUTÓNOMO hasta verificar eventos y escritura desde Work.** Este documento no crea una tarea de Work ni modifica el harness. El contrato `docs/harness/RUN-REPORT-CONTRACT.md` permanece DOCUMENTADO/NO OPERATIVO hasta que se complete y pruebe su implementación.

## Modos, supervisión y sesiones

**Leer también `docs/automation/MODOS-Y-CONTINUIDAD.md`.** Define la ubicación de resultados de Work en Programadas, la pausa manual segura, la continuidad entre chats y los controles pendientes antes de activar autonomía. Este piloto aún no incorpora un selector persistente AUTO/MANUAL y no debe ejecutarse con `--execute` en pruebas.

## Qué hace

Usar **dos pull requests de transporte, sin fusionarlos**, para intercambiar comentarios: OpenCode prepara un informe versionado con CI_UNVERIFIED/PENDING antes del publisher, lo incluye en los gates y QA y completa `/sf-feature → /sf-verify → /sf-review → /sf-publish` en main; cuando GitHub CI termina verde, `signal` publica un aviso en **PR #8** referenciando ese informe y el SHA de main; Work se despierta solamente por comentarios del PR #8, revisa la evidencia y publica la respuesta en **PR #7**; `watch` lee el PR #7 y coteja el reporte original del PR #8. Así las respuestas de Work no activan eventos nuevos. Opcionalmente puede ejecutar la spec autorizada por el `/sf-run` vigente. Python **no llama a OpenAI ni a DeepSeek**.

**Verificado manualmente:** Work pudo leer el repositorio y publicar un comentario en PR #7 (comentario #6808805849). La comprobación de Work indica que existe un activador para comentarios de un PR concreto, pero sin filtros de contenido. **Pendiente:** verificar una tarea automática real observando PR #8 y escribiendo en PR #7; la creación de tarea aún no se ha hecho.

## Fuentes de verdad y reglas inmutables

- `AGENTS.md`, `docs/governance/CONSTITUTION.md`, `docs/work/CURRENT.md`, `docs/work/features/*.md`, `docs/harness/CONTEXTO-CHATGPT.md`, `docs/harness/WORKFLOW-SDD-HIBRIDO.md`, `docs/harness/RUN-REPORT-CONTRACT.md` y GitHub Actions del SHA exacto.
- No modificar agentes, `opencode.json`, comandos `/sf-*`, contratos v2.2 ni workflows CI para el piloto. El RUN-REPORT no puede afirmar CI SUCCESS antes de publicarse: registrar CI_UNVERIFIED, y `signal` verifica CI remota tras el push. No publicar `VERIFIED` por cuenta del agente. Toda decisión de negocio y aprobación de spec DRAFT pertenece al propietario.
- OpenCode no edita `docs/harness/CONTEXTO-CHATGPT.md`, `public/docs/index.html` ni `resources/roadmap/index.html`: son de autoría exclusiva de GPT. Work tampoco debe adjudicar autoría y cambios que no haya realizado realmente.
- **PR #8 `[SF-WORK-SIGNAL]`:** recibe solo avisos OC. Es el ÚNICO PR que dispara Work. **PR #7 `[SF-WORK-CONTROL]`:** recibe solo instrucciones Work. Ambos deben quedar abiertos, base main, en ramas separadas, **NO MERGEAR**. Los comentarios de respuesta van a Conversation, no a Files changed.

## Modelo y potencia recomendados (Work Plus)

Durante configuración y detección: **GPT-6.1 Sol, Medio**. Para revisión técnica real: **GPT-6.1 Sol, Alto**, si está disponible en la cuenta; si no, mantener la opción equivalente que muestre Work y anotarla. Reservar **GPT-6 Astra, Alto** para problemas arquitectónicos excepcionales. No seleccionar Muy alto o Máximo como configuración permanente sin evidencias de mejor rendimiento.

**La tarea Work no tiene conmutación dinámica del modelo demostrada.** Una instrucción de Markdown no puede cambiar el selector de modelo. Si Work permite configurar tareas separadas con distintos modelos y condiciones de activación, se podrá evaluar después una tarea especializada, comprobando su consumo, filtros y prevención de bucles. Mientras no esté validado, la revisión ordinaria usa un modelo fijo y los casos complejos devuelven OWNER_REQUIRED para escalamiento. No prometer auto-ruteo que aún no existe.

## Compatibilidad del comentario real de Work

Durante la prueba manual Work escribió un objeto JSON puro en PR #7 (comentario #6808805849), sin bloque fenced Markdown. El supervisor acepta ahora **un bloque fenced estricto o un JSON que ocupe el comentario completo** y pase la validación del contrato. No acepta texto libre ni comandos shell. El modo de ejecución sigue bloqueado sin habilitación explícita.

## Contrato de comentarios

**OC → Work (PR #8):** comentario generado por `signal`, con bloque fenced (lenguaje `sf-oc-report-v1`), JSON del ejemplo:

```sf-oc-report-v1
{
  "protocol": "SF-OC-REPORT-1",
  "report_id": "USERS-001-run-001",
  "feature": "USERS-001",
  "sha": "<SHA real de main de 40 hexadecimales>",
  "kind": "RUN",
  "report_path": "docs/work/USERS-001-RUN-REPORT-001.md"
}
```

En modo `--smoke` el aviso lleva `kind: SMOKE` y `report_path: ""`, no comprueba CI ni habilita ninguna feature. **USERS-001 todavía es DRAFT** y no está autorizado para programación.

**Work → OC (PR #7):** único comentario con bloque `sf-work-instruction-v1` y JSON estricto:

```sf-work-instruction-v1
{
  "protocol": "SF-WORK-1",
  "instruction_id": "SMOKE-RESP-001",
  "report_id": "SMOKE-001",
  "source_feature": "USERS-001",
  "feature": "USERS-001",
  "base_sha": "<MISMO SHA real del reporte>",
  "action": "STOP",
  "notes": "Prueba de comunicación: no ejecutar OpenCode."
}
```

`source_feature` es la feature del reporte; `feature`, la siguiente spec que se pretende ejecutar. Pueden ser diferentes, **pero RUN requiere feature APPROVED y activa en CURRENT**. Acciones permitidas: `STOP`, `OWNER_REQUIRED` o `RUN`. Nunca incluir comandos Bash arbitrarios, secretos, datos de clientes, PII ni tokens. `instruction_id` único por comentario, `notes` máximo 8.000 caracteres. La verificación exige un reporte anterior en **PR #8** con el mismo report_id, SHA y source_feature. El comentario de respuesta se ubica en **PR #7**.

## Comportamiento del supervisor

- `scripts/sf_work_bridge.py signal --pr 8` comprueba que el PR sea el canal de avisos `[SF-WORK-SIGNAL]`. En RUN exige que el informe Markdown exista remotamente en main y que los cuatro checks de CI (PHP 8.3, Node 24, Smoke y contratos) estén terminados en success para ese SHA. En SMOKE solo envía aviso inofensivo.
- `scripts/sf_work_bridge.py watch --pr 7 --signal-pr 8` lee los comentarios de respuesta en #7 y comprueba el informe anterior en #8 con GitHub API cada 30 segundos; no consume tokens GPT por consultar. Filtra por autor GitHub exacto indicado mediante `--trusted-login`. Nunca acepta instrucciones para otro PR.
- `STOP` y `OWNER_REQUIRED` solo se registran. `RUN` sin `--execute` queda en modo DRY_RUN. `RUN` con `--execute` exige adicionalmente `SF_WORK_ALLOW_EXECUTE=YES`, SHA local/remoto sin diferencias, main limpio, spec APPROVED y activa. Ejecuta solo `opencode run --file <instrucción local> --command sf-run FEATURE`. Ante error, se bloquea y no se reintenta automáticamente.
- El ledger local `docs/work/.run/sf-work-bridge-state.json` está ignorado por Git y evita procesar dos veces el mismo comentario. No ejecutar múltiples supervisores paralelos.
- **En v1, la señal final de cada ejecución depende de que OpenCode invoque `signal` tras terminar publicación y CI.** Aún no hay hook E2E integrado; no declarar autonomía desatendida. Antes de dejar un ciclo ejecutándose durante horas hay que añadir presupuesto, límite de tokens/ciclos, calendario y exclusión mutua fuerte, con pruebas.
- Si Work y OC usan la misma identidad de GitHub, el filtro por login **no demuestra separación de autoría**. Para autonomía completa se necesitan identidades distintas o una validación externa adicional.

## Prompt de Work (copiar íntegro en Work)

> Crea una tarea **activada por evento** de GitHub para el repositorio privado marioromero/StockFlash: **comentario nuevo SOLO en PR #8, título [SF-WORK-SIGNAL]**. La interfaz no admite filtro por contenido, así que al comenzar cada ejecución revisa los comentarios recibidos y procesa únicamente avisos nuevos sf-oc-report-v1. Ignora cualquier otro comentario. **Nunca respondas en PR #8**, porque eso puede disparar de nuevo Work. Leer docs/automation/WORK-BRIDGE.md, AGENTS.md, docs/harness/CONTEXTO-CHATGPT.md, docs/work/CURRENT.md y la spec asociada. Comprobar el SHA y los checks de GitHub Actions para informes RUN; no dar por cierta la CI solo por el texto. Para kind=SMOKE publicar **un comentario de conversación en PR #7** con un bloque JSON sf-work-instruction-v1, action STOP, instruction_id nuevo y report_id, source_feature, feature y base_sha fieles al evento. No ejecutar código ni cambiar estados. Para RUN escoger STOP, OWNER_REQUIRED o RUN solo cuando la spec esté APPROVED y activa; publicar siempre la instrucción en **PR #7**, nunca en #8; el JSON debe respetar exactamente este contrato. No declarar VERIFIED sin decisión externa; respetar autoría exclusiva de GPT y todas las prohibiciones de deploy, migraciones y edición contractual. Si no tienes una acción autorizada para publicar comentarios, informar expresamente que la escritura no está disponible y detener la tarea; no afirmar que has entregado la instrucción. No crear tareas horarias ni utilizar APIs de pago adicionales.

## Costos y límites

ChatGPT Plus incluye acceso elegible a tareas event-triggered de Work, sujetas a su cuota Work/Codex. **Cada revisión de Work consume cuota; esperar el evento no.** Este Python no usa tokens de IA. GitHub API tiene rate limits; intervalo mínimo programado 15 s. Los costos de DeepSeek que usa OpenCode son independientes de este puente. No habilitar la ejecución de RUN hasta verificar permisos de Work y controles de presupuesto.

Fuentes oficiales:
- https://help.openai.com/en/articles/10291617-scheduled-tasks-in-chatgpt
- https://help.openai.com/en/articles/11145903-connecting-github-to-chatgpt
- https://opencode.ai/docs/cli/commands
