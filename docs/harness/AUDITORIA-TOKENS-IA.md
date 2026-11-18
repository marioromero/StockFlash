# Auditoría de consumo IA y rendimiento del harness — protocolo portable v1

**Estado:** PREPARADO / NO ACTIVO EN INSTALL-001. Activar en el siguiente handoff o feature que ChatGPT indique después de que termine el ciclo actualmente en marcha. **No modificar la ejecución local en curso, ni editar el harness operativo caliente mientras OpenCode publica INSTALL-001.**
**Propietario:** revisión funcional de ChatGPT; **ejecutor:** OpenCode + orquestador + implementador + revisor + publisher; **destinatarios externos opcionales:** Claude y otras IA.

## 1. Objetivo y principio de medición

Determinar de dónde provienen **tokens, tiempo y llamadas a herramientas** en una feature completa, incluidos subagentes, validadores y ciclos correctivos, y qué operaciones pueden simplificarse **sin reducir seguridad, pruebas obligatorias, QA independiente ni CI final**. Producir evidencia durable, legible en GitHub y exportable a terceros.

Medir por separado: (a) tokens realmente reportados por el proveedor/cliente; (b) tiempo de ejecución de procesos locales; (c) volumen de texto entregado al modelo; (d) acciones repetidas; (e) número de iteraciones. **Un comando shell largo no equivale a muchos tokens**, y el tiempo de `Thought` no permite inferir cuántos tokens se consumieron. Nunca estimar tokens a partir de segundos como si fueran mediciones.

**Límite de observabilidad:** no se puede acceder a razonamiento privado, prompts internos no expuestos, tokens de subagentes ocultos ni al coste exacto si OpenCode/proveedor no entrega telemetría. Registrar `null` y `availability_reason`, nunca inventar. Resumir la finalidad de una acción visible, **no transcribir cadenas de pensamiento**.

## 2. Momento de activación y ubicaciones

- Al próximo handoff SFH1 dirigido a OC: ChatGPT indicará `audit=ON` y referenciará esta política. OpenCode confirmará si su versión dispone de eventos/telemetría nativa y aplicará la captura menos intrusiva posible. No ejecutar /install ni cambiar BD, `.env` real, cron ni deploy.
- Por cada feature/ciclo, crear **archivo principal append-only** `docs/work/audits/TOKEN-AUDIT-<FEATURE>-<YYYYMMDD>-<RUNID>.jsonl`; cada línea es un objeto JSON UTF-8 válido. Es la fuente completa de eventos observables.
- Crear además `docs/work/audits/TOKEN-AUDIT-<FEATURE>-<YYYYMMDD>-<RUNID>-ANALYSIS.md` con índice, tabla agregada y recomendaciones comparables. Solo **este resumen y el JSONL sanitizado** se pueden versionar en GitHub; registros originales con secretos no se suben.
- Escribir checkpoints durante la ejecución, no reconstruir todo desde memoria al final. Si el runtime no admite hook automático, acumular observaciones en un archivo local estructurado con escritura por lotes al acabar cada etapa/subagente; declarar de forma explícita la menor cobertura.
- **Evitar el efecto observador:** no usar una llamada adicional al modelo por cada evento de auditoría. Preferir logs de herramientas existentes, callbacks nativos o script local simple que anota tiempo, tamaño y estado. Guardar agregados y referencias; nunca enviar salidas grandes de vuelta al LLM solo para contabilizarlas.
- La publicación del log en GitHub sigue los controles habituales de QA, revisión de privacidad y Git. **No** publicar un archivo parcial automáticamente en cada comando, ni hacer un commit por evento. El auditor GPT leerá la versión final y, si existe, la última versión de control.

## 3. Cobertura obligatoria de procesos visibles

Registrar **cada acción observable** del agente principal y de los subagentes cuando la interfaz/herramientas permitan capturarla:

1. Inicio y fin de sesión, feature, etapa y subagente; nombre del rol (`orchestrator`, `implementer`, `reviewer`, `publisher`, `other`), modelo/proveedor si está disponible, delegación padre-hijo, contexto/compactación solo si hay señal explícita.
2. Llamadas a herramientas: lectura de archivos, `grep`, `glob`, búsquedas, escrituras, ediciones, Git, herramientas externas, tareas delegadas; incluir entrada resumida, rutas/ámbito, tamaño de respuesta realmente entregada al modelo y resultado.
3. Procesos shell: comando **sanitizado**, propósito, inicio/fin/duración, exit code, bytes y líneas de stdout/stderr, si se truncó y *qué referencia segura* contiene el resultado completo. No guardar el stdout completo salvo extracto inocuo que justifique una observación.
4. Pruebas/validaciones: gate ID, suites y selección de tests, cantidad de tests/aserciones si está publicada, build, typecheck, comandos repetidos, hash/fingerprint del árbol probado, éxito/falla, reporte de evidencia. Diferenciar pruebas focalizadas, suite total y CI.
5. Rondas de corrección: número de ciclo, defecto que disparó la repetición, archivos afectados, revisiones y pruebas repetidas, evidencia reutilizada o invalidada por cambio de SHA/hash.
6. Consultas a GitHub y CI, cambios de contexto, sincronizaciones y publicación, sin registrar URLs privadas con tokens.
7. Tiempo visible de `Thought` cuando la UI lo muestra: solo duración/etapa. **No** solicitar ni guardar contenido interno del razonamiento.

Si no es posible observar un evento individual (p. ej. herramientas internas de un subagente), registrar **un evento de resumen del subagente** con el total visible de toolcalls y `coverage=summary_only`, en lugar de inventar sus detalles.

## 4. Campos de cada evento JSONL

Todos los eventos deben tener:

- `schema_version` (1), `event_id` (único), `run_id`, `timestamp_start_utc`, `timestamp_end_utc` (si conocido), `duration_ms` (si medido), `feature`, `stage`, `cycle`, `agent_role`, `agent_instance_id`, `parent_span_id`, `event_type`, `operation`, `status`, `coverage` (`native`, `wrapper`, `manual`, `summary_only`), `git_head` o `tree_fingerprint`, `description` (una línea).
- `tool_name`, `command_redacted`, `target_paths`, `path_count`, `read_bytes`, `write_bytes`, `stdout_bytes`, `stderr_bytes`, `response_bytes_to_model`, `response_lines_to_model`, `output_truncated`, `exit_code`, `evidence_ref` (todo opcional cuando no aplica). Evitar nombres de archivos sensibles y valor de argumentos secretos.
- `tokens`: `{input,output,reasoning,cache_read,cache_write,total,source,availability_reason}`. `source=native_metered|provider_usage|unavailable`; valores numéricos **solo si efectivamente medidos**. Especificar si incluyen tokens de subagentes o del padre para evitar doble conteo; `null` es válido.
- `cost_usd` solo si lo informa el proveedor, con modelo/precio aplicado y fecha si se dispone. Nunca convertir tokens a dinero sin tarifas verificadas y señalizar una proyección como proyección.
- `is_repeat` y `repeat_of_event_id` para llamadas equivalentes; `repeat_reason` (`retry_after_change`, `qa_independent`, `duplicate_unnecessary`, `same_input_recheck`, `other`, `unknown`); `input_fingerprint` sanitizado si la operación es comparable.
- `risk_class` (`none`, `security`, `privacy`, `data_loss`, `contract`, `deployment`) y `must_keep` para controles que no se deben eliminar; `note` breve con evidencia de por qué fue necesario.
- `model_context_tokens_before`, `model_context_tokens_after`, `compaction_event`, `provider_session_id` **solo si la API los expone sin datos sensibles**; de lo contrario null. No derivar contexto a partir del número de archivos ni de tiempo.

Ejemplo mínimo ilustrativo (NO es una medición real):

```json
{"schema_version":1,"event_id":"e0042","run_id":"install001-r2","timestamp_start_utc":"2026-11-17T23:00:00Z","timestamp_end_utc":"2026-11-17T23:00:11Z","duration_ms":11000,"feature":"INSTALL-001","stage":"qa_r2","cycle":2,"agent_role":"reviewer","agent_instance_id":"reviewer-r2","parent_span_id":"subagent-review-r2","event_type":"shell","operation":"npm run typecheck","status":"PASS","coverage":"wrapper","git_head":null,"tree_fingerprint":"safe-git-tree-hash","description":"Validación TS posterior a correcciones","tool_name":"bash","command_redacted":"npm run typecheck","target_paths":[],"stdout_bytes":2384,"response_bytes_to_model":320,"response_lines_to_model":6,"output_truncated":true,"exit_code":0,"tokens":{"input":null,"output":null,"reasoning":null,"cache_read":null,"cache_write":null,"total":null,"source":"unavailable","availability_reason":"CLI no expone uso por herramienta"},"cost_usd":null,"is_repeat":true,"repeat_of_event_id":"e0019","repeat_reason":"qa_independent","risk_class":"none","must_keep":false}
```

## 5. Cabecera, límites y checkpoints

- Primera línea `event_type=run_start`: feature/packet, base SHA, fecha, cliente y versión OpenCode, modelos/agentes accesibles, modo de captura, alcance y restricciones; **no** volcar configuración sensible.
- Eventos `subagent_start`, `subagent_end`, `tool_call`, `shell`, `gate`, `qa_finding`, `correction`, `compaction_visible`, `handoff`, `run_end` con `parent_span_id` que permita reconstruir jerarquía. Si hay ejecución paralela, conservar IDs y ventanas de tiempo, no sumar duraciones como si fueran secuenciales.
- En `run_end`, indicar `events_captured`, `events_known_missing`, `subagents_count`, `cycles`, `gates`, `ci_state`, `telemetry_completeness` y `unknowns`; si el proceso se corta, registrar cierre `interrupted` en la siguiente sesión cuando sea observable.
- Para evitar miles de líneas inútiles en el chat, mantener el JSONL en disco, escribir lotes por etapa o desde hook y consultar solo **sumarios**, no volver a leer todo el archivo con cada paso del agente.

## 6. Resumen ANALYSIS.md obligatorio

1. **Contexto reproducible:** feature, SHA inicial/final, sesiones/agentes/modelos realmente observados, cantidad de eventos capturados y porcentaje de cobertura (si hay denominador), limitaciones; sin inventar cifras.
2. **Tabla por agente y etapa:** llamadas a herramientas, tiempo pared vs tiempo shell, cantidad de lecturas, bytes/texto entregado al modelo, tokens medidos de entrada/salida/cache/razonamiento si disponibles, coste si disponible, compactaciones, tests y ciclos.
3. **Top 15 operaciones costosas:** por tokens medidos (si se dispone); si no, ordenar por volumen de salida al modelo y tiempo **sin llamarlo consumo de tokens**. Separar costes de proceso local de costes de inferencia.
4. **Duplicaciones con evidencia:** mismos archivos releídos, mismas búsquedas/suites/typecheck/build repetidos, motivo justificado o evitable, `event_id` y SHA de árbol probado en cada caso. Distinguir QA independiente legítimo de duplicación innecesaria.
5. **Causas de re-trabajo:** defectos del primer QA, bloqueos de permisos, falta de contexto, lectura indiscriminada, compactación, rework inducido por formato/harness, no asumir causas sin logs.
6. **Plan de ahorro priorizado:** ahorro de tokens verificable o intervalo hipotético claramente marcado, efecto en tiempo, dificultad, cobertura que se preserva, riesgo, pruebas de regresión para asegurar equivalencia. Distinguir quick wins sin código de cambios al harness.
7. **Experimento A/B sugerido:** comparar una feature semejante (número de archivos, suite, ciclos, modelos) con baseline y optimización, mediana y dispersión, sin atribuir ahorro cuando cambió el alcance.
8. **Limitaciones y preguntas abiertas** para segunda opinión externa. El análisis debe poder leerse sin acceso al disco local ni a la conversación con ChatGPT.

## 7. Privacidad, seguridad y auditoría de la auditoría

- Nunca guardar `.env`, variables reales, credenciales, tokens de acceso, contraseñas, RUT, correos de clientes, dumps, prompts completos, respuestas completas no sanitizadas, pensamientos privados, stack traces con secretos ni capturas de terminal con PII.
- Antes del commit, filtrar/redactar automáticamente patrones y **revisar a nivel semántico** rutas y outputs (una regex no basta). Preferir ruta relativa + hash de contenido frente a contenido de archivos. Si no es seguro, registrar `redacted=true`, bytes/tipo/estado y dejar detalle local excluido de Git.
- **La auditoría no justifica una nueva llamada IA por cada evento**, ni sumar logs de 100 KB a prompts, ni relajar tests/QA independiente, ni suspender la protección de producción y DB remota. Salidas extensas solo permanecen en almacenamiento local de ejecución excluido de Git.
- El análisis de terceros se limita a logs sanitizados: no compartir archivos confidenciales del repositorio privado ni enlace privado asumido público. El propietario puede exportar el JSONL/MD revisados a otra IA.

## 8. Preguntas que GPT debe responder al leer la auditoría de GitHub

- ¿Cuántos tokens realmente medidos se gastaron por agente, por revisor y por etapa? Si no están disponibles, ¿qué evidencia proxy existe, con qué incertidumbre?
- ¿Qué porcentaje de lecturas/búsquedas/comandos parece redundante según hash, path y objetivo? ¿Cuánto texto inútil entró al contexto?
- ¿Se ejecutó la suite completa más de una vez por un cambio en código? ¿Qué repeticiones eran justificadas por cambio de fingerprint?
- ¿Qué tareas largas son cómputo local barato en tokens versus inferencia IA costosa? ¿Hay bucles de relectura por compactación?
- ¿Qué 3–5 mejoras son seguras, medibles y más eficaces? ¿Cuál de ellas no requiere modificar la arquitectura del harness?

## 9. PROMPT PORTABLE PARA CLAUDE U OTRA IA (copiar y pegar)

> Actúa como auditor técnico independiente de eficiencia de agentes de IA en un proyecto Laravel 13 + Vue 3/Inertia/TypeScript (StockFlash). Usamos OpenCode con un orquestador, implementador, revisor QA independiente y publicador, con gates deterministas, pruebas PHPUnit, typecheck/build y CI GitHub obligatoria. El propietario desea **reducir tokens y tiempo sin bajar calidad, seguridad, privacidad ni independencia del QA**, sin eliminar gates finales. La documentación contractual y el código fuente no son necesarios para estimar oportunidades de eficiencia del flujo; si alguna recomendación exige leerlos, indícalo explícitamente.
>
> Te entrego: (a) este protocolo; (b) un JSONL de eventos observables de una feature; (c) un resumen ANALYSIS.md, si está disponible. Los valores `null` significan información no observada; no son cero. Los tiempos de shell y Thought no equivalen a tokens. Si los valores fueron medidos por proveedor, respétalos; si son proxies, no los presentes como mediciones.
>
> **Tareas:** 1) identifica actividades duplicadas, relecturas, pruebas repetidas, salidas verbosas y ciclos de subagentes evitables; 2) separa consumo IA medido, volumen de contexto, tiempo de cómputo local y retrabajo; 3) distingue revisiones de seguridad obligatorias de actividades de bajo valor; 4) propone un plan de mejoras priorizado con costo de implementación, riesgos, controles conservados y forma de validar el ahorro; 5) compara alternativas de capturar automáticamente telemetría sin llamar al modelo por evento; 6) enumera datos faltantes que impiden calcular el ahorro real.
>
> **Formato de respuesta:** hallazgos sustentados con `event_id`/evidencia, tabla `problema → prueba → cambio propuesto → riesgo → métrica antes/después`, 5 prioridades máximas y lista de supuestos/limitaciones. No inventes porcentajes, precios, tokens ni causas. No sugieras apagar QA, tests obligatorios, protección de secretos, CI exact-SHA ni controles Git. Señala expresamente si la segunda opinión contradice la primera y por qué.

## 10. Criterio de cierre

La auditoría no se considera completa por la mera existencia del archivo: requiere eventos representativos de **todos los subagentes visibles**, resumen de cobertura y limitaciones, análisis agregado con ejemplos trazables y envío `SFH1` que indique rutas y SHA. ChatGPT leerá directamente GitHub y decidirá ajustes futuros de harness, sin modificar una feature que todavía esté en ejecución.
