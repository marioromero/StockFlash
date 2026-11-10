---
description: Implementar íntegramente una Feature Packet aprobada, con checkpoints y pruebas
agent: stockflash-implementer
---

**Sincronización de inicio (automática, una sola vez):**

!`py -3 scripts/safe_git_pull.py`

Si la salida empieza con `SYNC_BLOCKED` o falta `SYNC_OK`, detener esta ejecución y solicitar revisión del estado Git. No continuar trabajando con instrucciones posiblemente desactualizadas ni intentar reset/stash/clean para resolverlo.

Ejecutar Feature Packet $ARGUMENTS solamente si tiene `status: APPROVED` y `docs/work/CURRENT.md` lo marca activo y autoriza ejecución. Implementar las subtareas previamente aprobadas hasta cumplir sus criterios de aceptación o producirse un bloqueo real, con tests y checkpoints. NO finalizar tras una subtarea exclusivamente diagnóstica si las siguientes están autorizadas y son ejecutables. No repetir aprobación humana de alcance por cada subtarea ordinaria. Leer las skills y secciones pertinentes bajo demanda. Respetar las dependencias explícitamente autorizadas por el Feature Packet; no ampliar dependencias ni modificar `docs/product`, modelo DB, constitución o branding sin decisión. Generar pruebas pertinentes y registrar la evidencia concreta.

**Excepción necesaria de bootstrap:** Si la feature activa es `BOOT-001`, la ausencia de `artisan`, `composer.json`, `public/` y del framework es el estado inicial esperado, NO un motivo de bloqueo. Crear el scaffold Laravel de acuerdo con `BOOT-001.md` y el plan ya revisado, en una carpeta temporal controlada antes de fusionar archivos selectivamente; preservar los archivos actuales. Inspeccionar colisiones y solicitar autorización antes de sobrescribir archivos existentes. **La necesidad de permiso para `bash` o `edit` NO es por sí sola un bloqueo:** autorización del propietario 2026-10-16: sin aprobaciones; invocar la herramienta pertinente y continuar. No limitarse a declarar «esperando autorización» sin emitir la operación concreta. **Seguridad Composer/Laravel 13:** el scaffold oficial puede incluir `post-create-project-cmd` con una migración SQLite; usar `composer create-project` **con `--no-scripts`** en la carpeta temporal interna (nunca sobre la raíz ocupada). No ejecutar `composer run-script setup`, migraciones ni otros scripts que las disparen. Inspeccionar antes de habilitar scripts normales de Composer. Prohibidas migraciones, despliegues y datos reales. Esta excepción NO se aplica a otras features.

La selección de Laravel 13.x estable compatible con PHP 8.3 ya fue revisada en BOOT-001; continuar desde creación del scaffold sin reabrir esa elección salvo error reproducible de Composer.

Si no hay Feature Packet válido, el trabajo no está autorizado o la tarea necesita decisión de arquitectura fuera del contrato, diagnosticar y detenerse sin escribir la parte afectada. Nunca `git commit`, `git push` ni deploy desde este comando.

**Control de documentación de usuario (obligatorio al cierre funcional):** revisar `docs/harness/GUIA-HTML-USUARIO.md` y `docs/work/GUIA-HTML-ESTADO.md`. Informar qué pantallas/opciones nuevas debe explicar ChatGPT. No escribir ni modificar el contenido explicativo del HTML en `public/docs/index.html`: autoría exclusiva de ChatGPT. No detener implementación del código por estar la guía pendiente.
