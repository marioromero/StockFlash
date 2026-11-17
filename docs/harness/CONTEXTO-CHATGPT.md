# StockFlash — CONTEXTO PARA CHATGPT (continuidad entre sesiones)

**Última revisión por ChatGPT:** 2026-11-17
**Repositorio:** `marioromero/StockFlash` · rama de desarrollo `main`
**Estado de referencia observado:** SETUP-001 VERIFIED técnico (code `13cdd6a`, CI 2/2); su issue #25 cerrado con SFH1 i=010 OC y CI de HEAD `d5229872` confirmada (run `44183821283` PHP/Node/smoke y `44183821381` contratos SUCCESS, revisados por GPT). Nuevo packet `docs/work/features/INSTALL-001.md` APPROVED con delegación comercial vigente, requisito CORE-17 P0; `docs/work/CURRENT.md` lo deja ACTIVO, pero OC debe primero obtener **CI 2/2 verde del HEAD posterior a estos commits documentales**. Solo código y pruebas de instalador, sin instalar realmente, tocar .env real/BD remota/migraciones/seeds/cron/deploy. BKP-08 aún bloquea producción no demo. Siguiente SFH1 issue nuevo para INSTALL-001, `dst=OC` pendiente al crearse.
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
10. **RESPUESTAS BREVES Y ACCIONES REALES — propietario 2026-11-17 (actualiza regla anterior de 2026-11-16).** En interacciones StockFlash: responder solo con lo que el propietario necesite saber, normalmente **1–3 líneas**: feature, CI GitHub efectivamente comprobada (PASS/FAIL/PENDING), dictamen de revisión y estado o bloqueo. **No** narrar pasos, logs, pruebas individuales, cambios de código, reportes ni sugerencias genéricas; la evidencia íntegra se mantiene en GitHub. Cuando exista una intervención humana realmente necesaria, terminar con **`Tu siguiente acción:`** y una instrucción inequívoca (qué chat/aplicación, qué escribir o comprobar y por qué). Cuando **no haya acción humana**, no inventar una, no forzar el rótulo y no pedir `ok` por rutina: bastan estado verificable y resultado. `ok en OpenCode` exclusivamente con SFH1 `dst=OC` pendiente y packet autorizado; `ok aquí` solo con `dst=GPT` pendiente. ChatGPT conserva autonomía de priorizar y preparar packets para requisitos ya aprobados, sin pedir aprobación administrativa. Ante nueva decisión material, formular solo la pregunta concreta con recomendación. Nunca afirmar CI/VERIFIED sin comprobarlo. Se permite ampliar la respuesta **solo si el propietario lo solicita**, si debe decidir algo importante o si existe un riesgo/bloqueo que necesita explicación.

11. **AVISO DE CAMBIO DE SESIÓN — propietario 2026-11-17.** Comprobar periódicamente si la conversación acumula mucho contexto, referencias contradictorias, repeticiones o dificultad para mantener decisiones y SHA. Si una sesión nueva mejorará fiabilidad, **avisar explícitamente al propietario antes de pedir el próximo `ok`**, preferentemente en un handoff limpio: asegurar que `docs/work/CURRENT.md` y este archivo estén actualizados con HEAD, issue SFH1 pendiente, feature y estado CI; indicar acción concreta «Abre un nuevo chat en este proyecto y escribe `Continuar StockFlash desde docs/work/CURRENT.md`». No fingir un medidor exacto de tokens, no forzar sesiones nuevas por rutina y no prometer avisos mientras el usuario no esté interactuando.


12. **REVISIÓN VISUAL POR HITOS — propietario 2026-11-17.** ChatGPT decide de manera autónoma cuándo es útil que el propietario pruebe visualmente **un conjunto funcional de características o una pantalla ya integrada**, no por cada subtarea. Solicitarlo solo cuando el entorno sea realmente accesible y el estado técnico lo justifique; **nunca autoriza automáticamente deploy ni migración/uso de BD remota**. Indicar en 2–4 puntos breves la URL/entorno real, acción a probar y resultado observable; si aún no existe entorno accesible, registrar pendiente para predespliegue y seguir la secuencia autónoma. No presentar un resultado visual como probado si no se ejecutó.

13. **NO INICIAR OTRA FEATURE CON CI BASE ROJA — reparación preventiva 2026-11-17.** Cuando ChatGPT publica una edición propia de `public/docs/index.html` o `resources/roadmap/index.html`, debe comprobar el efecto sobre `tests/Feature/Docs/DocsIntegrationTest.php` y demás pruebas de documentación (no dejar cantidades fijas obsoletas), obtener CI GitHub **2/2 SUCCESS sobre el nuevo HEAD** y **solo entonces** activar/hacer handoff de otra feature. Si la CI falla por edición editorial propia, ChatGPT identifica y corrige el desacople, verifica el nuevo SHA y emite el siguiente SFH1; no delega al propietario un bloqueo técnico. No alterar un indicador verdadero solo para satisfacer una prueba desactualizada. Si GitHub no permite observar la CI inmediatamente, registrar PENDING y condicionar el handoff a su confirmación; nunca afirmar verde sin comprobación.

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

**Guía CATPROD-001 y SETUP-001 (26 temas, 2026-11-17):** `public/docs/index.html` actualizado en `b3938efd151444fed9aaed869e3cb3f696521453`, sección `#catalogo-productos`, tras QA y CI de código `c8e1338`. Sin despliegue; visual real pendiente. Se añadió `#setup-inicial` en `e09069c1179bc400495a6bb896fd557913b32ce4`; el registro técnico `GUIA-HTML-ESTADO.md` debe sincronizarlo OpenCode, sin editar HTML.

## 4B. ROADMAP HTML DEL PROPIETARIO — AUTORÍA EXCLUSIVA CHATGPT

**Decisión expresa (2026-11-10):** ChatGPT redacta y mantiene por sí solo la página visual de progreso y sus estados. Archivo canónico: `resources/roadmap/index.html`, fuera del directorio público. URL prevista tras integración: **`/roadmap`**, protegida para usuario autenticado con `settings.manage` (Admin Central).

**Fuentes de avance:** `docs/harness/ROADMAP-STOCKFLASH.md`, `docs/work/CURRENT.md`, Feature Packets, QA/gates y CI verificadas. Refleja R0–R10, capacidades realmente terminadas, pendientes para comercializar y versiones posteriores. No asigna fechas ni porcentajes arbitrarios.

**Estado editorial vigente (2026-11-17):** `resources/roadmap/index.html` ahora incluye CATPROD-001 y SETUP-001 VERIFIED técnico, commit `c6fa1fce028968ab3ba137f83a20d9ac4705ea5d`. R4 existencias/kardex/traspasos pendientes, R7 primera comercial POS+Bodega sin DTE integrado; recuperación de secretos BKP-08 e instalador CORE-17 pendientes. Bitácora técnica `ROADMAP-HTML-ESTADO.md` la sincroniza OpenCode; sin porcentajes ni plazos prometidos.

**Continuidad:** `docs/harness/ROADMAP-HTML-PROYECTO.md` contiene reglas obligatorias de autoría; `docs/work/ROADMAP-HTML-ESTADO.md` es una bitácora técnica que OpenCode puede modificar (no el HTML). Tras cada feature relevante OpenCode informa `ROADMAP_PENDIENTE_CHATGPT` con evidencia; el propietario lo traslada a ChatGPT; ChatGPT revisa y redacta actualización si corresponde. La nueva sesión de ChatGPT debe comprobar ambos archivos antes de intervenir.

**Nueva norma de ahorro de respuestas (2026-11-17):** ChatGPT responde normalmente en 1–3 líneas; OpenCode responde `OK` por rutina, pero guarda evidencia completa en GitHub. ChatGPT decide por hitos cuándo solicitar revisión visual al usuario; sin entorno publicado no inventa accesibilidad.\n
## 5. TRABAJO ABIERTO Y SIGUIENTE PASO

1. USERS-001 y HCTX-001: VERIFIED técnico, informes de cierre publicados el 2026-11-16; sin despliegue. Concurrencia MariaDB real y UX visual quedan explícitamente pendientes para predespliegue.
2. **SETUP-001 VERIFIED técnico** (2026-11-17): código `13cdd6a`, gates 19/19, 529 PHP/4665, QA independiente APROBADO, CI SHA 2/2 SUCCESS, cierre `docs/work/SETUP-001-CLOSURE-20261117.md`; guía 26 temas y roadmap actualizados por ChatGPT. CATPROD-001 también VERIFIED técnico. Riesgos pendientes: BKP-08 bloquea producción no demo, `/install` CORE-17 no implementado, MariaDB y navegador real no probados. CATPROD R2 N-01 debe resolverse antes de POS/Fiscal. NO desplegar ni tocar DB remota. El siguiente trabajo aprobado bajo delegación es INSTALL-001 CORE-17 P0 con packet propio; ejecución local de código/tests solamente y base CI verde obligatoria.
3. **Siguiente paso real:** en issue SFH1 nuevo de INSTALL-001, OpenCode verifica primero CI base GitHub 2/2 SUCCESS del SHA indicado, hace `safe_git_pull.py` conservando untracked, después implementa packet aprobado sin acciones reales de BD/hosting. Devolver gates/QA/CI al completar; ChatGPT realiza revisión VERIFIED y guía/roadmap. Si falla CI, informar bloqueo y corregir antes de implementar.
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
