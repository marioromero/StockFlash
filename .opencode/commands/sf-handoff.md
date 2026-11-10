---
description: Preparar resumen corto para dueño y ChatGPT al cerrar o bloquear una tarea
agent: plan
---

Sin editar, generar nota corta del estado actual: feature, commit si existe, qué cambió, pruebas que sí corrieron, bloqueos, solicitud DEC si aplica, siguiente acción concreta. No pegar textos completos ni secretos. Recordar: dueño debe traer el reporte a ChatGPT; no hay conector directo.

**Guía HTML:** leer `docs/harness/GUIA-HTML-USUARIO.md` y `docs/work/GUIA-HTML-ESTADO.md` y cerrar con una línea `GUIA_ACTUALIZADA` o `GUIA_PENDIENTE_CHATGPT`. Si pendiente, dar al propietario ID de feature, SHA, rutas/componentes y cambios visibles necesarios para que ChatGPT escriba el contenido. No redactarlo desde OpenCode; no existe conexión automática entre sesiones.


**Continuidad entre chats:** leer `docs/harness/CONTEXTO-CHATGPT.md` como referencia de decisiones, pero no editarlo. Resumir SHA, QA/CI, rutas/pantallas nuevas y próximos pasos, indicando al propietario que ChatGPT actualizará su propio contexto y el contenido de `/docs/` si corresponde cuando reciba el cierre.

**Roadmap del propietario:** añadir `ROADMAP_ACTUALIZADO` o `ROADMAP_PENDIENTE_CHATGPT` si hubo impacto en hitos, versiones o capacidad de comercialización. Cuando esté pendiente, incluir feature, SHA, QA/CI y cambios reales para que el propietario lo lleve a ChatGPT. El HTML `resources/roadmap/index.html` y sus textos son de ChatGPT exclusivamente; OC solo lo integra y prueba.
