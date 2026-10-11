# LEEME PRIMERO — StockFlash Harness v1.1 CORREGIDO

**Este paquete reemplaza íntegramente el ZIP inicial v1.0**, porque ese paquete contenía las specs v2.1 sin integrar las decisiones comerciales del owner. No fusionar ambos paquetes ciegamente.

## Qué se corrigió
- `docs/product`: 3 especificaciones **v2.2 CONSOLIDADAS EN REVISIÓN** con cambios funcionales, de modelo y de integración (no son el PUNTO 0 final debido a decisiones abiertas).
- `docs/product/historico-v2.1`: copias v2.1 ORIGINALES, preservadas byte a byte para trazabilidad.
- `AGENTS.md`, constitución, memoria, índice y guías ahora dan PRECEDENCIA a v2.2 frente a v2.1.
- `docs/product/StockFlash-Control-SPEC-v0.1.md`: alcance de panel central privado, suscripciones y pago manual.
- `docs/governance/DECISIONES-ABIERTAS-v2.2.md`: políticas no resueltas que OpenCode no debe inventar.

## Qué NO afirma este ZIP
- No configura cPanel, Laravel ni bases de datos; no instala Engram ni crea GitHub Actions.
- No resuelve por arte de magia la política exacta de 500 ventas/mes, voucher/pagos mixtos ni tablas físicas de Control. Son decisiones estructurales abiertas.
- No habilita publicación, push o cambios de BD productiva. No incluye secretos.

## Instalación desde Windows/Warp
1. Abre el repositorio clonado StockFlash (con `.git`). Si instalaste ZIP v1.0, respalda antes cualquier archivo local que hayas cambiado a mano.
2. Descomprime **v1.1** en la raíz; acepta reemplazar archivos anteriores del harness (salvo cambios personales que debas preservar).
3. Elimina manualmente **solo** `docs/product/especificacion-v2.1-*.md` del NIVEL PRINCIPAL `docs/product` si se encuentran por el paquete anterior. No borres `historico-v2.1/`.
4. Confirma que `AGENTS.md` y `docs/product/especificacion-v2.2-...` están presentes, junto a `.agents/skills/` y `.opencode/agents/`.
5. No ejecutes `/init` ni despliegues. El siguiente paso guiado será `/sf-diagnostico` y revisión de PHP/MariaDB/SSH/cPanel.

**Precaución:** si anteriormente pegaste contraseñas de FTP o BD en el chat, deben rotarse antes de un despliegue real y almacenarse fuera de repo/prompts.
