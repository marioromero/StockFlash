# StockFlash — Roadmap HTML del propietario

**Sucesión aprobada BRIDGE-001, 2026-11-12:** Codex CLI sucede a ChatGPT como
autor/editor exclusivo de este HTML y del contexto; OpenCode conserva
integración técnica, pruebas y publicación sin modificar el HTML. Las menciones
de ChatGPT inferiores se leen con esta sucesión, conservando estados históricos.
El puente entregará los pedidos `ROADMAP_PENDIENTE_CHATGPT` a Codex cuando se
valide; aislamiento ya comprobado, autenticación/integral aún pendientes.
No actualizar hitos ni cobertura por pruebas unitarias del puente. Activación y
VERIFIED siguen sujetos a decisión externa; la excepción documental posterior
solo permite entregar Markdown en la rama piloto (ver AGENTS), sin código.


decision_owner: Propietario, 2026-11-10
content_owner: Codex CLI (sucesor exclusivo de ChatGPT; aprobación BRIDGE-001)
technical_integrator: OpenCode
canonical_html: `resources/roadmap/index.html`
requested_route: `/roadmap` (solo administrador autenticado con `settings.manage`)
technical_ledger: `docs/work/ROADMAP-HTML-ESTADO.md`
continuity: `docs/harness/CONTEXTO-CHATGPT.md`

## Propósito

Mostrar de forma visual, breve y comprensible **dónde está StockFlash**, qué funciona realmente, qué se está preparando y qué falta para llegar a la primera versión comercializable. Incluye una línea de hitos R0–R10, diferencia demo/alfa vs primera versión vendible y señala las ampliaciones posteriores.

**No presentar fechas comprometidas, plazos, esfuerzo, coste ni porcentajes de avance ficticios.** El avance se expresa por estados de entregables comprobables: `VERIFIED`, parcialmente implementado, DRAFT, pendiente o versión posterior. El estado de una Feature Packet es independiente de la capacidad comercial.

La versión inicial de venta se sitúa en R7 (POS + Bodega sin DTE integrado, piloto, suscripciones, respaldos y seguridad) según `docs/harness/ROADMAP-STOCKFLASH.md`. No anticiparla solo porque hay UI o pruebas de desarrollo.

## Reglas estrictas de autoría

- **Codex escribe y modifica íntegramente `resources/roadmap/index.html`**:
  contenido, etiquetas, estados, estructura, diseño y scripts, tras revisión
  sustantiva de entregas cuando haya cambios reales que lo justifiquen.
- OpenCode **NO edita ese archivo**, ni estados ni genera HTML por scripts. Lo
  consulta para pruebas y entrega solicitudes a Codex por el puente validado o
  handoff explícito mientras esté pendiente, sin delegar esa autoría a otro agente.
- OpenCode implementa las rutas, enlace visual desde el Dashboard (solo para quienes puedan ver el roadmap), permisos server-side, pruebas de accesibilidad/HTTP, CI y publicación. Solo OpenCode puede actualizar la bitácora técnica `ROADMAP-HTML-ESTADO.md`.
- Ni la actualización por Codex ni un commit de HTML cambia por sí solo un estado
  contractual o concede VERIFIED.

## Integración de interfaz

- La fuente HTML se encuentra **fuera de `public/`** para evitar acceso directo sin autorización. Servirla bajo `GET /roadmap`, protegido por `auth`, `password.change`, `verify.session` y `can.permission:settings.manage`, de modo que usuarios no autorizados no puedan verla.
- En Dashboard, enlace **Avance del proyecto** visible solo para rol/permisos autorizados; abre `/roadmap`. El manual `/docs/` permanece independiente y público.
- La guía debe ser usable en móvil/escritorio y con teclado; los controles de expandir y contraer hitos deben funcionar. No se requieren servicios ni dependencias externas.

## Detección y actualización editorial por Codex

1. Al cerrar cualquier feature o al emitir `sf-handoff`, OpenCode contrasta el último SHA cubierto en `ROADMAP-HTML-ESTADO.md` con la nueva funcionalidad/hito y detecta si hay cambios que afectarían la línea de avance, el bloque de primera versión comercial o los estados.
2. Si hay cambios relevantes, registra `ROADMAP_PENDIENTE_CHATGPT` y en el informe incluye feature, SHA publicado, estado real, QA/gates/CI, pantallas y dependencias. **No edita el HTML ni infiere nuevos porcentajes.**
3. Codex recibe el cierre por el puente validado durante una ejecución autorizada,
   contrasta evidencia efectiva y actualiza HTML/contexto solo cuando corresponda.
   Mientras falte aceptar el puente, conservar un handoff explícito pendiente.
4. OpenCode integra el HTML exacto de Codex sin reescribirlo, ejecuta QA/gates
   adecuados, publica por el harness autorizado y actualiza cobertura técnica.
5. La falta de revisión del roadmap **no bloquea** la implementación o publicación de una feature, pero siempre debe aparecer como pendiente en el handoff, al igual que `GUIA_PENDIENTE_CHATGPT`.

**No hay conexión con el chat web ni tarea permanente activada:** el transporte
CLI solo se atribuye tras aceptación. Una nueva sesión de Codex recupera contexto,
protocolo, HTML y evidencia vigente antes de actuar, sin asumir actividad de fondo.

## Verificación mínima

- Comprobar GET autorizado, redirección de no autenticados y 403 sin permiso; no permitir ruta de archivo estático pública.
- Verificar hitos R0–R10, primera versión R7, títulos, anclas, interacción de expandir/contraer, legibilidad móvil y ausencia de recursos externos/secretos.
- Mantener `USERS-001` como DRAFT mientras no se apruebe formalmente; no publicar un porcentaje inventado o convertir un módulo planificado en funcionalidad existente.
