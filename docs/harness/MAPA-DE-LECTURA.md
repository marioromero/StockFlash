# Mapa de lectura v1.2 — baja carga de contexto

## Lectura por defecto
1. `AGENTS.md` + estado ACTIVO resumido de `docs/work/CURRENT.md` + Feature Packet específico (`docs/work/features/<ID>.md`).
2. Resolver IDs y requisitos con `docs/requirements/INDEX-REQUISITOS.md`; usar `grep`/`glob` para abrir solo las secciones de contrato y los archivos de código pertinentes.
3. Skill de tarea bajo demanda. Reviewer examina feature + diff + pruebas, no toda la especificación.

## Lo que NO se lee por defecto
- Las 3 partes v2.2 completas (`docs/product/especificacion-v2.2-*-CONSOLIDADO-EN-REVISION.md`): abrir solo la sección necesaria.
- `docs/product/historico-v2.1/`: fuente histórica para diff/trazabilidad, NUNCA prevalece sobre v2.2 consolidada.
- Reportes previos de verificación/QA/cierres y `docs/harness/CONTEXTO-CHATGPT.md` completo: consultar solo si la tarea lo exige.
- `docs/product/StockFlash-Control-SPEC-v0.1.md` salvo features de Control.

## Cuándo expandir
- Existe una dependencia real entre módulos, un cambio transversal o una ambigüedad del contrato.
- Faltan IDs o criterios en `INDEX-REQUISITOS.md` y hay que confirmar el texto exacto.
- Se requiere revisar una decisión abierta en `docs/governance/DECISIONES-ABIERTAS-v2.2.md`; si sigue abierta, escalar y detener únicamente la parte que depende de ella.

La búsqueda puntual en código sigue siendo la fuente principal de dependencias; no reconstruir índices ni inventarios de archivos por módulo.

Mantenimiento pasivo: actualizar este mapa solo si durante el cierre normal de una feature aparece un patrón de lectura realmente reutilizable; si no hay información nueva útil, no editar nada.
