# StockFlash — Protocolo de documentación HTML de usuario

**Sucesión aprobada BRIDGE-001, 2026-11-12:** Codex CLI asume la autoría
explicativa y mantenimiento antes reservados a ChatGPT. Las menciones de
ChatGPT como autor en este protocolo incluyen a Codex como sucesor técnico.
OpenCode conserva integración, QA y publicación, sin redacción autónoma.
Los pedidos `GUIA_PENDIENTE_CHATGPT` se entregarán a Codex por el puente una vez
validado; mientras BRIDGE-001 esté bloqueado no atribuir comunicación automática.
La publicación sigue controlada por publisher y requiere autorización del entorno.
El piloto no autoriza publicación de código; una solicitud posterior del
propietario permite entregar solo Markdown en la rama piloto (ver AGENTS).
No cambia la cobertura del manual.


**Trazabilidad:** el propietario reservó la redacción a ChatGPT el 2026-11-10 y
transfirió esa responsabilidad a Codex CLI en BRIDGE-001. La separación respecto
de OpenCode se conserva; las instrucciones operativas siguientes usan Codex.

## Punto de acceso y formato

- Ubicación canónica: `public/docs/index.html`. Ruta del sistema: `/docs/`. Actualización acumulativa tras cada etapa funcional concluida.
- Una guía intuitiva para el primerizo, en español de Chile; explicación de para qué sirve cada opción, botones/campos, pasos reales, permisos y ejemplos sencillos; menú lateral, buscador por tema, acordeones, anclas, móvil y teclado.
- Solo describir como disponibles funcionalidades realmente implementadas y verificadas. Indicar siempre la diferencia entre desarrollo local y despliegue a clientes.
- `documentacion/` y `public/documentacion/` se conservan como fuentes o documentación heredada; no sustituyen el manual navegable ni autorizan a inventar contenido.

## Separación obligatoria de responsabilidades

**Codex CLI, autor exclusivo del contenido dirigido al usuario:** títulos,
navegación, explicaciones, ejemplos, pasos, glosario, preguntas y toda modificación
semántica de `public/docs/index.html`. Revisa código/UI/evidencia antes de escribir
y entrega la edición al flujo controlado de gates, QA y publisher. Respeta anclas
y contenido publicado; el piloto no habilita publicación funcional en main.

**OpenCode, integrador técnico:** implementa enlaces/rutas, verifica HTML/CSS/JS,
navegación, responsive, accesibilidad y CI; publica por el harness autorizado.
No crea ni modifica explicaciones ni delega redacción. Al detectar contenido
ausente o incorrecto, entrega el pedido concreto a Codex según el estado del puente.

OpenCode puede mantener `docs/work/GUIA-HTML-ESTADO.md` y producir handoffs técnicos; estos NO constituyen documentación explicativa para el usuario.

## Ciclo por Feature Packet: detección y handoff automáticos

1. **Inicio:** durante `/sf-run` o equivalente, comprobar `docs/work/GUIA-HTML-ESTADO.md` y este protocolo. Anotar si la feature agregará o cambiará pantallas, botones, flujos o comportamientos visibles.
2. **Finalización funcional:** comparar cobertura con código y pantallas probadas.
   Si falta redacción de Codex, emitir `GUIA_PENDIENTE_CHATGPT` como alias histórico
   compatible. Un Markdown auxiliar no concede GUIA_ACTUALIZADA.
3. **Handoff obligatorio:** incluir feature, SHA, rutas/componentes, cambios de
   opciones y evidencia QA/CI. Entregar a Codex por el puente durante una ejecución
   validada; mientras no esté operativo, dejar el pendiente explícito. OpenCode
   no redacta el manual ni concede autorizaciones por falta del traslado.
4. **Cuando Codex entregue el HTML:** integrar por verificación técnica, QA y
   publisher; contrastar con UI y devolver a Codex las correcciones semánticas.
5. **Bitácora:** actualizar `docs/work/GUIA-HTML-ESTADO.md` indicando último código cubierto, funcionalidades cubiertas y SHA de documentación integrado. Si aún está pendiente, conservar indicador y fecha del pendiente.
6. **Al solicitar VERIFIED externo:** informar en el resumen si la guía está actualizada o pendiente. No bloquear el desarrollo ni publicación de código por falta del traspaso humano; no afirmar que el manual está al día cuando no lo esté.

**Límite:** el puente local conecta CLI, no este chat web. No hay vigilancia
permanente ni ejecución desatendida activada por este documento. La entrega
automática se atribuye solo después de demostrar BRIDGE-001 y en una ejecución
autorizada; la autenticación pendiente no se presenta como conexión operativa.

## Recuperación al cambiar de chat o sesión

Protocolo y bitácora son persistentes en Git. Codex recupera AGENTS, este
protocolo, bitácora, CURRENT, HTML, packet y código/QA de la etapa; contrasta HEAD
y snapshot autorizado y revisa cambios pendientes antes de editar. En el piloto
aislado, el transporte aporta SHA/manifiesto del origen sin abrir el repo privado.
La edición se integra mediante el flujo controlado, sin publicar por inferencia.

Esto permite continuidad reproducible **cuando el propietario inicia el encargo**, sin prometer lectura automática en una sesión donde todavía no se pidió continuar.

## QA técnico de la guía

- Buscar en español con/sin tildes, menú y acordeones con teclado y móvil, anclas estables, enlaces reales, `GET /docs/`, entrada «Ayuda» desde Dashboard.
- Sin datos personales, contraseñas reales, API keys ni tokens; solo ejemplos genéricos.
- Mantener una guía consolidada y sin duplicaciones; revisar coherencia con funciones realmente verificadas.
- Gates técnicos comprueban disponibilidad/estructura; no sustituyen la revisión
  editorial de Codex por redacción autónoma de OpenCode.

## Archivo de continuidad exclusivo para Codex

`docs/harness/CONTEXTO-CHATGPT.md` conserva su nombre histórico y lo mantiene Codex
al revisar cierres significativos. OpenCode solo consulta ese contexto; sí mantiene
los metadatos técnicos de GUIA-HTML-ESTADO tras integrar redacción de Codex.
