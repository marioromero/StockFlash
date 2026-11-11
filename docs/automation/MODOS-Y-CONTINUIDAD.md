# StockFlash — modos, supervisión y continuidad (anexo obligatorio del piloto)

**Estado: DISEÑO PENDIENTE DE ACTIVACIÓN.** El piloto de PR #6 no dispone todavía de un selector persistente AUTO/MANUAL ni del control de presupuesto y horario. No afirmar autonomía completa.

## Dónde se ve cada cosa

- ChatGPT > **Programadas**: tarea Work, ejecuciones anteriores, errores, pausa/reanudación y aprobaciones pendientes. Cada ejecución de Work es una revisión distinta; no prometer que aparecerá en el chat manual.
- GitHub PR **#7** [SF-WORK-CONTROL]: historial cronológico durable de reportes OC y respuestas de Work; mantener abierto y **NO MERGEAR**.
- GitHub main: Feature Packet, contexto ChatGPT, CURRENT, QA, gates, SHA exacto y CI; es la fuente de verdad.
- Windows: `docs/work/.run/sf-work-bridge-state.json` (ledger local, ignorado por Git) y consola del supervisor; no borrar al cambiar de modo. El ledger local no reemplaza el historial GitHub.

## Operación manual HOY, con el piloto existente

1. En ChatGPT > **Programadas**, **pausar** la tarea Work. La pausa detiene futuras ejecuciones, no necesariamente una ya en curso: revisar el historial y las acciones pendientes.
2. En el equipo Windows, detener el supervisor de escucha con **Ctrl+C** o cerrar su proceso únicamente cuando no haya un subproceso OpenCode activo; si lo hay, dejar que termine o supervisar su checkpoint antes de cerrar. No borrar temporales, ramas, PR ni ledger.
3. Continuar manualmente en ChatGPT leyendo `CONTEXTO-CHATGPT.md`, `CURRENT.md`, la spec activa y el último reporte/CI del SHA exacto. Después ejecutar OC manualmente como antes. No lanzar el mismo `instruction_id` dos veces.
4. Para reanudar, primero confirmar que no hay ejecución en curso, que main y la evidencia corresponden al mismo SHA y que no hay instrucciones RUN obsoletas. Luego reanudar Work y el supervisor. **No usar `--execute` en la fase piloto**.

## Funcionalidad exigida antes de autonomía real

Implementar y probar un modo `MANUAL` (predeterminado) y `AUTO` persistente en archivo local ignorado por Git. Crear comandos `status`, `pause` y `resume` separados del canal de comentarios. Todo nuevo RUN debe consultar el modo justo antes de lanzar OpenCode, nunca solo al iniciar el proceso. Pausa de Python debe inhibir despacho sin interrumpir trabajo en curso. Pausar Work en Programadas sigue siendo necesario para evitar revisiones que consuman cuota. Incluir un único proceso/lock, política de horarios, cuota de ciclos y tope económico DeepSeek antes de permitir AUTO. Reanudar no ejecutará instrucciones obsoletas ni repetidas.

## Continuidad automática y sesiones

No depender de memoria de conversación ni mantener un chat abierto durante días. Cada revisión Work reconstruye su contexto leyendo documentos versionados del SHA real. No hay transferencia automática de mensajes de un chat manual a otro; solo valen las decisiones publicadas de manera verificable.

Al cerrar una revisión, registrar como mínimo: `feature`, `report_id`, `instruction_id`, `SHA`, CI/gates/QA realmente observados, decisiones del propietario y siguiente acción permitida. **OpenCode no edita** `docs/harness/CONTEXTO-CHATGPT.md`, `public/docs/index.html` ni `resources/roadmap/index.html`. Si Work carece de capacidad de escritura GitHub, marcar `OWNER_REQUIRED` y usar el protocolo editorial manual; no atribuir un cambio que no se ha publicado.

Para reanudar en un chat GPT nuevo: \"Continúa StockFlash leyendo docs/harness/CONTEXTO-CHATGPT.md, docs/work/CURRENT.md, la spec activa y PR #7. Comprueba el SHA y el estado real de CI. No ejecutes automáticamente nada\".

## Pruebas que faltan

- Evento Work exacto compatible con comentario en PR #7 y acción de escritura efectiva a GitHub. Si falla cualquiera, no hay ciclo automático Work ↔ OC.
- Conmutación a MANUAL durante trabajo sin nueva ejecución; tras reanudar, deduplicación y comprobación de SHA.
- Continuidad desde chat nuevo y desde nueva ejecución Work sin inventar estados o aprobaciones.
- Paradas por costo, límite de ciclos y horario, y notificación visible al propietario.

Fuentes: https://help.openai.com/es-419/articles/10291617-scheduled-tasks-in-chatgpt y https://help.openai.com/en/articles/11145903-connecting-github-to-chatgpt .
