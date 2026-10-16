# STAGING-001 — Informe del camino de despliegue (2026-10-16)

## Punto de partida

Scaffold BOOT-001 VERIFIED (Laravel 13 + Vue 3 + Inertia, SHA `54073d04`), hosting cPanel compartido `grupoint`, app en `/home/grupoint/stockflash-dev-app`, Document Root en `.../public`, URL `https://stockflash-dev.tusitioexpress.cl/`. Cuenta FTPS operativa, sin SSH utilizable, sin migraciones R3. STAGING-001 APPROVED, no VERIFIED.

## Intento 1 — FTPS archivo por archivo (fallido)

Transportador `scripts/staging_ftps_one_shot.py` con 8911 archivos. Resultado real: 92 subidos, 8819 errores (`EOF occurred in violation of protocol`), backup `backup-20261015-212305/`.
Causa: Pure-FTPd exige reutilizar la sesión TLS en cada canal de datos; `ftplib` no lo hace y el canal muere tras los primeros archivos, con cascada de fallos sin reconexión. Se corrigió con `ReuseFTP_TLS` + reconnect + reintentos + cortacircuito, pero el propietario ordenó no seguir perfeccionándolo.

## Intento 2 — ZIP único + curl manual (abandonado por operación manual)

ZIP de 8911 archivos validado (`testzip` OK, 0 excluidos). La subida manual por PowerShell/cPanel exigía intervención repetitiva del propietario en cada despliegue. Decisión arquitectónica: automatizar todo.

## Camino final — despliegue automatizado vía API de cPanel (exitoso en archivos)

Piezas: `scripts/sf_credstore.py` (DPAPI Windows, fuera de Git) + `scripts/sf_deploy_staging.py` (12 pasos) + comando `/sf-deploy-staging`. Verificaciones previas sin asumir nada: API base `https://cp012.servidoresph.com:2083` con TLS válido, `PROBE_PASS` con archivos efímeros.

Problemas reales encontrados y cómo se resolvieron:

1. **Forma UAPI distinta a la documentada**: responde `{status,data}` plano, no `{result}`. Se aceptan ambas formas; lo desconocido bloquea, no se asume.
2. **Mutaciones solo por API2** (`UAPI Fileman::fileop` no existe en este servidor). Fallback implementado.
3. **Parámetros correctos según docs**: `destfiles` (no `destdir`), `doubledecode=1`, rutas ABSOLUTAS (las relativas extraían en un subdir duplicado anidado).
4. **`unlink`/`rmdir` mienten** (resultado 1 sin borrar). Eliminación real con `trash` + re-listado de verificación.
5. **`list_files` nunca muestra dotfiles**. `.env` se gestiona con `overwrite=0` (crear sin clobber, sin leer secretos) y la prueba es funcional vía smoke.
6. **Extracciones largas mueren en el servidor**: 9 partes ZIP (~1200 archivos c/u) con centinela por parte.
7. **Rollback indiscriminado destruía buenos despliegues**: solo se revierte ante fallo APP; fallo NET (red) no revierte.
8. **Error de arranque Laravel** (`sessions` sobre SQLite inexistente): `.env` corregido a `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` + `APP_KEY` rotada (sin usuarios afectados) + `storage/framework/cache/data`.

## Qué se necesitó del propietario (3 cosas, nada más)

1. Crear el token API de cPanel una vez y guardarlo con `sf_credstore.py`.
2. Eliminar los `ask` del harness (autorizado 2026-10-16) para ejecución directa.
3. Verificar en navegador la pantalla StockFlash (mi egress no llega al vhost) y confirmar para cerrar con `APP_DEBUG=false`, gates y QA.

## Estado actual

Archivos 100 % desplegados y verificados; backup y cuarentena al día; vhost atendiendo (el propietario ya ve la app); pendiente solo confirmación visual para el cierre G6 + QA + `VERIFIED` externo.
