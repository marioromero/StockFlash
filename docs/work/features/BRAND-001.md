# BRAND-001 — Sistema SVG provisional de StockFlash (8 logos + inventario)

status: APPROVED
owner_approval: APROBADA por propietario + ChatGPT el 2026-10-14 para ocho SVG provisionales solo para revisión visual (decisión externa a OpenCode); activada en CURRENT.md. No declara logos oficiales ni VERIFIED.
etapa: BRANDING
responsable_implementacion: OpenCode (solo tras APPROVED + activación)
revision: Independiente (`/sf-review BRAND-001`) antes de dar por VERIFIED.

garantías: este documento es propuesta para aprobación; no reutilizar como packet aprobado ni ejecutar anticipadamente.

## Bloqueo previo explícito

- Histórico 2026-10-13 (superado el 2026-10-14 con BOOT-001 VERIFIED): `docs/work/CURRENT.md` mantenía **BOOT-001 activo, NO VERIFIED y NO release**, con instrucción expresa: **«No implementar otra feature antes de este gate»**.
- Verificación local `/sf-verify` BOOT-001 = `GATES_PASS` (`docs/work/BOOT-001-VERIFY-20261013-213942.md`), pero **VERIFIED solo lo declara el owner/ChatGPT fuera de OpenCode**.
- Bloqueo superado el 2026-10-14 (BOOT-001 VERIFIED, packet APPROVED, activación en `CURRENT.md`); la condición histórica era que BRAND-001 **NO se implementaba** hasta: (1) BOOT-001 VERIFIED, (2) aprobación del propietario de este packet, (3) activación en `CURRENT.md`. No se modifica `CURRENT.md` desde este packet.

## Qué / Por qué / No incluido

- Propósito concreto: crear los ocho logos vectoriales provisionales de StockFlash y documentar su uso (`docs/branding/brand-assets.md`), sin contradecir contratos de marca ni alterar el funcionamiento Laravel actual. Autorización del propietario cubre **preparar assets SVG provisionales**; no los declara logos oficiales definitivos ni reemplaza los PNG oficiales previstos.
- Exclusiones:
  - No declarar los SVG oficiales definitivos ni sustituir el fallback textual de las pantallas automáticamente.
  - No generar logos para Caja, Clientes, Ventas, Precios ni otros componentes internos (funciones internas, nunca productos).
  - No modificar `brand-contract.md`, `module-identity-map.md`, `AGENTS.md`, `CONSTITUTION.md` ni `opencode.json` (protegidos, `deny`).
  - No descargar tipografías ni incorporar fuentes automáticamente; no instalar dependencias nuevas.
  - No migraciones, despliegues, commits ni push desde agentes.
- Entitlement / permisos si aplica: n/a (assets estáticos públicos, sin PII ni RBAC).

## Referencias precisas

- Parte 1: §4.1 (arquitectura modular), §4.5 (estructura de código), §5 (stack); Parte 2: sin cambios de tablas/columnas; Anexo: n/a.
- `docs/branding/brand-contract.md`: identidad canónica (Stock `#303846`, Flash `#0856FF`, turquesa POS `#03C6BE`); PNG oficiales prevalecen; sin PNG usar fallback textual; **«Nunca dibujar un nuevo logo por cuenta propia»** — la excepción para estos SVG provisionales la da la autorización del propietario registrada en este packet (APPROVED 2026-10-14).
- `docs/branding/module-identity-map.md`: 8 identidades y nombres PNG previstos (`stockflash.png`, `stockflash-pos.png`, `stockflash-bodega.png`, `stockflash-syncwp.png`, `stockflash-fiscal.png`, `stockflash-compras.png`, `stockflash-analitica.png`, `stockflash-control.png`). Control = app privada del fabricante, no producto contratado.
- `docs/ux/UI-UX-CONTRACT-v0.1.md`: tokens compartidos, sin librerías visuales nuevas por feature sin aprobación.
- AGENTS.md regla 10: PNG oficiales en `public/images/brand/`; si no existen, identidad textual provisional.
- ADR aprobado y deltas aplicados: ninguno; ADR-01..06 abiertos, no se deciden aquí.

## Contratos/casos límite

- Invariantes:
  - Exactamente 8 archivos en `public/images/brand/`: `stockflash.svg`, `stockflash-pos.svg`, `stockflash-bodega.svg`, `stockflash-syncwp.svg`, `stockflash-fiscal.svg`, `stockflash-compras.svg`, `stockflash-analitica.svg`, `stockflash-control.svg`. Respetar «Analítica» con tilde y «SyncWP» con esa escritura. Estado local verificado 2026-10-14: `public/images/brand/` **no existe** (ni PNG ni SVG); crear la carpeta solo al implementar, sin sobrescribir nada existente.
  - Marca madre: texto `StockFlash`, `Stock` `#303846`, `Flash` `#0856FF`, horizontal, sin isotipos/efectos/degradados. Productos: mismo wordmark + etiqueta rectangular adosada a la derecha con el nombre (criterio provisional sujeto a revisión del propietario). POS: etiqueta turquesa `#03C6BE` con texto oscuro contrastante. Demás: etiqueta con color provisional sobrio por línea (ciclo 3, 2026-10-14, solo revisión visual — Bodega `#1E7A4C`, SyncWP `#4E53A8`, Fiscal `#8C5400`, Compras `#B03A2E`, Analítica `#6C3FA3`, Control grafito `#303846`; texto `#FFFFFF` salvo POS); **acentos provisionales, no definitivos ni oficiales**. Etiquetas ≠ colores semánticos éxito/advertencia/error.
  - SVG 1.1 válido por `<img>`: `xmlns`, `viewBox`, `<title>`, XML válido, proporciones/márgenes sin recortes, sin JS/CSS remoto/recursos de red, sin raster/Base64/scripts/`foreignObject`/referencias externas, sin librerías JS, legible sobre fondo blanco y en escalado responsive. `<text>` con fuentes del sistema solo como solución provisional documentada (varía por plataforma; no es arte definitivo).
  - `docs/branding/brand-assets.md` = inventario operativo (12 secciones según solicitud); no reemplaza `brand-contract.md`; documenta estado provisional pendiente de aprobación visual y política de sustitución por PNG/vectores oficiales.
  - Skills: agregar en `stockflash-brand-system` y `stockflash-no-brand-drift` referencia a `brand-assets.md` y orden de consulta (1. contrato, 2. mapa, 3. inventario); la revisión exige asset existente y prohíbe presentarlo como oficial sin evidencia. Sin tocar reglas comerciales de navegación/permisos/licencias.
- Datos de prueba sintéticos: n/a (sin datos; validación = XML bien formado, ausencia de recursos externos/inseguros, inspección visual en fondo claro).

## Aceptación (EARS)

- CUANDO se implemente BRAND-001, EL SISTEMA DEBE contener exactamente los 8 SVG listados, válidos y autocontenidos.
- CUANDO una tarea implique identidad visual, EL AGENTE DEBE consultar contrato → mapa → inventario, en ese orden.
- CUANDO los PNG oficiales lleguen, EL INVENTARIO DEBE definir su sustitución; los SVG NO DEBEN presentarse como oficiales sin aprobación visual del propietario.
- CUANDO falte aprobación visual definitiva de los SVG, NO DEBEN presentarse como oficiales ni integrarse como fuente primaria; BOOT-001 está VERIFIED y este packet está APPROVED (2026-10-14), por lo que la creación de los 8 SVG provisionales está autorizada.

## Plan técnico mínimo por OpenCode

- Tareas 1–10:
  1. Re-verificar `CURRENT.md` activo y este packet APPROVED antes de tocar archivos.
  2. Crear `public/images/brand/` + 8 SVG según diseño controlado.
  3. Validar XML de los 8 (parser), ausencia de externos/inseguros (`script`, `foreignObject`, `http`, `data:image`, `base64`), nombres y colores contra contrato.
  4. Revisión visual en fondo claro (legibilidad, recortes, vista conjunta sin dependencias nuevas).
  5. Crear `docs/branding/brand-assets.md` (12 secciones, rutas reales Laravel/Vue, correspondencia PNG, limitaciones `<text>`, propuesta de ajuste contractual).
  6. Actualizar las 2 skills (solo referencias + orden de consulta + exigencia de existencia).
  7. Registrar propuesta concreta de ajuste contractual para owner+ChatGPT (¿pasan los SVG a fuente visual primaria?).
  8. `git status` (incl. untracked); no alterar archivos BOOT-001/dominio.
  9. `/sf-review BRAND-001` (solo lectura).
  10. Entregar informe: creados, modificados, validaciones, limitaciones, decisiones pendientes. Estado final: **listo para revisión visual**, logos no aprobados.
- Archivos afectados previstos: `public/images/brand/*.svg` (8 nuevos), `docs/branding/brand-assets.md` (nuevo), 2 `SKILL.md` (referencias). Prohibidos: contratos protegidos, `CURRENT.md` (lo activa el owner), código BOOT-001/dominio.
- Tests y evidencia: validación XML + grep de patrones prohibidos + checklist visual + `git status`; sin migraciones ni tests de dominio.

## Gates

- [ ] 8 SVG existen, válidos, autocontenidos, colores/nombres contra contrato
- [ ] `brand-assets.md` existe con referencias válidas y estado provisional explícito
- [ ] 2 skills consultan el inventario (orden contrato → mapa → inventario)
- [ ] seguridad/permisos: sin externos/inseguros, sin secretos, sin cambios a protegidos
- [ ] responsive/táctil: legibilidad en escalado y fondo claro (revisión visual)
- [ ] reviewer independiente (`/sf-review BRAND-001`)
- [ ] Sin commit/push/deploy; logos NO aprobados (revisión visual pendiente del propietario)
