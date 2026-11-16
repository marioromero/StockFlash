# StockFlash — CONTEXTO PARA CHATGPT (continuidad entre sesiones)

**Última revisión por ChatGPT:** 2026-11-16
**Repositorio:** `marioromero/StockFlash` · rama de desarrollo `main`
**Estado de referencia observado:** `ceb50e02616d9d1185e4f849c7f2a8760d80f6fc` (CORESEQ-001 verificado contra QA/CI, servicio interno sin manual nuevo; CORELIC-001 activa; comprobar HEAD)
**Aviso de vigencia:** este SHA es un punto de control histórico, NO el HEAD garantizado al abrir una nueva sesión. Verificar HEAD, PR abiertos y documentación actual antes de actuar.

## 1. INSTRUCCIONES PARA CUALQUIER SESIÓN NUEVA DE CHATGPT

Si el propietario solicita «continuar StockFlash», «revisar lo que hizo OpenCode» o «seguir el manual»:

1. Leer este archivo en GitHub, luego `AGENTS.md`, `docs/work/CURRENT.md`, `docs/harness/GUIA-HTML-USUARIO.md` y `docs/work/GUIA-HTML-ESTADO.md` (si están integrados).
2. Consultar HEAD de `main`, últimas Feature Packets, QA/gates, GitHub Actions y código relevante. El estado de este archivo puede estar desactualizado frente a un commit nuevo.
3. Contrastar resultados de OpenCode con evidencia real, sin dar por verdes CI/QA no comprobados.
4. **Decidir autónomamente prioridades, secuencia, dependencias y división de features del contrato YA aprobado**; redactar y activar packets técnicos derivados, sin consultar al propietario ni pedir aprobación de propuestas genéricas. El propietario se consulta únicamente por decisiones NUEVAS materiales de negocio/producto o autorización de despliegue/BD remota; presentar preguntas específicas con alternativas y recomendación. La delegación técnica no permite inventar nuevas decisiones comerciales.
5. **Actualizar ESTE archivo en GitHub cuando una revisión cambie materialmente el estado del proyecto, los compromisos o los próximos pasos**, conservando el histórico de hitos con referencia SHA. Autoría y mantenimiento de este archivo: **ChatGPT**. OpenCode lo lee sin editarlo.
6. Si la revisión incluye una funcionalidad visible nueva o modificada, actualizar como autor exclusivo el contenido del manual `public/docs/index.html`, o preparar una rama/PR con la modificación hasta que el harness la integre. Comprobar nombres de campos y pantallas reales, no inventarlos.
7. Para cambios en el contexto, preferir un commit documental acotado solo a este archivo, con control del SHA previo y sin sobrescribir trabajo paralelo. Si el flujo de Git disponible o el protocolo vigente exige PR, usar PR y dejar identificada la revisión pendiente.
8. SFH1 permite intercambio asíncrono por GitHub issue bajo demanda: el propietario escribe `ok` a la IA destinataria, quien consulta el issue y procesa mensajes dirigidos a ella. Sin despertador automático ni copia de informes. `ok` no aprueba nuevos packets, migraciones remotas ni deploy. VERIFIED requiere dictamen externo técnico de ChatGPT sobre evidencia del packet previamente aprobado; no una segunda firma.
9. Revisar también el roadmap HTML del propietario (`resources/roadmap/index.html`) y su protocolo. Cuando los hitos reales, las funcionalidades listas o el camino a R7 cambien, actualizarlo **solo desde ChatGPT**, sin plazos ni porcentajes inventados. OpenCode no edita ese archivo.
10. **FIN DE CADA RESPUESTA SIN LIMBO — INSTRUCCIÓN PERMANENTE DEL PROPIETARIO (2026-11-16).** **En toda interacción de StockFlash, sea revisión, consulta o trabajo realizado, finalizar siempre con UNA acción concreta, útil e inequívoca que el propietario deba realizar**, bajo el rótulo `**Tu siguiente acción:**`; especificar exactamente en qué chat/aplicación, qué debe escribir o decidir y qué se espera después. Prioridad normal: `escribe ok en OpenCode` **SOLO** si se comprobó un issue SFH1 ABIERTO con mensaje nuevo `dst=OC` aún no procesado y alcance previamente autorizado; `escribe ok aquí` solo cuando realmente corresponda a un mensaje `dst=GPT` pendiente. **Nunca inventar un OK** ni enviarlo a una IA sin trabajo listo, no crear issues o trabajo artificial solo para mantener el circuito. Si no hay trabajo pendiente de OC, presentar **el siguiente paso REAL**: por ejemplo, un conjunto breve de características/decisiones nuevas, explicadas en lenguaje del propietario para que las apruebe, o una acción específica de revisión/elección. No pedir aprobación de características que solo implementan obligaciones previamente aprobadas ni reaprobar publicación/VERIFIED. ChatGPT debe seleccionar la siguiente feature segura, preparar packet + SFH1 y finalizar con `ok` para OC cuando efectivamente haya tarea. Si hace falta una decisión comercial nueva, preguntar eso concretamente, nunca «¿apruebas esta lista genérica?» o «¿continuamos con R3?». Si una herramienta/CI impide continuar, indicar **qué acción práctica** debe realizar el propietario para desbloquear; no afirmar avance inexistente. **Prohibido terminar solo con estados, enlaces, resumen, «¿qué sigue?» o propuestas vagas.** Esta regla no obliga a inventar trabajo ni aprobación cuando no se necesitan.

**Idea central:** este archivo es un índice de continuidad y decisiones, no sustituye la fuente de verdad (el repositorio, Feature Packets y código).

## 2. PROPÓSITO, STACK Y REGLAS INVARIANTES

**Producto:** StockFlash, plataforma comercial modular para Chile. Arquitectura Laravel 13 + Vue 3 + Inertia 3 + TypeScript/Vite. La documentación y textos UX usan español de Chile.

**Módulos comerciales:** POS, Bodega, SyncWP, Fiscal, Compras, Analítica. Control es administración interna.

**Primer lanzamiento:** POS + Inventario sin emisión DTE integrada; referencia fiscal externa cuando proceda. Planes iniciales y demo/gratis quedan regidos por contratos v2.2, no inventar reglas pendientes.

**Contratos prevalentes:** `AGENTS.md`; `docs/governance/CONSTITUTION.md`; archivos `docs/product/especificacion-v2.2-*-CONSOLIDADO-EN-REVISION.md`; `docs/work/features/*.md`.

**Flujo actual (2026-11-13):** propietario aprueba una feature UNA vez → OpenCode implementa → gates `GATES_PASS` → QA independiente `APROBADO` → publisher Git automático (modo `AUTO_GIT`) → GitHub Actions en SHA → ChatGPT revisa al recibir `ok` → correcciones SFH1 con informe `.md`, sin nueva autorización Git → cierre `VERIFIED` por dictamen técnico externo ChatGPT respaldado por aprobación inicial y evidencia; sin segunda firma. No staging/producción ni BD remota sin autorización propia. Permisos amplios `allow/deny` según harness.

## 3. HITOS VALIDOS EN LA FECHA DE ESTE SNAPSHOT

- `BOOT-001`: VERIFIED. Base Laravel/Vue/Inertia/TypeScript.
- `AUTH-001`: VERIFIED, código `55b7f16`, cierre `79f64b6`. Login, sesiones, cambio de contraseña, RBAC.
- `BRANCH-001`: VERIFIED, código consolidado `4f81457`, cierre `55086ed`. CRUD de sucursales, permisos, auditoría transaccional. CI PHP/Node/smoke/contratos confirmada en GitHub para `4f81457`.
- `DOCS-001`: **VERIFIED** (decisión expresa del propietario, 2026-11-10). Código publicado `90254e5`, cierre documental `3fde691`, gates `GATES_PASS`, QA independiente `APROBADO` y CI PHP/Node/smoke + contratos SUCCESS ([42908696754](https://github.com/marioromero/StockFlash/actions/runs/42908696754), [42908696677](https://github.com/marioromero/StockFlash/actions/runs/42908696677)). Manual /docs/, enlace Ayuda y continuidad ChatGPT integrados.
- `ROADMAP-001`: **VERIFIED** (decisión externa del propietario y cierre documental `docs/work/ROADMAP-001-CLOSURE-20261110.md` publicado en `main`). Implementación `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41`; integración selectiva editorial del PR #4 y cierre documental presentes en el snapshot `3735c147`. Gate definitivo `ROADMAP-001-VERIFY-20261110-194620.md` = GATES_PASS (RM1–RM15), QA definitivo `ROADMAP-001-DOCS-REVIEW-20261110-2.md` = APROBADO (0 bloqueantes). **CI remota comprobada por ChatGPT** para `3735c147`: [PHP/Node/smoke SUCCESS](https://github.com/marioromero/StockFlash/actions/runs/42918708678), [contratos SUCCESS](https://github.com/marioromero/StockFlash/actions/runs/42918708636). `/roadmap` requiere `settings.manage`; botón del Dashboard, hitos R0–R10, bitácora y alertas `ROADMAP_*` operativos.
- `USERS-001`: **VERIFIED técnico por ChatGPT 2026-11-16, no desplegado.** Primer commit `882f281`, hallazgo GPT sobre valores PII en logs y corrección `2aec03893c78bc7180544930e0b6f741599df9ed` (2026-11-13). Gates 11/11 PASS `USERS-001-VERIFY-20261113-173228.md`, revisor independiente `USERS-001-REVIEW-20261113-173533.md` APROBADO, 199 PHP tests/1183 aserciones en local. CI en SHA corregido: [PHP/Node/smoke SUCCESS](https://github.com/marioromero/StockFlash/actions/runs/43503252099) y [contratos SUCCESS](https://github.com/marioromero/StockFlash/actions/runs/43503252172). D01–D16/P1–P4 cubiertas; PII excluida de old/new. Concurrencia real MariaDB aislada y validación visual móvil no ejecutadas. Reportes enviados SFH1 issue #13 `i=010`, dirigidos a GPT; no implica trabajo pendiente para OC.
- Sin despliegue nuevo ni migraciones remotas autorizadas en este snapshot.

## 4. MANUAL HTML — AUTORÍA 100 % CHATGPT

**Decisión del propietario:** ChatGPT redacta el 100 % de títulos, explicaciones, pasos, definiciones, preguntas frecuentes y ejemplos de la documentación de usuario; OpenCode no puede crear ni reescribir esos textos. OpenCode solo integra, automatiza seguimiento, prueba UI/CSS/JS, añade enlace Ayuda y publica.

**Referencia canónica:** `public/docs/index.html` (URL `/docs/`). Manual inicial 14 temas; ampliación ChatGPT del 2026-11-13 a **19 temas** (cinco sobre USERS-001), publicada junto al roadmap en `79c3b0f`. Indica implementada con cierre `VERIFIED` pendiente y sin deploy comercial. Navegación, búsqueda y acordeones responsive conservados.

**Estado de integración al 2026-11-10:** la guía inicial y el protocolo se integraron selectivamente en `main` mediante DOCS-001, commit `90254e5`. El PR #2 fue la fuente original; **no debe fusionarse**, porque su rama antecede a revisiones de USERS-001 y podría introducir regresiones. OpenCode integró los archivos ChatGPT byte a byte, con bitácora técnica actualizada. Enlace histórico: https://github.com/marioromero/StockFlash/pull/2

**Reglas persistentes integradas en main:** `docs/harness/GUIA-HTML-USUARIO.md`, `docs/work/GUIA-HTML-ESTADO.md`, `AGENTS.md`, `.opencode/commands/sf-run.md`, `sf-feature.md`, `sf-review.md`, `sf-publish.md` y `sf-handoff.md`.

**Mecánica:** cuando OC implemente una función visible nueva, debe emitir `GUIA_PENDIENTE_CHATGPT` con SHA, UI y evidencia. El propietario entrega ese resumen a ChatGPT; ChatGPT revisa código y escribe contenido. OC integra y verifica. Una nueva sesión retoma desde este archivo y la bitácora.

**Estado editorial:** manual `/docs/`, contexto y roadmap preparados en PR #3 y luego la explicación del botón «Avance del proyecto» preparada en PR #4 **ya están integrados selectivamente en `main`** al corte `3735c147`. PR #3 y PR #4 fueron cerrados sin fusionar para evitar cambios antiguos; no volver a aplicarlos. **Pendiente exclusivamente editorial de ChatGPT:** actualizar en el roadmap y este contexto el estado ROADMAP-001 a VERIFIED y el gate a `194620`; revisión preparada en [PR #5](https://github.com/marioromero/StockFlash/pull/5), rama `docs/chatgpt-cierra-roadmap001-20261110`, posterior a ese corte. Verificar su integración en GitHub.

**Estado actual:** la restricción DRAFT dejó de aplicar. USERS-001 ya está implementada, publicada y documentada con aviso expreso de que VERIFIED formal aún no se declara; no mostrarla como desplegada a clientes.

## 4B. ROADMAP HTML DEL PROPIETARIO — AUTORÍA EXCLUSIVA CHATGPT

**Decisión expresa (2026-11-10):** ChatGPT redacta y mantiene por sí solo la página visual de progreso y sus estados. Archivo canónico: `resources/roadmap/index.html`, fuera del directorio público. URL prevista tras integración: **`/roadmap`**, protegida para usuario autenticado con `settings.manage` (Admin Central).

**Fuentes de avance:** `docs/harness/ROADMAP-STOCKFLASH.md`, `docs/work/CURRENT.md`, Feature Packets, QA/gates y CI verificadas. Refleja R0–R10, capacidades realmente terminadas, pendientes para comercializar y versiones posteriores. No asigna fechas ni porcentajes arbitrarios.

**Estado al 2026-11-13:** `resources/roadmap/index.html` actualizado por ChatGPT en `79c3b0f` para reflejar USERS-001 implementada y publicada, pendiente de VERIFIED; ruta `/roadmap` mantiene permiso `settings.manage`; R0–R10 sin porcentajes ni fechas ficticias. BOOT/CI/AUTH/BRANCH/DOCS/ROADMAP verificados. Primera comercial R7 POS+Bodega sin DTE integrado; R4 Bodega, R5 licencias, R6 POS y controles R7 pendientes. Después R8 SyncWP, R9 Fiscal, R10 Compras/Analítica.

**Continuidad:** `docs/harness/ROADMAP-HTML-PROYECTO.md` contiene reglas obligatorias de autoría; `docs/work/ROADMAP-HTML-ESTADO.md` es una bitácora técnica que OpenCode puede modificar (no el HTML). Tras cada feature relevante OpenCode informa `ROADMAP_PENDIENTE_CHATGPT` con evidencia; el propietario lo traslada a ChatGPT; ChatGPT revisa y redacta actualización si corresponde. La nueva sesión de ChatGPT debe comprobar ambos archivos antes de intervenir.

## 5. TRABAJO ABIERTO Y SIGUIENTE PASO

1. USERS-001 y HCTX-001: VERIFIED técnico, informes de cierre publicados el 2026-11-16; sin despliegue. Concurrencia MariaDB real y UX visual quedan explícitamente pendientes para predespliegue.
2. CFG-001/002/003 y CORESEQ-001: VERIFIED técnico. CORESEQ-001 SHA `ceb50e0`, 16 gates, 282 pruebas, QA/CI verde; sin concurrencia MariaDB real. CORELIC-001 activa: contrato local de licencia, avisos y modo solo lectura, sin servidor comercial.
3. SFH1: mantener un issue activo por tarea; no exigir autorizaciones administrativas de cierre repetidas. ChatGPT verifica y registra el cierre, OpenCode solo lo publica.

## 6. FORMATO BREVE DE ACTUALIZACIÓN DE ESTE ARCHIVO

En cada cierre revisado de OC, ChatGPT debe:
- Actualizar «Última revisión», «Estado de referencia observado» y estados afectados en §3.
- Registrar feature ID, SHA, QA, gates y CI que se hayan verificado, y lo que siga pendiente.
- Actualizar §4 con cobertura del manual, PR/commit correspondiente, sin afirmar integración no verificada.
- Actualizar §4B con cobertura y cambios del roadmap HTML, diferenciando DRAFT, VERIFIED y versión comercial; sin fechas ni porcentajes inventados.
- Sustituir el siguiente paso de §5 y mantener un único estado actual, evitando que la sección se vuelva una acumulación de logs.
- No insertar secretos ni información personal.

**Instrucción mínima para el propietario al cambiar de chat:** «Continúa StockFlash. Lee `docs/harness/CONTEXTO-CHATGPT.md` del repositorio `marioromero/StockFlash`, revisa el HEAD y el último cierre de OpenCode».
