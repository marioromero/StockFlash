# Guía interactiva de usuario — StockFlash

**Decisión del propietario (2026-11-10):** mantener una guía HTML acumulativa, sencilla e interactiva para quien utiliza StockFlash por primera vez.

## Ubicación y alcance
- Documento principal: `public/docs/index.html`; URL desde la aplicación: `/docs/`.
- Un único punto de entrada, sin servidores, APIs, CDN ni dependencias externas para búsqueda o navegación.
- Menú lateral agrupado, búsqueda por tema y palabras clave, secciones enlazables mediante anclas, elementos desplegables, diseño responsive y accesible.
- Español de Chile, lenguaje comprensible, ejemplos cotidianos y nombres de botones que existan efectivamente en la interfaz.
- Solo presentar como disponibles funcionalidades implementadas y comprobadas; distinguir desarrollo local y despliegue productivo.
- Actualización acumulativa: cada etapa funcional verificada amplía lo anterior sin perder ni duplicar temas.
- Incluir en cada tema: para qué sirve, dónde está, qué significan las opciones, instrucciones paso a paso, permisos, límites, errores frecuentes y ayuda.
- Conservar los manuales existentes de `documentacion/` y `public/documentacion/` como antecedentes; evitar contenidos contradictorios.

## Responsables
- **ChatGPT** redacta y actualiza el contenido HTML tras cada cierre funcional que el propietario comparta en la conversación, contrastándolo con las pantallas, código y manuales vigentes. ChatGPT no trabaja de manera autónoma entre conversaciones.
- **OpenCode** incorpora los cambios redactados, enlaza `/docs/` desde la interfaz, revisa enlaces, funcionalidades de búsqueda, anclas, responsive y accesibilidad, ejecuta sus verificaciones y publica por el harness.
- Si una etapa termina sin actualización de la guía, el informe debe indicarlo como pendiente visible para que el propietario traiga el cierre a ChatGPT, sin inventar texto funcional ni afirmar que la guía está actualizada.
- Ni la guía ni su publicación significan automáticamente `VERIFIED`; se mantiene la regla de decisión externa.

## Comprobaciones de integración
- `GET /docs/` devuelve la guía sin requerir un despliegue; probar localmente mediante Laravel/servidor web o inspección estática equivalente donde corresponda.
- El enlace de ayuda en Dashboard lleva a `/docs/`.
- La búsqueda funciona con palabras y tildes, el menú lateral y los desplegables funcionan con teclado y en móvil, y las anclas apuntan a secciones reales.
- No hay rutas ni funciones documentadas como disponibles antes de su implementación.
- No modificar permisos de OpenCode ni reconstruir el harness para integrar la guía.
