# StockFlash — CONTEXTO PARA CHATGPT (continuidad entre sesiones)

**Última revisión por ChatGPT:** 2026-11-10
**Repositorio:** `marioromero/StockFlash` · rama de desarrollo `main`
**Estado de referencia observado:** `3735c1473b5494ce0840acfc3fa8de6cdc82b245` (ROADMAP-001 VERIFIED, cierre documental publicado; CI remota comprobada)
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
9. Revisar también el roadmap HTML del propietario (`resources/roadmap/index.html`) y su protocolo. Cuando los hitos reales, las funcionalidades listas o el camino a R7 cambien, actualizarlo **solo desde ChatGPT**, sin plazos ni porcentajes inventados. OpenCode no edita ese archivo.

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
- `DOCS-001`: **VERIFIED** (decisión expresa del propietario, 2026-11-10). Código publicado `90254e5`, cierre documental `3fde691`, gates `GATES_PASS`, QA independiente `APROBADO` y CI PHP/Node/smoke + contratos SUCCESS ([42908696754](https://github.com/marioromero/StockFlash/actions/runs/42908696754), [42908696677](https://github.com/marioromero/StockFlash/actions/runs/42908696677)). Manual /docs/, enlace Ayuda y continuidad ChatGPT integrados.
- `ROADMAP-001`: **VERIFIED** (decisión externa del propietario y cierre documental `docs/work/ROADMAP-001-CLOSURE-20261110.md` publicado en `main`). Implementación `a9c19d415b0a6fff0c16294c5dca9bd84e37cb41`; integración selectiva editorial del PR #4 y cierre documental presentes en el snapshot `3735c147`. Gate definitivo `ROADMAP-001-VERIFY-20261110-194620.md` = GATES_PASS (RM1–RM15), QA definitivo `ROADMAP-001-DOCS-REVIEW-20261110-2.md` = APROBADO (0 bloqueantes). **CI remota comprobada por ChatGPT** para `3735c147`: [PHP/Node/smoke SUCCESS](https://github.com/marioromero/StockFlash/actions/runs/42918708678), [contratos SUCCESS](https://github.com/marioromero/StockFlash/actions/runs/42918708636). `/roadmap` requiere `settings.manage`; botón del Dashboard, hitos R0–R10, bitácora y alertas `ROADMAP_*` operativos.
- `USERS-001`: **DRAFT, NO implementado**, revisado y QA documental APROBADO. Último snapshot `f67a077`.  Detalla CRUD usuarios, rol global/por sucursal, PIN conforme `system_configs.pin_length`, `SoftDeletes`, límites de usuarios activos, protección último administrador, invalidación de sesiones por cambio de accesos y auditoría. Contiene **16 decisiones propuestas**. **Requiere aprobación explícita del propietario** para pasar a APPROVED e implementar; no deducirla de este documento.
- Sin despliegue nuevo ni migraciones remotas autorizadas en este snapshot.

## 4. MANUAL HTML — AUTORÍA 100 % CHATGPT

**Decisión del propietario:** ChatGPT redacta el 100 % de títulos, explicaciones, pasos, definiciones, preguntas frecuentes y ejemplos de la documentación de usuario; OpenCode no puede crear ni reescribir esos textos. OpenCode solo integra, automatiza seguimiento, prueba UI/CSS/JS, añade enlace Ayuda y publica.

**Nueva referencia canónica:** `public/docs/index.html` (URL `/docs/`). Primera versión redactada por ChatGPT: 14 temas de AUTH-001 y BRANCH-001, menú lateral, búsqueda, acordeones y diseño responsive.

**Estado de integración al 2026-11-10:** la guía inicial y el protocolo se integraron selectivamente en `main` mediante DOCS-001, commit `90254e5`. El PR #2 fue la fuente original; **no debe fusionarse**, porque su rama antecede a revisiones de USERS-001 y podría introducir regresiones. OpenCode integró los archivos ChatGPT byte a byte, con bitácora técnica actualizada. Enlace histórico: https://github.com/marioromero/StockFlash/pull/2

**Reglas persistentes integradas en main:** `docs/harness/GUIA-HTML-USUARIO.md`, `docs/work/GUIA-HTML-ESTADO.md`, `AGENTS.md`, `.opencode/commands/sf-run.md`, `sf-feature.md`, `sf-review.md`, `sf-publish.md` y `sf-handoff.md`.

**Mecánica:** cuando OC implemente una función visible nueva, debe emitir `GUIA_PENDIENTE_CHATGPT` con SHA, UI y evidencia. El propietario entrega ese resumen a ChatGPT; ChatGPT revisa código y escribe contenido. OC integra y verifica. Una nueva sesión retoma desde este archivo y la bitácora.

**Estado editorial:** manual `/docs/`, contexto y roadmap preparados en PR #3 y luego la explicación del botón «Avance del proyecto» preparada en PR #4 **ya están integrados selectivamente en `main`** al corte `3735c147`. PR #3 y PR #4 fueron cerrados sin fusionar para evitar cambios antiguos; no volver a aplicarlos. **Pendiente exclusivamente editorial de ChatGPT:** actualizar en el roadmap y este contexto el estado ROADMAP-001 a VERIFIED y el gate a `194620`; revisión preparada en rama `docs/chatgpt-cierra-roadmap001-20261110` posterior a ese corte, cuyo PR/integración debe comprobarse en GitHub.

**Límite:** no crear guía de USERS-001 como funcionalidad existente mientras esté DRAFT.

## 4B. ROADMAP HTML DEL PROPIETARIO — AUTORÍA EXCLUSIVA CHATGPT

**Decisión expresa (2026-11-10):** ChatGPT redacta y mantiene por sí solo la página visual de progreso y sus estados. Archivo canónico: `resources/roadmap/index.html`, fuera del directorio público. URL prevista tras integración: **`/roadmap`**, protegida para usuario autenticado con `settings.manage` (Admin Central).

**Fuentes de avance:** `docs/harness/ROADMAP-STOCKFLASH.md`, `docs/work/CURRENT.md`, Feature Packets, QA/gates y CI verificadas. Refleja R0–R10, capacidades realmente terminadas, pendientes para comercializar y versiones posteriores. No asigna fechas ni porcentajes arbitrarios.

**Estado actual al corte 2026-11-10:** roadmap HTML integrado en `main` desde PR #3 y PR #4 de forma selectiva; código/cierre verificados hasta `3735c147`. Ruta `/roadmap` activa solo para administradores; pruebas de acceso, QA, gates y CI remota confirmadas. ROADMAP-001 ya fue declarado y registrado VERIFIED; ChatGPT preparó una última actualización editorial en `docs/chatgpt-cierra-roadmap001-20261110` que necesita integración por OpenCode. Etapa actual R3 Core; BOOT/CI/AUTH/BRANCH/DOCS/ROADMAP verificados; USERS-001 sigue DRAFT sin implementar. **Primera versión comercializable:** R7 (POS + Bodega sin DTE integrado), después de R4 Bodega, R5 Control comercial/licencias y R6 POS, más controles R7. Posteriores: R8 SyncWP, R9 Fiscal, R10 Compras/Analítica.

**Continuidad:** `docs/harness/ROADMAP-HTML-PROYECTO.md` contiene reglas obligatorias de autoría; `docs/work/ROADMAP-HTML-ESTADO.md` es una bitácora técnica que OpenCode puede modificar (no el HTML). Tras cada feature relevante OpenCode informa `ROADMAP_PENDIENTE_CHATGPT` con evidencia; el propietario lo traslada a ChatGPT; ChatGPT revisa y redacta actualización si corresponde. La nueva sesión de ChatGPT debe comprobar ambos archivos antes de intervenir.

## 5. TRABAJO ABIERTO Y SIGUIENTE PASO

1. **Actualizar contenido ChatGPT:** integrar la revisión de `resources/roadmap/index.html` y de este archivo desde `docs/chatgpt-cierra-roadmap001-20261110` (buscar su PR abierto). Corrige el estado ROADMAP-001 a VERIFIED, el gate vigente `194620` y la referencia `3735c147`. OpenCode solo integra, hace QA/gates y publica; actualiza su bitácora técnica para `ROADMAP_ACTUALIZADO` cuando esté confirmado.
2. **USERS-001:** QA documental aprobado y packet DRAFT pendiente de aprobación expresa del propietario de las 16 decisiones. No iniciar implementación sin esta autorización. Conservar las observaciones no bloqueantes en la preparación de implementación: `VerifySession` cubriendo rutas autenticadas, lectura de PII PRV-06/CORE-10 y precisión editorial.
3. Continuar verificando GitHub en cada cierre de OpenCode y actualizar este contexto, la guía y el roadmap según los cambios reales. No hay cron ni conexión automática entre agentes.

## 6. FORMATO BREVE DE ACTUALIZACIÓN DE ESTE ARCHIVO

En cada cierre revisado de OC, ChatGPT debe:
- Actualizar «Última revisión», «Estado de referencia observado» y estados afectados en §3.
- Registrar feature ID, SHA, QA, gates y CI que se hayan verificado, y lo que siga pendiente.
- Actualizar §4 con cobertura del manual, PR/commit correspondiente, sin afirmar integración no verificada.
- Actualizar §4B con cobertura y cambios del roadmap HTML, diferenciando DRAFT, VERIFIED y versión comercial; sin fechas ni porcentajes inventados.
- Sustituir el siguiente paso de §5 y mantener un único estado actual, evitando que la sección se vuelva una acumulación de logs.
- No insertar secretos ni información personal.

**Instrucción mínima para el propietario al cambiar de chat:** «Continúa StockFlash. Lee `docs/harness/CONTEXTO-CHATGPT.md` del repositorio `marioromero/StockFlash`, revisa el HEAD y el último cierre de OpenCode».
