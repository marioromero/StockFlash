# SDD híbrido (propietario + Codex lideran; OpenCode ejecuta)

**Adenda aprobada BRIDGE-001 (2026-11-12):** Codex CLI sucede a ChatGPT en la
función técnica externa, prepara instrucciones y revisa las evidencias del
reviewer sin sustituirlo. Propietario conserva aprobación de alcance, decisiones
importantes, activación y VERIFIED. OpenCode conserva el coordinador y su cadena.
Python será transporte persistente, sin elegir features ni interpretar contratos.
Las instrucciones/revisiones rutinarias viajarán por el puente tras su aceptación;
no está operativo mientras falte aislamiento demostrado. Piloto sin publisher,
publicación de código, despliegues, migraciones ni acceso de escritura al original.
Solicitud posterior del propietario permite commit/push documental selectivo por
Codex en la rama piloto; ver excepción acotada en AGENTS.
No iniciar USERS-001. Ver packet BRIDGE-001 y CURRENT para estado vigente.


### Capas
**Producto/arquitectura:** propietario conserva aprobación de alcance/decisiones;
Codex prepara y revisa técnicamente QUÉ, POR QUÉ, criterios, límites y UX conforme
a contratos. No atribuye una aprobación humana inexistente.
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
5. `/sf-publish F-XXXX`: dos operaciones permanentes. **A (ordinaria):** publicador `stockflash-publisher` valida Git (main, sync ff-only con `origin/main`), alcance autorizado, `APROBADO` + `GATES_PASS` vigente ejecutado, staging explícito por archivo (nunca `git add -A`), `git diff --cached --check`, commit descriptivo y `git push origin main` sin force; publicar no significa VERIFIED ni autoriza deploy. **B (cierre documental):** con feature ya VERIFIED por decisión externa documentada e implementación publicada, publica solo registro de cierre (estado/evidencias), sin código ni contratos y sin repetir gates funcionales; este flujo no declara VERIFIED. Flujo permanente: `/sf-feature → /sf-verify → /sf-review → /sf-publish` (+ cierre documental autorizado).
6. Propietario decide cambios de spec, VERIFIED/release y decisiones reservadas
   con revisión técnica de Codex; no cada modificación ordinaria en alcance.

### Reglas de escalamiento
No ampliar alcance, dependencias, schema ni reglas fiscales/privacidad/licencia.
Emitir solicitud con alternativas y recomendación; el puente validado la entrega
a Codex/propietario sin conceder autorización. No existe llamada al chat web;
mientras la integral esté pendiente, dejar el handoff y bloqueo explícitos.

### Contexto y memoria
Un feature por sesión; sólo lectura puntual de normativa; Engram opcional y consultivo; memoria resumida sin credenciales. No ruteo dinámico de modelos a mitad de trabajo.
