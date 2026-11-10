# StockFlash — Protocolo de documentación HTML de usuario

**Decisión expresa del propietario (2026-11-10):** el contenido de la guía /docs/ será 100 % redactado por ChatGPT, no por OpenCode. Esta norma entra en vigor cuando el PR #2 se integra a main.

## Punto de acceso y formato

- Ubicación canónica: `public/docs/index.html`. Ruta del sistema: `/docs/`. Actualización acumulativa tras cada etapa funcional concluida.
- Una guía intuitiva para el primerizo, en español de Chile; explicación de para qué sirve cada opción, botones/campos, pasos reales, permisos y ejemplos sencillos; menú lateral, buscador por tema, acordeones, anclas, móvil y teclado.
- Solo describir como disponibles funcionalidades realmente implementadas y verificadas. Indicar siempre la diferencia entre desarrollo local y despliegue a clientes.
- `documentacion/` y `public/documentacion/` se conservan como fuentes o documentación heredada; no sustituyen el manual navegable ni autorizan a inventar contenido.

## Separación obligatoria de responsabilidades

**ChatGPT, autor exclusivo del contenido dirigido al usuario:** títulos, textos de navegación, descripciones, explicaciones, ejemplos, pasos, glosario, preguntas frecuentes y cualquier modificación semántica de `public/docs/index.html`. Lee el código, la UI y la evidencia antes de escribir; crea la revisión de HTML en rama/PR GitHub cuando dispone del conector, o entrega el archivo listo para integrar. Respeta anclas y contenido previamente publicado.

**OpenCode, integrador técnico:** puede implementar enlaces «Ayuda» en la interfaz, asegurar que `/docs/` se sirve, verificar HTML/CSS/JS, navegación, responsive, accesibilidad, CI y publicar mediante el harness. Puede corregir aspectos técnicos del estilo, script o enlaces sin cambiar el contenido redactado. **No crea ni modifica por su cuenta la explicación para usuarios, ni delega esa redacción a otro agente/modelo.** Al detectar texto ausente, incorrecto o desactualizado, emite un pedido concreto de actualización a ChatGPT por medio del propietario.

OpenCode puede mantener `docs/work/GUIA-HTML-ESTADO.md` y producir handoffs técnicos; estos NO constituyen documentación explicativa para el usuario.

## Ciclo por Feature Packet: detección y handoff automáticos

1. **Inicio:** durante `/sf-run` o equivalente, comprobar `docs/work/GUIA-HTML-ESTADO.md` y este protocolo. Anotar si la feature agregará o cambiará pantallas, botones, flujos o comportamientos visibles.
2. **Finalización funcional:** después de implementación, gates, QA y publicación, comparar el estado de guía contra el SHA y las pantallas implementadas. Si hay contenido que ChatGPT no ha redactado e integrado, marcar **`GUIA_PENDIENTE_CHATGPT`** en el informe de cierre; no declarar `GUIA_ACTUALIZADA` por haber escrito un Markdown auxiliar.
3. **Handoff obligatorio:** incluir feature, SHA, URLs/rutas y componentes Vue afectados, resumen técnico de nuevas opciones, packet y evidencia QA/CI, y una sola instrucción al propietario: **«Comparte este cierre con ChatGPT para actualizar /docs/»**. No rellenar el manual con texto de OpenCode.
4. **Cuando ChatGPT entregue el HTML:** integrar mediante flujo del harness (verificación técnica, QA, publisher). Comprobar el texto contra la UI real y pedir a ChatGPT cualquier corrección semántica. Añadir enlaces/rutas si faltan. Publicar y comprobar el SHA.
5. **Bitácora:** actualizar `docs/work/GUIA-HTML-ESTADO.md` indicando último código cubierto, funcionalidades cubiertas y SHA de documentación integrado. Si aún está pendiente, conservar indicador y fecha del pendiente.
6. **Al solicitar VERIFIED externo:** informar en el resumen si la guía está actualizada o pendiente. No bloquear el desarrollo ni publicación de código por falta del traspaso humano; no afirmar que el manual está al día cuando no lo esté.

**Limitación importante:** el harness NO dispone de conexión automática con esta sesión de ChatGPT. Ni OpenCode puede solicitarle contenido por sí solo ni ChatGPT reacciona en segundo plano a cambios de Git. El aviso automático en el handoff necesita que el propietario traiga la entrega a ChatGPT.

## Recuperación al cambiar de chat o sesión

El protocolo y la bitácora viven versionados en GitHub, no dependen del contexto de una conversación. En una sesión nueva el propietario puede decir: «Actualiza la guía /docs de StockFlash con la última etapa». ChatGPT debe abrir `AGENTS.md`, `docs/harness/GUIA-HTML-USUARIO.md`, `docs/work/GUIA-HTML-ESTADO.md`, `docs/work/CURRENT.md`, `public/docs/index.html`, el packet y el código/QA de la etapa; confirmar el HEAD y la existencia de cambios/PR pendientes antes de editar. Solo entonces redacta y publica la actualización.

Esto permite continuidad reproducible **cuando el propietario inicia el encargo**, sin prometer lectura automática en una sesión donde todavía no se pidió continuar.

## QA técnico de la guía

- Buscar en español con/sin tildes, menú y acordeones con teclado y móvil, anclas estables, enlaces reales, `GET /docs/`, entrada «Ayuda» desde Dashboard.
- Sin datos personales, contraseñas reales, API keys ni tokens; solo ejemplos genéricos.
- Mantener una guía consolidada y sin duplicaciones; revisar coherencia con funciones realmente verificadas.
- Gates técnicos pueden comprobar disponibilidad y estructura; nunca sustituir el criterio editorial de ChatGPT por generación de texto automática.
