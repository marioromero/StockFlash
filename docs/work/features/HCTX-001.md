# HCTX-001 — Contexto selectivo sin sobreingeniería

status: APPROVED
owner_approval: Autorización expresa del propietario en conversación StockFlash el 2026-11-13 para refinar la lectura selectiva del harness y su actualización incremental, sin crear nuevos protocolos ni agregar trabajo/tokens de mantenimiento.
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
active: docs/work/CURRENT.md
type: mantenimiento acotado del harness; no funcionalidad comercial

## Objetivo y principio

Reducir el contexto que OpenCode lee en cada feature, sin rediseñar el harness. **El mejor mantenimiento adicional es el que NO se ejecuta.** Sin trabajo en background, análisis global recurrente, resumen per-turn, ni promesas de ahorro medido sin evidencia.

## Cambios mínimos autorizados

1. **Ruta de lectura:** afinar únicamente el mapa existente `docs/harness/MAPA-DE-LECTURA.md` y, solo si es imprescindible, una indicación corta en `.opencode/agents/stockflash-implementer.md`. Inicio: leer `AGENTS.md` + estado ACTIVO resumido en `CURRENT.md` + packet de la feature; resolver IDs con `INDEX-REQUISITOS.md`; abrir **secciones de contrato y archivos de código pertinentes**, usando `grep/glob`/búsqueda de símbolos. No leer las 3 partes v2.2, historial, reportes anteriores, UI de otros módulos o `CONTEXTO-CHATGPT.md` completo por defecto. Expandir solo ante dependencia real, cambio transversal o ambigüedad.
2. **CURRENT corto:** depurar `docs/work/CURRENT.md` a estado operativo acotado (objetivo aprox. 2–4 KB): feature activa, bloqueos, dependencias, estados VERIFIED compactos y enlaces a cierre/packet/evidencia. **Preservar** decisiones, restricciones y referencias necesarias a gates/harness, sin copiar largos históricos; mantenerlos en los informes/cierres **ya existentes** y en Git. Comprobar referencias de pruebas que dependen de texto literal antes de cambiarlo.
3. **Autoperfeccionamiento pasivo:** en el cierre ORDINARIO de una feature, actualizar el mapa **solo si aparece un nuevo patrón de lectura realmente reutilizable**; actualizar CURRENT solo cuando cambien estado/decisión/dependencia. Cambios mínimos por diff, no reconstruir índices. Si no hay información nueva útil, **NO editar nada**. Nunca ejecutar una rutina o un agente separado para optimizar contexto. La búsqueda puntual en código sigue siendo la fuente principal de dependencias; no crear inventario de archivos por módulo que se vuelva obsoleto.
4. **Eliminar contradicciones ya conocidas** en `LEEME-PRIMERO.md` (scaffold Laravel antiguo) y `docs/harness/WORKFLOW-SDD-HIBRIDO.md` (permisos `ask`, handoff con copia), mediante correcciones puntuales. No actualizar fechas o documentación histórica restante por estética.
5. **Pruebas existentes:** permitir una entrada mínima `HCTX-001` en `GATES_BY_PACKET` de `scripts/sf_verify.py` reutilizando `check_contracts.py` y tests existentes, sin crear runners, herramientas ni scripts. QA independiente + publisher habitual, GitHub Actions por SHA exacto.

## Límites estrictos

**No crear** nuevos agentes, skills, MCP, automatizaciones, plugins, caches/Engram obligatorios, archivos de índice o mapa adicional, catálogos de dependencia, trackers de tokens, log de lecturas, presupuestos de tokens, compactación programada, dashboards, formatos, fases/gates nuevos ni protocolos. No dividir todavía `sf_verify.py` por feature: hacerlo solo si surge necesidad medida más adelante. No modificar código de negocio, migraciones, base de datos, `AGENTS.md` protegido, `docs/product/*`, Constitución, `docs/harness/CONTEXTO-CHATGPT.md` (autoría exclusiva GPT), manual/roadmap HTML, secret files ni `opencode.json`. Sin deploy.

## Aceptación

- Lectura por defecto localiza packet + IDs + código afectado sin exigir documentos/escaneos globales; escalamiento explícito por dependencia.
- `CURRENT.md` conserva los estados y decisiones del proyecto y las referencias críticas, sin históricos redundantes.
- Autorefino **por oportunidad**: cero archivos modificados cuando no cambia nada útil; trabajo incremental piggyback de cierre existente.
- QA independiente comprueba ausencia de nueva infraestructura/burocracia y continuidad de reglas. Gates y GitHub Actions SUCCESS; si falla, corregir en alcance y republicar (máximo tres rondas).
- Informe SFH1 compacto con SHA, gate, QA, CI. **No declarar VERIFIED** por cuenta propia.

## Exclusiones

No alterar USERS-001 ni su condición actual (publicada y pendiente decisión VERIFIED); no inventar nuevos requisitos ni afectar continuidad entre ChatGPT/OpenCode. Se mantiene aprobación única AUTO_GIT y sincronización Git segura.
