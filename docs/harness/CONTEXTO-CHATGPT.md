# StockFlash — CONTEXTO PARA CHATGPT (continuidad entre sesiones)

**Última revisión por ChatGPT:** 2026-11-10
**Repositorio:** `marioromero/StockFlash` · rama de desarrollo `main`
**Estado de referencia observado:** `90254e59bf61bc0252a579c076886fc500eb0119` (DOCS-001 publicado; revisión de CI confirmada)
**Aviso de vigencia:** este SHA es un punto de control histórico, NO el HEAD garantizado al abrir una nueva sesión. Verificar HEAD, PR abiertos y documentación actual antes de actuar.

## 1. INSTRUCCIONES PARA CUALQUIER SESIÓN NUEVA DE CHATGPT

Si el propietario solicita «continuar StockFlash», «revisar lo que hizo OpenCode» o «seguir el manual»:

1. Leer este archivo en GitHub, luego `AGENTS.md`, `docs/work/CURRENT.md`, `docs/harness/GUIA-HTML-USUARIO.md` y `docs/work/GUIA-HTML-ESTADO.md` (si están integrados).
2. Consultar HEAD de `main`, últimas Feature Packets, QA/gates, GitHub Actions y código relevante. El estado de este archivo puede estar desactualizado frente a un commit nuevo.
3. Contrastar resultados de OpenCode con evidencia real, sin dar por verdes CI/QA no comprobados.
4. Tomar decisiones de alcance, aprobar propuestas técnicamente fundadas cuando el propietario autorice, y preparar mensajes listos para OpenCode. Nunca atribuir al propietario una autorización que no haya dado.
5. **Actualizar ESTE archivo en GitHub cuando una revisión cambie materialmente el estado del proyecto, los compromisos o los próximos pasos**, conservando el histórico de hitos con referencia SHA. Autoría y mantenimiento de este archivo: **ChatGPT**. OpenCode lo lee sin editarlo.
6. Si la revisión incluye una funcionalidad visible nueva o modificada, actualizar como autor exclusivo el contenido del manual `public/docs/index.html`, o preparar una rama/PR con la modificación hasta que el harness la integre. Comprobar nombres de campos y pantallas reales, no inventarlos.
7. Para cambios en el contexto, preferir un commit documental acotado solo a este archivo, con control del SHA previo y sin sobrescribir trabajo paralelo. Si el flujo de Git disponible o el protocolo vigente exige PR, usar PR y dejar identificada la revisión pendiente.
8. No hay comunicación automática entre OpenCode y este ChatGPT. El propietario inicia cada revisión pegando el cierre de OC, y puede abrir otra sesión con la instrucción «Lee el contexto de StockFlash en GitHub y continúa». No prometer trabajos sin una nueva interacción.

**Idea central:** este archivo es un índice de continuidad y decisiones, no sustituye la fuente de verdad (el repositorio, Feature Packets y código).

## 2. PROPÓSITO, STACK Y REGLAS INVARIANTES

**Producto:** StockFlash, plataforma comercial modular para Chile. Arquitectura Laravel 13 + Vue 3 + Inertia 3 + TypeScript/Vite. La documentación y textos UX usan español de Chile.

**Módulos comerciales:** POS, Bodega, SyncWP, Fiscal, Compras, Analítica. Control es administración interna.

**Primer lanzamiento:** POS + Inventario sin emisión DTE integrada; referencia fiscal externa cuando proceda. Planes iniciales y demo/gratis quedan regidos por contratos v2.2, no inventar reglas pendientes.

**Contratos prevalentes:** `AGENTS.md`; `docs/governance/CONSTITUTION.md`; archivos `docs/product/especificacion-v2.2-*-CONSOLIDADO-EN-REVISION.md`; `docs/work/features/*.md`.

**Flujo:** owner+ChatGPT deciden alcance → OpenCode implementa → gates GATES_PASS → QA independiente APROBADO → publisher Git → CI en GitHub → owner+ChatGPT deciden VERIFIED → OpenCode registra cierre. Publicar no equivale a VERIFIED. No migrar BD remota ni desplegar sin autorización expresa. Mantener los permisos amplios de OpenCode según decisión del propietario; no proponer reducciones rutinarias.

## 3. HITOS VALIDOS EN LA FECHA DE ESTE SNAPSHOT

- `BOOT-001`: VERIFIED. Base Laravel/Vue/Inertia/TypeScript.
- `AUTH-001`: VERIFIED, código `55b7f16`, cierre `79f64b6`. Login, sesiones, cambio de contraseña, RBAC.
- `BRANCH-001`: VERIFIED, código consolidado `4f81457`, cierre `55086ed`. CRUD de sucursales, permisos, auditoría transaccional. CI PHP/Node/smoke/contratos confirmada en GitHub para `4f81457`.
- `DOCS-001`: **APPROVED, implementado y publicado; pendiente de decisión VERIFIED del propietario**. Código y harness integrados en `90254e5` el 2026-11-10; gates `DOCS-001-VERIFY-20261110-184136.md` = GATES_PASS, QA `DOCS-001-REVIEW-20261110.md` = APROBADO, suite local 106 pruebas / 528 aserciones. **CI remota verificada por ChatGPT** en el SHA `90254e5`: PHP/Node/smoke SUCCESS (run `42908696754`), contratos SUCCESS (run `42908696677`). Ruta `/docs/`, Dashboard «Ayuda», reglas del harness y bitácora integrados. ChatGPT recomienda declarar VERIFIED, pero no atribuye la decisión al propietario hasta su confirmación.
- `USERS-001`: **DRAFT, NO implementado**, revisado y QA documental APROBADO. Último snapshot `f67a077`.  Detalla CRUD usuarios, rol global/por sucursal, PIN conforme `system_configs.pin_length`, `SoftDeletes`, límites de usuarios activos, protección último administrador, invalidación de sesiones por cambio de accesos y auditoría. Contiene **16 decisiones propuestas**. **Requiere aprobación explícita del propietario** para pasar a APPROVED e implementar; no deducirla de este documento.
- Sin despliegue nuevo ni migraciones remotas autorizadas en este snapshot.

## 4. MANUAL HTML — AUTORÍA 100 % CHATGPT

**Decisión del propietario:** ChatGPT redacta el 100 % de títulos, explicaciones, pasos, definiciones, preguntas frecuentes y ejemplos de la documentación de usuario; OpenCode no puede crear ni reescribir esos textos. OpenCode solo integra, automatiza seguimiento, prueba UI/CSS/JS, añade enlace Ayuda y publica.

**Nueva referencia canónica:** `public/docs/index.html` (URL `/docs/`). Primera versión redactada por ChatGPT: 14 temas de AUTH-001 y BRANCH-001, menú lateral, búsqueda, acordeones y diseño responsive.

**Estado de integración al 2026-11-10:** la guía inicial y el protocolo se integraron selectivamente en `main` mediante DOCS-001, commit `90254e5`. El PR #2 fue la fuente original; **no debe fusionarse**, porque su rama antecede a revisiones de USERS-001 y podría introducir regresiones. OpenCode integró los archivos ChatGPT byte a byte, con bitácora técnica actualizada. Enlace histórico: https://github.com/marioromero/StockFlash/pull/2

**Reglas persistentes en el PR:** `docs/harness/GUIA-HTML-USUARIO.md`, `docs/work/GUIA-HTML-ESTADO.md`, `AGENTS.md`, `.opencode/commands/sf-run.md`, `sf-feature.md`, `sf-review.md`, `sf-publish.md` y `sf-handoff.md`.

**Mecánica:** cuando OC implemente una función visible nueva, debe emitir `GUIA_PENDIENTE_CHATGPT` con SHA, UI y evidencia. El propietario entrega ese resumen a ChatGPT; ChatGPT revisa código y escribe contenido. OC integra y verifica. Una nueva sesión retoma desde este archivo y la bitácora.

**Actualización editorial pendiente de integrar:** ChatGPT preparó una revisión de `/docs/` explicando el nuevo botón «Ayuda» del Dashboard, y este archivo de continuidad, en **PR #3** (`docs/chatgpt-actualiza-docs001-20261110`) a partir de `90254e5`: https://github.com/marioromero/StockFlash/pull/3. Verificar si ya está fusionado al consultar GitHub; nunca afirmar que está en `main` antes de comprobarlo.

**Límite:** no crear guía de USERS-001 como funcionalidad existente mientras esté DRAFT.

## 5. TRABAJO ABIERTO Y SIGUIENTE PASO

1. **DOCS-001:** código publicado en `90254e5` y CI verificada en GitHub; queda pendiente solo la **declaración externa VERIFIED** del propietario y su registro documental mediante operación B. ChatGPT ya verificó gates, QA y CI.
2. **Actualizar guía y contexto por ChatGPT:** integrar la revisión documental redactada por ChatGPT (botón Ayuda y este contexto) desde PR separado, utilizando gates/QA pertinentes. No dejar que OpenCode reescriba explicación de usuario ni este contexto.
3. **USERS-001:** QA documental aprobado, DRAFT pendiente de aprobación expresa del propietario de las 16 decisiones. No comenzar su implementación por inercia.
4. En cada nuevo cierre de OpenCode, ChatGPT revisa evidencia actual y, en la misma interacción, mantiene este contexto y el HTML de guía cuando corresponda.

## 6. FORMATO BREVE DE ACTUALIZACIÓN DE ESTE ARCHIVO

En cada cierre revisado de OC, ChatGPT debe:
- Actualizar «Última revisión», «Estado de referencia observado» y estados afectados en §3.
- Registrar feature ID, SHA, QA, gates y CI que se hayan verificado, y lo que siga pendiente.
- Actualizar §4 con cobertura del manual, PR/commit correspondiente, sin afirmar integración no verificada.
- Sustituir el siguiente paso de §5 y mantener un único estado actual, evitando que la sección se vuelva una acumulación de logs.
- No insertar secretos ni información personal.

**Instrucción mínima para el propietario al cambiar de chat:** «Continúa StockFlash. Lee `docs/harness/CONTEXTO-CHATGPT.md` del repositorio `marioromero/StockFlash`, revisa el HEAD y el último cierre de OpenCode».
