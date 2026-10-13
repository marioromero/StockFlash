---
description: Revisar la implementación completa y evidencia de Feature Packet aprobado, sin editar
agent: stockflash-reviewer
---

Revisar independientemente Feature Packet $ARGUMENTS, criterios de aceptación, cambios rastreados, **archivos nuevos sin seguimiento Git** y evidencia/tests. Solo lectura; nunca implementar, aprobar despliegues ni alterar archivos.

**Inventario obligatorio antes de concluir si hay código:**
- Ejecutar `git status --short`, `git diff --stat`, `git diff` y `git ls-files --others --exclude-standard`. El último comando es indispensable: `git diff HEAD` NO muestra archivos untracked.
- Leer los archivos untracked pertinentes directamente mediante herramienta de lectura. Verificar estructura, dependencias, scripts, rutas, pruebas y configuración; no suponer ausencia de código porque el diff esté vacío.
- No leer `.env`, claves, secretos, `vendor/`, `node_modules/`, ni carpetas temporales ignoradas; comprobar exclusiones por rutas y configuración sin abrir contenidos sensibles.
- Para BOOT-001 comprobar al menos `composer.json`/lock, `package.json`/lock, `vite.config.ts`, `routes/web.php`, recursos Vue/Inertia, `app/`, `bootstrap/`, `config/`, `database/`, `tests/`, `.gitignore` y `docs/work/BOOT-001-EVIDENCE.md` con el estado LOCAL presente, no evidencia de sesiones antiguas.
- Contrastar el informe de pruebas con evidencia reproducible. Puede solicitar permisos `ask` para comandos de solo lectura o tests locales que no impliquen migraciones ni efectos remotos. No simular resultados ni aceptar una captura desactualizada.

Clasificar hallazgos por severidad: correctness, permisos/privacidad, UX, performance, schema y cobertura. Resultado `APROBADO` o `CAMBIOS NECESARIOS`, con bloqueantes y evidencia exacta (archivo + línea o comando). No declarar `VERIFIED` ni aprobar release: corresponde a los gates y revisión de owner/ChatGPT después del QA.

Si no hay código tras comprobar **también untracked**, indicar `NO APLICA`. No omitir revisiones por tratarse de un scaffold todavía sin commit.
