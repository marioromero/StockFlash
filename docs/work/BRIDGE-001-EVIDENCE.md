# BRIDGE-001 — Evidencia parcial local (2026-11-12)

## Actualización vigente posterior al aprovisionamiento

Aislamiento real, publicación local negativa y 17 pruebas comprobados como
StockFlashAgent; ambas CLI arrancan, 8 pruebas de transporte nuevas PASS.
Adaptadores/mode piloto preparados, pero autenticación e integral LLM pendientes.
Ver `BRIDGE-001-AUTH-AND-ACCEPTANCE.md`; ese documento distingue interfaces listas
de sesiones/modelos realmente ejecutados. Los límites y fallos originales inferiores
son históricos. No conceden ni revocan por sí solos aceptación global o VERIFIED.

Base y HEAD al ejecutar las pruebas: `d103120f6e45dcb10a999c99f3d6f2faff5f466c`.
Rama: `pilot/codex-bridge-20261112`.
Autorización: propietario aprobó implementación local sin commits/publicación.

## Entrega documental posterior solicitada por el propietario

Publicar únicamente Markdown del avance en `pilot/codex-bridge-20261112` para
revisión en GitHub. Excepción puntual a la prohibición inicial y al publisher
limitado a main; commit selectivo por Codex y push sin force. Sin código ni pruebas
Python: la implementación descrita abajo permanece LOCAL y no es reproducible
solo desde esta entrega documental. No constituye GATES_PASS ni QA/VERIFIED.
El SHA base de ejecución se conserva como referencia histórica, mientras el
HEAD de la rama piloto avanzará por este commit documental. El validador actual
del puente seguirá bloqueando por HEAD distinto; no se modifica para sortearlo.

## Implementado

- `scripts/sf_bridge.py`: núcleo de transporte, validación cerrada de mensajes,
  manifiestos SHA-256, rutas sensibles rechazadas antes de leer, ledger SQLite
  externo, transacciones y eventos, exclusión persistente por proyecto,
  instrucciones idempotentes, resultados recibidos idempotentes, referencias de
  sesión y presupuesto de tres intentos persistido.
- Reconciliación conservadora distingue activo, interrumpido y resultado recibido;
  divergencia Git/packet bloquea. No vuelve a despachar ni aprueba QA.
- Patrón Git/argv reutilizado del handshake de Downloads; archivo original intacto.
  Sin llamadas shell arbitrarias ni subprocesos derivados de mensajes.
- `tests/bridge/test_bridge.py`: 17 pruebas locales, sin red ni modelos.
- Entrada BRIDGE-001 en `GATES_BY_PACKET`, usando el verificador actual.

## Ejecutado

`py -3 -B -m unittest discover -s tests/bridge -v`: 17 tests, OK.
`py -3 -B scripts/sf_bridge.py inspect`: piloto identificado, BLOCKED,
`agent_execution=false`, `publication=forbidden`.
`py -3 -B scripts/sf_bridge.py run`: rechazo ISOLATION_NOT_PROVEN.

`py -3 -B scripts/sf_verify.py BRIDGE-001`: **GATES_FAIL**, exit 1.
BR1-persistent-core PASS (exit 0); BR2-isolation-required FAIL (exit 2).
Reporte del ejecutor existente:
`docs/work/BRIDGE-001-VERIFY-20261112-202402.md` (reporte histórico incluido en la entrega documental).
El reporte no es un QA del reviewer ni aceptación integral. Sus indicadores
GUIA/ROADMAP se refieren a cobertura previa, no a funcionalidad del puente.

`git diff --check`: sin errores. HEAD piloto y main remoto conservan SHA base.
`py -3 -B scripts/check_contracts.py`: OK, 362 referencias y 6 hashes comprobados.
Estado del original conserva los cuatro reportes untracked anteriores, sin
cambios rastreados. No se accedió al contenido de esos reportes ni a secretos.

Las pruebas usan directorios temporales externos al repo; no crean commits,
acceden a proveedores ni lanzan agentes. Reinicio simulado cerrando y reabriendo
SQLite; rollback comprobado. No es aún una prueba de caída abrupta del SO o de
recuperación de un proceso CLI real. Las observaciones de recuperación son
fixtures; el adaptador verificador de procesos/evidencias está pendiente.

## No demostrado / no completado

- Aislamiento de procesos real, bloqueo de publicación desde un agente activo.
- Adaptadores CLI, despacho, captura de session IDs reales y reanudación de CLI.
- Integración de modo piloto en comandos, sincronización y orquestador.
- Inventario Git integral con eliminaciones y fingerprints vinculados a gates/QA.
- Validación semántica y atribución independiente de evidencia QA.
- Revisión externa automatizada, correcciones y aceptación integral.
- Activación, publicación, CI sobre nuevo SHA, VERIFIED.

`receive` conserva un resultado; no interpreta que su texto constituya QA real.
No existe vía de activación por flag ni por declarar aislamiento aprobado en JSON.
Los estados GATES_FAILED/QA_CHANGES_REQUIRED requieren futura integración con
evidencia verificable; los tests de presupuesto inyectan esos estados en fixtures.

## Documentación y producto

Sin cambio de UI, manual ni hitos comerciales. Guía y roadmap conservan su
cobertura previa. Transferencia editorial registrada progresivamente por
adendas; OpenCode no adquiere autoría. USERS-001 permanece DRAFT.

## Siguiente puerta

Resolver `BRIDGE-001-ISOLATION-DECISION.md`. No ejecutar modelos sobre código
hasta demostrar aislamiento; después completar una única aceptación real.
