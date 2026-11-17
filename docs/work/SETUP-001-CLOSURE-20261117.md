# SETUP-001 — Cierre técnico ChatGPT (2026-11-17)

**Dictamen: VERIFIED técnico**, bajo contrato aprobado CORE-02. No significa producción lista ni instalación desplegada.

- Código: `13cdd6a6f1ae3a986884e8079f81fb34d5f259e4` (publicado en `main`).
- Feature Packet: `docs/work/features/SETUP-001.md`; aprobación delegada en contrato existente, sin decisiones comerciales nuevas.
- Entregable: asistente de primer inicio `/setup` para instalar datos de comercio/responsable, preferencias generales, primera sucursal y único Admin Central, con validación de campos, token de flujo, guard global server-side, auditoría y escritura transaccional. El esquema y las semillas deben estar previamente preparados por el responsable técnico. No ejecuta instalación ni migraciones automáticas.
- Evidencia de verificación: `docs/work/SETUP-001-VERIFY-20261117-182741.md`, **19/19 GATES_PASS**; 51 pruebas SETUP (468 aserciones) y 529 pruebas PHP (4665 aserciones), typecheck y build PASS.
- QA independiente: `docs/work/SETUP-001-REVIEW-20261117-183106-R3.md` — **APROBADO**, sin bloqueantes ni mayores nuevos. Corrección final del smoke `/roadmap` acepta únicamente 302/403 o 503 por esquema ausente con contenido esperado, y sigue prohibiendo 200 público; prueba de regresión incluida.
- GitHub Actions del SHA: [CI PHP + Node + smoke](https://github.com/marioromero/StockFlash/actions/runs/44180835960), tres jobs `success`; [contratos](https://github.com/marioromero/StockFlash/actions/runs/44180836253), job `success`. Comprobados por ChatGPT vía jobs remotos.
- Guía editorial ChatGPT: `public/docs/index.html#setup-inicial` añadida en `e09069c1179bc400495a6bb896fd557913b32ce4`, índice y 26 temas coherentes, 40 aserciones de textos verificadas estáticamente.
- Roadmap editorial ChatGPT: `resources/roadmap/index.html` actualizado en `c6fa1fce028968ab3ba137f83a20d9ac4705ea5d`. La CI del HEAD después de estas dos modificaciones editoriales debe confirmarse por separado **antes de iniciar una próxima feature**; la verificación de los archivos fue estática, no una nueva ejecución de suite.

## Límites y riesgos residuales

- **BKP-08 P1★ no implementado**: onboarding no demo en producción queda explícitamente bloqueado `NOT_READY_FOR_PRODUCTION`. No habilitar banderas de producción ni eliminar el guard sin mecanismo verificable de recuperación de secretos.
- `/install` CORE-17 no implementado; `/setup` requiere esquema y semillas existentes.
- Entorno MariaDB real, concurrencia multi-proceso, pruebas reales de navegador/responsive/teclado, lector de pantalla y despliegue **NO EJECUTADOS**; SQLite `:memory:`/CI exclusivamente.
- El estado `is_setup_completed=false` en una instalación con usuarios/sucursales existentes impide reabrir el asistente y bloquea operaciones privadas por diseño. Antes de integrar en una base DEV ya poblada, se requiere un plan seguro y autorizado de adopción/regularización, sin reinstalar ni borrar datos; **no tocar la BD remota**.
- Observaciones QA menores: consultas de esquema por petición, endurecimiento opcional del segundo `curl` del smoke, validación de locks en MariaDB pendiente.

Cierre técnico emitido por ChatGPT tras corroborar código, QA, gates, jobs CI y coherencia documental. `remote_db=UNTOUCHED`; no migraciones ni deploy.
