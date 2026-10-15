# Revisión del protocolo maestro Claude v2 — aplicación selectiva en StockFlash
Fecha: 2026-10-15
Estado: DIAGNÓSTICO ADOPTADO / CAMBIOS FUNCIONALES AL HARNESS NO AUTORIZADOS
Base comparada: `PROTOCOLO_MAESTRO_HARNESS_HIBRIDO_v2.md` facilitado por el propietario y comentarios de Claude. Este archivo no sustituye el protocolo genérico original ni el contrato v2.2 de StockFlash.

## Veredicto
La versión 2 mejora claramente la pedagogía operativa: fases 0–10, perfiles, evidencias, decisiones A/B/C, olas, reportes y traspaso. **No se trasplanta entera a StockFlash**: estamos en proyecto FULL con constitución, contratos, Feature Packets y CI reales. Renombrar todo o declarar `SPEC_READY` sin resolver ADR sería regresión.

## Decisiones de adopción
| Claude v2 | StockFlash | Acción |
|---|---|---|
| LITE / STANDARD / FULL (§8) | **Aplica para proyectos nuevos**, StockFlash es FULL por alcance, datos y duración. | NO rehacer sus documentos ni migrar nombres por perfil. |
| Etapas 0–10 y spec-first | Muy útil como checklist de requisitos; StockFlash tiene v2.2 consolidado **en revisión**, ADR-01..06 abiertos. | Usar al cerrar ADR y al diseñar R3; no declarar `SPEC_READY` global todavía. |
| Decisiones Clase A/B/C + Decision Sweep (§4) | **Adoptar**. | `docs/work/DECISIONS-LOG.md` creado; antes de una Feature Packet revisar dudas que forzarían a OpenCode a pedir decisiones. |
| RUN-REPORT + ledger (§16) | **Adoptar como evolución técnica**. | Contrato definido en `docs/harness/RUN-REPORT-CONTRACT.md`; no implementado. Requiere script, pruebas y E2E DV-007. |
| Olas `/queue-run` (§18) | **Interesante, no listo**: el coordinador falló al invocarse automáticamente y CI-001 fue coordinado manualmente. | Primero DV-001 (smoke de delegación). Después decisión de autorizar olas y cola; no instalar `/queue-run` todavía. |
| Auto `VERIFIED` no visual (§12) | **No adoptar por defecto**: al publicar código no se aprueban reglas de caja, datos, impuestos ni requisitos comerciales. | Mantener VERIFIED externo salvo autorización explícita posterior para packets administrativos acotados. |
| Permisos totalmente abiertos + acceso externo + `doom_loop:allow` (§14.3) | **No adoptar** como copia; podría abrir archivos ajenos al repo y anular separación de roles. `--auto` ya cubre `ask`. | Mantener permisos actuales hasta prueba E2E y diagnóstico de bloqueo concreto; cambio sensible requiere decisión propia. |
| Eliminar seguridad y secret scan, posponer todo al release (§7) | **No adoptar** para POS que tratará datos de clientes, pagos y usuarios. | Mantener diseño de RBAC, privacidad, minimización y secretos desde el principio; hardening de despliegue puede tener puerta posterior. |
| Test rojo primero por cada AC (§15) | **Adoptar de forma proporcional**. | Mapear AC → pruebas verificables. Red-first recomendado para lógica, permisos e invariantes; no fingir tests automatizados para UX visual o investigación. |
| Modelos por agente (`opencode models`) (§14.4) | **Pendiente de evidencia del entorno local**. | DV-002: investigar catálogo realmente disponible, presupuesto y versión; no fijar modelos ni hacer upgrades sin comprobar. |
| Versionado `HARNESS-VERSION` (§24) | **Buena idea**. | Crear cuando el usuario proporcione `opencode --version` y versión de configuración efectivamente cargada; no inventar. |
| Investigación de stack (`STACK-GUIDE`) | **Adoptar por hito**. | Preparar guía Laravel/PHP/Node/cPanel con versiones ejecutadas, dependencias, CI y límites reales; no repetir explicaciones que ya figuran en paquetes. |
| Validaciones diferidas (§6) | **Adoptar condicionadamente**. | Tabla real en `docs/quality/DEFERRED-VALIDATIONS.md`. No aplazar validaciones críticas que condicionan stack o seguridad de datos. |
| Traspaso de chat (§21) | **Adoptar inmediatamente**. | `docs/work/handoffs/HANDOFF-2026-10-15.md`; incluye HEAD de referencia y próximo bloque real. |
| Congelar harness tras comprobarlo (§20) | **Adoptar**. | Solo cambios por defecto demostrado o ahorro medible; no seguir sumando agentes sin necesidad. |

## Revisión de versión OpenCode
- El `opencode.json` existente de StockFlash usa **sintaxis V1**: `agent`, `permission`, `bash`, `task`. La existencia de docs V2 **no demuestra que el cliente local ejecute V2**.
- Fuentes oficiales consultadas 2026-10-15: https://opencode.ai/docs/agents/ y https://opencode.ai/v2/docs/permissions y https://opencode.ai/v2/docs/agents.
- V2 usa `agents`, `permissions`, `shell`, `subagent`; **NO mezclar** ambos estilos en un mismo parche. Antes de migrar: versión instalada, esquema válido, evaluación de permisos reales, pruebas de delegación; documento de reversión.
- `subagent_depth: 2` aparece en configuración V1 oficial, pero que esté guardado en JSON no prueba que el IDE lo aplique en la sesión concreta. Hay evidencia de coordinación manual, no E2E.

## Alcance de esta incorporación
Sólo documentación de decisiones, validaciones, formato de reporte futuro y handoff. Ningún cambio a `AGENTS.md`, `CONSTITUTION.md`, `opencode.json`, scripts, modelos, workflow CI, BD, staging, agentes o contratos v2.2. **La próxima conversación debe centrarse en STAGING-001 y ADR de R3, no en rediseñar indefinidamente el harness.**
