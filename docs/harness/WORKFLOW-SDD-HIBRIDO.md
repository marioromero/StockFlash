# SDD híbrido (owner+ChatGPT lidera; OpenCode ejecuta)

### Capas
**Producto/arquitectura:** propietario decide NUEVAS reglas comerciales/producto y riesgos materiales; ChatGPT elige secuencia, tamaño de feature y criterios verificables DERIVADOS de contratos ya aprobados, sin pedir permiso administrativo.
**Repo:** especificaciones/ADR/Feature Packets aprobados y versionados (fuente persistente).
**OpenCode:** implementar CÓMO en tareas pequeñas y tests, con modelo libre seleccionado, sin rehacer specs.
**QA independiente:** reviewer read-only verifica diff, tests, accesibilidad y contratos.
**Entrega:** pipeline determinista tras gates; agent nunca accede a secretos de release.

### Ciclo por feature
0. ChatGPT prioriza y coloca packet `APPROVED` cuando implementa requisitos YA aprobados sin decisiones comerciales nuevas; dejar `approval_basis`, referencias precisas y `new_business_decisions: NONE`. Si hay alternativa comercial material sin resolver, pedir al propietario decisión concreta antes de aprobar la parte afectada. El propietario no ordena features ni aprueba descripciones genéricas.
1. `/sf-diagnostico`: diagnóstico de repo/stack, sin editar.
2. `/sf-plan F-XXXX`: plan técnico de máximo 10 subtareas, archivos, tests y riesgos; sin suponer cambios normativos.
3. `/sf-feature F-XXXX`: implementa las subtareas de la feature aprobada, con evidencia/checkpoints y pruebas. No precisa una nueva autorización del plan ni microaprobaciones por cada subtarea, pero respeta los permisos `allow`/`deny` sin prompts interactivos; ante cambios de alcance, dejar solicitud estructurada.
4. `/sf-review F-XXXX`: reviewer compara cambios y tests; resultado APROBADO/REQUIERE CAMBIOS (no aprueba automáticamente release).
5. `/sf-publish F-XXXX`: dos operaciones permanentes. **A (ordinaria):** publicador `stockflash-publisher` valida Git (main, sync ff-only con `origin/main`), alcance autorizado, `APROBADO` + `GATES_PASS` vigente ejecutado, staging explícito por archivo (nunca `git add -A`), `git diff --cached --check`, commit descriptivo y `git push origin main` sin force; publicar no significa VERIFIED ni autoriza deploy. **B (cierre documental):** con feature ya VERIFIED por decisión externa documentada e implementación publicada, publica solo registro de cierre (estado/evidencias), sin código ni contratos y sin repetir gates funcionales; este flujo no declara VERIFIED. Flujo permanente: `/sf-feature → /sf-verify → /sf-review → /sf-publish` (+ cierre documental autorizado).
6. El propietario aprueba solo decisiones nuevas de negocio/producto, cambios contractuales materiales y despliegues/BD remota. ChatGPT organiza features derivadas del contrato existente y registra VERIFIED técnico tras SHA, criterios, QA y CI sin segunda aprobación; OC ejecuta/publica. Nunca pedir autorización para elegir el próximo módulo o decidir el orden.

### Reglas de escalamiento
No se inventan reglas de negocio, dependencias materiales, esquema no aprobado, cambios fiscales/privacidad/licencia. Ante decisión material realmente abierta, reportar pregunta concreta con opciones/recomendación; seguir con las partes independientes que ya están aprobadas. OpenCode NO puede llamar automáticamente a este chat; el traspaso usa el intercambio compacto por GitHub de `docs/harness/GITHUB-HANDOFF-COMPACT.md` y el owner trae la decisión a Git sin copiar informes completos.

### Contexto y memoria
Un feature por sesión; sólo lectura puntual de normativa; Engram opcional y consultivo; memoria resumida sin credenciales. No ruteo dinámico de modelos a mitad de trabajo.

### Comunicación de bajo consumo (propietario 2026-11-17)
Al final de cada interacción normal, OpenCode responde al propietario **solo `OK`**; al cerrar una feature o requerir intervención humana, como máximo 1–3 líneas con estado real y acción. Los reportes técnicos extensos siguen siendo obligatorios **en archivos Git/QA/gates/SFH1**, no como respuesta conversacional. ChatGPT también entrega resúmenes mínimos (CI, revisión, estado y siguiente acción **si existe**); decide por sí mismo cuándo solicitar validación visual de un conjunto funcional de pantallas, respetando que sin despliegue autorizado o entorno accesible no puede exigir una prueba real. Ver regla 24 de `AGENTS.md` y el archivo de continuidad de ChatGPT.
