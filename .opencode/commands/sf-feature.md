---
description: Implementar una subtarea acotada de Feature Packet aprobado
agent: stockflash-implementer
---

Ejecutar Feature Packet $ARGUMENTS solamente si tiene `status: APPROVED` y `docs/work/CURRENT.md` lo marca activo y autoriza ejecución. Una subtarea por invocación, no todo el epic. Leer las skills y secciones pertinentes bajo demanda. Respetar las dependencias explícitamente autorizadas por el Feature Packet; no ampliar dependencias ni modificar `docs/product`, modelo DB, constitución o branding sin decisión. Generar pruebas pertinentes y registrar la evidencia concreta.

**Excepción necesaria de bootstrap:** Si la feature activa es `BOOT-001`, la ausencia de `artisan`, `composer.json`, `public/` y del framework es el estado inicial esperado, NO un motivo de bloqueo. Crear el scaffold Laravel de acuerdo con `BOOT-001.md`, en una carpeta temporal controlada antes de fusionar archivos selectivamente; preservar los archivos actuales. Inspeccionar colisiones y solicitar autorización antes de sobrescribir. Prohibidas migraciones, despliegues y datos reales. Esta excepción NO se aplica a otras features.

Si no hay Feature Packet válido, el trabajo no está autorizado o la tarea necesita decisión de arquitectura fuera del contrato, diagnosticar y detenerse sin escribir la parte afectada. Nunca `git commit`, `git push` ni deploy desde este comando.
