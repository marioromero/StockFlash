# Guía de Desarrollo con IA: OpenCode, Harness Engineering y SDD

Este documento consolida las mejores prácticas, comandos y metodologías para trabajar con OpenCode y desarrollar software guiado por inteligencia artificial. Está diseñado para servir como contexto base para inicializar un proyecto desde cero.

## 1. Fundamentos y Uso de OpenCode

OpenCode es un agente de código IA de código abierto. Funciona bajo un ciclo de: Entender -> Planificar -> Actuar -> Evaluar -> Ajustar.

### Comandos Principales
*   `/help`: Guía de referencia.
*   `/agents` o `TAB`: Alterna entre el modo **Build** (ejecuta/escribe código) y **Plan** (analiza y propone pasos antes de actuar).
*   `/init`: Analiza el proyecto y genera automáticamente el archivo `AGENTS.md`.
*   `/models` / `/connect`: Selecciona proveedores y modelos de IA.
*   `/status` / `/new` / `/session`: Gestión de estado y sesiones.
*   `CTRL + T`: Cambia el nivel de esfuerzo del modelo.
*   `@`: Hace referencia a ficheros, carpetas, skills o subagentes (ej. `@MEMORY.md`, `@explore`).
*   `!`: Modo Shell para ejecutar comandos de terminal.

### Modo Plan vs Modo Build
*   **Modo Plan:** Úsalo para tareas grandes, refactors, o supervisión estratégica antes de tocar código.
*   **Modo Build:** Ejecuta las implementaciones concretas de tareas ya planificadas y testeadas.

---

## 2. Harness Engineering (Ingeniería de Contexto)

Para que el agente no alucine y mantenga el rumbo, necesita "arneses" y "guardarraíles" mediante archivos de contexto.

### AGENTS.md (El archivo core)
Generado con `/init`. Debe contener (máx 30-40 líneas):
1.  **Stack y estructura:** Tecnologías clave y arquitectura básica.
2.  **Comandos:** Cómo ejecutar, testear y compilar.
3.  **Convenciones:** Estilo de código, idioma de UI/comentarios, patrones.
4.  **Reglas de dominio:** Trampas conocidas y lógica de negocio (ej. manejo de fechas en hora local, no UTC).
5.  **Forma de trabajar:** Cuándo planificar, tamaño de commits.
6.  **Límites:** 
    *   *Siempre:* Reglas innegociables.
    *   *Pregunta antes:* Nuevas dependencias, cambios de esquema.
    *   *Nunca:* Lo que no debe tocar.
7.  **Verificación:** Cómo probar antes de dar por terminado.

### MEMORY.md
Archivo para mantener la memoria del proyecto entre sesiones (máx 50 líneas).
*   **Estado actual:** Qué funciona en la versión actual.
*   **Decisiones (y por qué):** Justificación técnica.
*   **Aprendizajes y errores a evitar:** Lecciones de implementaciones pasadas.
*   **Próximos pasos.**
*   *Regla:* El agente debe leerlo al inicio y actualizarlo al terminar cada tarea.

---

## 3. Comandos Personalizados, Skills y MCP

### Comandos Personalizados (`.opencode/commands/`)
Archivos Markdown para prompts reutilizables.
*   *Ejemplo (`feature.md`):* Define un prompt que exige planificar una funcionalidad, listar archivos afectados, casos límite y actualizaciones a `AGENTS.md` antes de escribir código.

### Agent Skills (`.agents/skills/`)
Instrucciones reutilizables (Skills). Se instalan (ej. `npx skills add vercel-labs/agent-skills`) y se invocan con `/`.
*   *Ejemplo local (`local-dates`):* Reglas estrictas de cómo manejar fechas (`getFullYear()`, evitar `toISOString()`, checklist de revisión).

### MCP (Model Context Protocol)
Conecta al agente con herramientas externas. Se configura en `opencode.json`.
*   **Chrome DevTools:** Para abrir la web, probar UI, leer consola y hacer capturas (responsive).
*   **Otros útiles:** Context7 (Docs actualizadas), GitHub, Figma, Supabase.

---

## 4. Spec-Driven Development (SDD)

Metodología obligatoria: Definir requisitos, límites y criterios primero; el código es un subproducto de la especificación.
*   **Spec-anchored:** La spec se mantiene viva y evoluciona con el código.

### Flujo SDD Paso a Paso:
1.  **Constitución (`docs/constitution.md`):** Principios innegociables (ej. simplicidad, no dependencias externas, tests como barrera, idioma).
2.  **Especificación (`specs/NNN-nombre/spec.md`):** El QUÉ y el POR QUÉ. 
    *   Usa formato **EARS** para Criterios de Aceptación (ej. *CUANDO [evento], EL SISTEMA [respuesta]*).
    *   Incluye casos límite y dudas (`[NECESITA ACLARACIÓN]`).
3.  **Clarificación:** El agente revisa la spec como QA buscando ambigüedades.
4.  **Planificación (`plan.md`):** Archivos afectados, funciones puras necesarias, estrategia de tests, decisiones técnicas justificadas.
5.  **Tareas (`tasks.md`):** Máx 10 tareas pequeñas (20-30 min), en orden, con checkboxes y criterios de "Hecho cuando".
6.  **Implementación:** TDD estricto. Implementar SOLO una tarea. Tests en rojo -> Código -> Tests en verde -> Marcar tarea -> Parar.
7.  **Validación:** Revisar la spec RF por RF usando tests o MCP (DevTools).

*(Nota: Existen comandos SDD para automatizar este flujo: `/sdd-constitution`, `/sdd-spec`, `/sdd-plan`, `/sdd-implement`, `/sdd-validate`).*

---

## 5. Sistemas Multiagente y Subagentes

En lugar de un solo agente, se orquestan varios roles que se comunican.

### Agentes por Defecto
*   **Principales:** `build` (escribe código), `plan` (analiza).
*   **Subagentes:** `general` (investiga), `explore` (solo lectura).

### El Flujo Multiagente SDD (Ejemplo de Orquestación)
Se coordinan a través de un **Agente Coordinador** (ej. `.opencode/agents/coordinator.md` con `mode: primary`) que dirige el flujo SDD:

1.  **Coordinator (Coordinador):** Habla con el usuario. Delega tareas, pasa contexto y exige aprobaciones. NO edita código.
2.  **Planner (Planificador):** Subagente (`mode: subagent`). Redacta `spec.md`, `plan.md` y `tasks.md`. Nunca escribe código de producción.
3.  **Reviewer (Revisor):** Subagente (`mode: subagent`). Actúa como QA. Valida specs (clarificación) y comprueba la implementación final con `node --test` y `git diff`. Devuelve `APROBADO` o `CAMBIOS NECESARIOS`.
4.  **Implementer (Implementador):** Subagente (`mode: subagent`). Ejecuta UNA sola tarea del `tasks.md`. Obligado a hacer tests primero. Verifica visualmente con Chrome DevTools.

**Regla de Oro:** El sistema nunca debe saltarse una aprobación humana (en la Spec o en el Plan) antes de que el implementador toque el código. Tú diriges la arquitectura y validas las intenciones, la IA ejecuta.