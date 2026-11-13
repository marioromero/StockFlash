# StockFlash — Roadmap HTML del propietario

decision_owner: Propietario, 2026-11-10
content_owner: ChatGPT (exclusivo)
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

- **ChatGPT escribe y modifica íntegramente `resources/roadmap/index.html`**: contenido, texto, etiquetas, estados, estructura, diseño y scripts. Solo ChatGPT actualiza esta página en cada revisión sustantiva de entregas de OpenCode, si hay cambios reales que lo justifiquen.
- OpenCode **NO edita ese archivo**, ni actualiza estados/hitos/etiquetas ni genera automáticamente el HTML mediante scripts. Puede consultarlo para pruebas de solo lectura y solicitar a ChatGPT correcciones de contenido o técnicas a través del propietario.
- OpenCode implementa las rutas, enlace visual desde el Dashboard (solo para quienes puedan ver el roadmap), permisos server-side, pruebas de accesibilidad/HTTP, CI y publicación. Solo OpenCode puede actualizar la bitácora técnica `ROADMAP-HTML-ESTADO.md`.
- Ni la actualización por ChatGPT ni el commit de HTML cambia por sí solo un estado contractual o concede `VERIFIED` a una feature.

## Integración de interfaz

- La fuente HTML se encuentra **fuera de `public/`** para evitar acceso directo sin autorización. Servirla bajo `GET /roadmap`, protegido por `auth`, `password.change`, `verify.session` y `can.permission:settings.manage`, de modo que usuarios no autorizados no puedan verla.
- En Dashboard, enlace **Avance del proyecto** visible solo para rol/permisos autorizados; abre `/roadmap`. El manual `/docs/` permanece independiente y público.
- La guía debe ser usable en móvil/escritorio y con teclado; los controles de expandir y contraer hitos deben funcionar. No se requieren servicios ni dependencias externas.

## Detección automática y actualización por ChatGPT

1. Al cerrar cualquier feature o al emitir `sf-handoff`, OpenCode contrasta el último SHA cubierto en `ROADMAP-HTML-ESTADO.md` con la nueva funcionalidad/hito y detecta si hay cambios que afectarían la línea de avance, el bloque de primera versión comercial o los estados.
2. Si hay cambios relevantes, registra `ROADMAP_PENDIENTE_CHATGPT` y en el informe incluye feature, SHA publicado, estado real, QA/gates/CI, pantallas y dependencias. **No edita el HTML ni infiere nuevos porcentajes.**
3. El propietario comparte el cierre de OpenCode con ChatGPT. ChatGPT verifica evidencia en GitHub y actualiza `resources/roadmap/index.html` y `docs/harness/CONTEXTO-CHATGPT.md` **cuando corresponda**, por rama/PR documental.
4. OpenCode integra el HTML exacto de ChatGPT sin reescribirlo; ejecuta QA/gates adecuados, publica por el harness y ajusta la bitácora para marcar `ROADMAP_ACTUALIZADO` con SHA cubierto.
5. La falta de revisión del roadmap **no bloquea** la implementación o publicación de una feature, pero siempre debe aparecer como pendiente en el handoff, al igual que `GUIA_PENDIENTE_CHATGPT`.

**Comunicación SFH1 bajo demanda:** el propietario escribe `ok` en el chat destinatario; cada IA recupera su mensaje desde GitHub issue y puede consultar código/QA y bitácora. No hay ejecución en segundo plano. Para otra sesión, leer `CONTEXTO-CHATGPT.md`, el protocolo y la última versión HTML.

## Verificación mínima

- Comprobar GET autorizado, redirección de no autenticados y 403 sin permiso; no permitir ruta de archivo estático pública.
- Verificar hitos R0–R10, primera versión R7, títulos, anclas, interacción de expandir/contraer, legibilidad móvil y ausencia de recursos externos/secretos.
- Reflejar el estado real de USERS-001 según el packet: DRAFT antes de aprobar, `APPROVED` una vez implementada, y `VERIFIED` solo tras decisión externa expresa. No publicar porcentajes inventados ni confundir código publicado con despliegue comercial.
