# INSTALL-001 — Instalador web CORE-17 P0 (una instalación)

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Contrato
- Fuente: docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md §8.1 CORE-17, §9 ARQ, hosting compartido/cron; Parte 2 §4.2 installation_info, §12 secretos, §13.1 seeds contractuales.
- CORE-02 SETUP-001 ya VERIFIED es independiente: /install prepara entorno/esquema/semillas; /setup define comercio, sucursal inicial y Admin Central. No crear usuarios ni datos comerciales desde /install.
- Modelo existente installation_info: UUID v4 único por instalación, app_version, schema_version, release_channel, installed_at, recovery_package_exported_at. No alterar contratos ni migraciones/modelo por intuición.
- BKP-08 recovery package P1★ no está implementado: mantener bloqueo NOT_READY_FOR_PRODUCTION del onboarding no demo, sin falsos bypass.

## Entregable
1. Página /install responsive/accesible es-CL: preflight de PHP/extensiones/dependencias/permisos de escritura, prueba de conexión de BD seleccionada, generación/configuración de claves fuertes, ejecución explícita de migraciones y semillas autorizadas, estado final y una única línea orientativa de cron por minuto: php artisan schedule:run (no instalar cron). Tras éxito, enviar a /setup.
2. Seguridad pública fail-closed: inicio técnico explícitamente habilitado por secreto bootstrap de uso único gestionado fuera de la aplicación pública, sesión/CSRF/token de flujo, validación server-side de cada paso, acceso no público por defecto, rate limit, bloqueo de doble envío/concurrencia, no URLs/acciones que permitan al visitante migrar una BD por simple GET.
3. Nunca permitir reinstalación: si installation_info existe, o ya hay usuarios/sucursales, o esquema/datos incompatibles, bloquear; sin borrar datos, eliminar .env, reinicializar usuarios ni sobreescribir claves de una instalación existente. Si estado inconsistente, detener y documentar recuperación manual no destructiva. Sin migrate:fresh, db:wipe ni reset.
4. Credenciales BD y secretos fuera de HTML/props/logs/flash/auditoría/QA/commits. Uso de CSPRNG para APP_KEY, BLIND_INDEX_KEY y BACKUP_ENCRYPTION_KEY, claves únicas y sin defaults de testing. Validar host, puerto, nombres y caracteres especiales; no interpolar input en shell. Persistencia segura/atómica de configuración local solo cuando operador explícitamente confirma; no leer .env real para pruebas.
5. Orquestación de instalación: preflight, prueba de conexión, confirmación explícita de destino, migraciones normales e idempotencia de semillas §13.1, registro único installation_info, mostrar cron y continuar /setup. Usar APIs Laravel seguras con estado/lock y salida sin secretos. En fallas intermedias NO marcar éxito ni revertir migraciones automáticamente. No asumir transacción entre filesystem y BD. Dejar estado fallido/recuperable sin borrados.
6. Integrar los guards SETUP-001 y rutas anteriores: /install accesible solo en bootstrap autorizado sin esquema, /roadmap y privadas siguen protegidas, /docs preservado. No crear comodines de exención del guard ni afectar autentificación.
7. Mantener los seeders existentes idempotentes y no generar demo ni licencias comerciales como efecto colateral; defaults existentes contractuales. No introducir un framework/DSL instalador externo.

## Ejecución y aceptación
- Durante esta feature SOLO escribir código y pruebas y publicar por Git. NO ejecutar instalador real, conectar BD real/remota, migrar ni sembrar DB del propietario, tocar .env real, desplegar a cPanel o instalar cron. Los tests usan SQLite :memory:, archivos temporales y dobles/fakes de conexión/migrador/secretos. El instalador en producción no se habilita por la mera publicación del código.
- Pruebas: preflight, errores de PHP/BD, CSRF/bootstrap-token, estado virgin/instalada/preexistente, marcación installation_info, UUID único, credenciales sin filtración, claves generadas de forma segura, migración/seed simuladas, fallo a mitad de instalación, paso /setup, guard del roadmap y doble confirmación. Confirmar en tests que el código nunca borra datos.
- Gates declarativos scripts/sf_verify.py INSTALL-001, suite Laravel, npm typecheck/build, check_contracts, tests de regresión AUTH/SETUP, protected files. Revisor independiente APROBADO y publisher AUTO_GIT; verificación GitHub Actions exact-SHA 2/2 antes de VERIFIED externo GPT.
- OpenCode registra GUIA_PENDIENTE_CHATGPT / ROADMAP_PENDIENTE_CHATGPT y datos reales de UI; no edita public/docs/index.html, resources/roadmap/index.html, docs/harness/CONTEXTO-CHATGPT.md.
- EARS: WHEN authorized fresh installation passes preflight AND operator confirms, THEN technical installation completes once, shows cron and continues /setup. WHEN DB or preflight fails THEN no installation success and no secret leak. WHEN an instance already exists, THEN no reinstall/overwrite. WHEN requests race or are forged, THEN no unauthorized install. WHEN a phase fails, THEN fail-closed and preserve data.

## Hard start guard
Before implementing, confirm GitHub Actions CI PHP/Node/smoke AND DOC contracts 2/2 SUCCESS on exact latest base HEAD (including this packet/current handoff). On PENDING/FAIL, STOP without code changes and send SFH1 BLOCK. Sync via scripts/safe_git_pull.py; no reset, stash, clean, remote DB or deploy.
