# SDD híbrido (owner+ChatGPT lidera; OpenCode ejecuta)

### Capas
**Producto/arquitectura:** Owner y ChatGPT cierran QUÉ, POR QUÉ, decisiones, criterios verificables, límites, UX, alcance comercial.
**Repo:** especificaciones/ADR/Feature Packets aprobados y versionados (fuente persistente).
**OpenCode:** implementar CÓMO en tareas pequeñas y tests, con modelo libre seleccionado, sin rehacer specs.
**QA independiente:** reviewer read-only verifica diff, tests, accesibilidad y contratos.
**Entrega:** pipeline determinista tras gates; agent nunca accede a secretos de release.

### Ciclo por feature
0. El owner coloca feature en `docs/work/features/F-XXXX.md` con `status: APPROVED`, referencias normativas exactas, criterios de aceptación y dependencias ya autorizadas.
1. `/sf-diagnostico`: diagnóstico de repo/stack, sin editar.
2. `/sf-plan F-XXXX`: plan técnico de máximo 10 subtareas, archivos, tests y riesgos; sin suponer cambios normativos.
3. `/sf-feature F-XXXX`: implementa las subtareas de la feature aprobada, con evidencia/checkpoints y pruebas. No precisa una nueva autorización del plan ni microaprobaciones por cada subtarea, pero respeta permisos `ask`; ante cambios de alcance, dejar solicitud estructurada.
4. `/sf-review F-XXXX`: reviewer compara cambios y tests; resultado APROBADO/REQUIERE CAMBIOS (no aprueba automáticamente release).
5. Cuando exista pipeline de CI configurado, el sistema hará commit/push/staging después de checks. No activar este flujo antes de disponer de tests y credenciales aisladas.
6. Owner/ChatGPT aprueba cambios de spec e hitos productivos, no cada modificación trivial de implementación.

### Reglas de escalamiento
No se amplía alcance, no se crean dependencias inesperadas, no se altera schema congelado, no se cambia regla fiscal/privacidad/licencia; en cambio producir `docs/work/DECISION-REQUEST-TEMPLATE.md` completado o issue con pregunta y alternativas. OpenCode NO puede llamar automáticamente a este chat: el owner traslada el pedido y trae el ADR a Git.

### Contexto y memoria
Un feature por sesión; sólo lectura puntual de normativa; Engram opcional y consultivo; memoria resumida sin credenciales. No ruteo dinámico de modelos a mitad de trabajo.
