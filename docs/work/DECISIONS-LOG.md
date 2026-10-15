# StockFlash — Registro de decisiones y preguntas (2026-10-15)

**Propósito:** evitar volver a preguntar lo ya decidido y distinguir hechos, supuestos y preguntas pendientes. No sustituye `docs/governance/CONSTITUTION.md`, los ADR ni los contratos v2.2.

## Decisiones confirmadas
| ID | Estado | Decisión / fuente |
|---|---|---|
| DC-001 | CONFIRMADA | Modelo híbrido: propietario + ChatGPT deciden producto/arquitectura y OpenCode implementa únicamente Feature Packets APPROVED y activas. `docs/harness/WORKFLOW-SDD-HIBRIDO.md`. |
| DC-002 | CONFIRMADA | CI-001 VERIFIED por GitHub Actions: PHP 8.3, Node 24, Smoke y contrato verdes, SHA `218de3a`; el último estado documental está en CURRENT. |
| DC-003 | CONFIRMADA | Logos BRAND-001 VERIFIED **solo como prototipos SVG**, no se sustituyen por defecto los fallback/PNG oficiales. |
| DC-004 | CONFIRMADA | VERIFIED lo declara la decisión externa del propietario/ChatGPT; `GATES_PASS` o QA no lo conceden automáticamente. No cambiar este contrato a partir de una plantilla genérica. |
| DC-005 | CONFIRMADA | No iniciar funcionalidades R3 sin la petición previa del propietario; STAGING-001 aún es DRAFT. |
| DC-006 | CONFIRMADA | No rehacer harness ni partes documentales ya vigentes por adoptar un protocolo genérico: aplicar únicamente deltas justificados. |

## Preguntas y decisiones por resolver
| ID | Clase | Estado | Pregunta puntual | Cuándo decidir |
|---|---|---|---|---|
| DQ-001 | A | PENDIENTE | ¿Staging aislado en el hosting cPanel actual o alternativa separada? ¿Qué aislamiento realmente permite? | Antes de aprobar STAGING-001 y cualquier deploy. |
| DQ-002 | A | PENDIENTE | ADR-01..06 abiertos del contrato v2.2: revisar cada uno antes de su primera feature dependiente, no resolver por intuición. | Antes de los componentes que afecten licencia, fiscal, caja o contratos. |
| DQ-003 | B | PENDIENTE | ¿Se aprueba una ola/cola de Feature Packets para ejecución desatendida con un solo comando? | Después de demostrar `/sf-run` E2E sin delegación manual. |
| DQ-004 | B | PENDIENTE | Versión real instalada de OpenCode y modelos accesibles por agente; no inferir V1/V2 por el protocolo adjunto ni fijar modelos no disponibles. | Antes de modificar configuración de agentes o modelos. |
| DQ-005 | A | PENDIENTE | ¿Se autoriza la autoaceptación `VERIFIED` de tareas específicas y no críticas? | Solo con aprobación expresa del propietario y criterios por tipo de packet. El valor actual es **NO**. |
| DQ-006 | A | PENDIENTE | ¿Cuándo y bajo qué condiciones integrar SVG provisionales en UI en lugar de fallback textual? | Antes de una Feature Packet de integración de marca, no bloquea staging. |

## Convenciones de clasificación
- **A**: producto, datos, UX, integración, infraestructura, coste o aceptación: cerrar con propietario; si afecta contratos, registrar ADR.
- **B**: convenciones técnicas repetitivas: propuesta en bloque por arquitecto y aprobación; no microconfirmaciones.
- **C**: detalle interno del packet consistente con contratos: OpenCode elige, deja nota y continúa.
- Anotar `[ASSUMED]` únicamente para hipótesis no críticas con responsable y disparador de revisión. Las Class A bloqueantes no se convierten silenciosamente en supuestos.
- Cuando el usuario diga “no sé”, recomendar una opción provisional explicando el efecto y la reversibilidad; no equiparar silencio con aprobación.

**Regla de no duplicación:** decisiones nuevas vinculantes van a su fuente canónica/ADR. Este archivo mantiene enlaces y cuestiones, no reescribe la norma.
