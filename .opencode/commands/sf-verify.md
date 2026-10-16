---
description: Ejecutar gates locales de una Feature Packet con verificador determinista, sin revisar ni editar
agent: general
---

Ejecutar el verificador determinista para la Feature Packet $ARGUMENTS (p. ej. `BOOT-001`). Este comando NO revisa como QA ni implementa: solo ejecuta gates y registra evidencia. El reviewer independiente (`/sf-review`, agente `stockflash-reviewer`) mantiene su auditoría en solo lectura y no depende de este comando.

**Ejecutor único:** `py -3 scripts/sf_verify.py $ARGUMENTS`

- Para BOOT-001 los gates obligatorios, en orden, son: `composer validate`, `php artisan test`, `npm run build` y comprobación HTTP local de `GET /` mediante servidor temporal que el propio script levanta y cierra siempre (try/finally). No sustituirlos por valores reportados en `docs/work/BOOT-001-EVIDENCE.md` ni por el veredicto de `/sf-review`.
- Si el packet no tiene gates definidos en `GATES_BY_PACKET`, detenerse con `GATES_FAIL` y proponer la definición concreta antes de continuar; no improvisar comandos.
- El verificador solo puede generar los artefactos locales necesarios para las pruebas (p. ej. `public/build/`, cachés de test) y su reporte `docs/work/<PACKET>-VERIFY-<timestamp>.md`. No leer ni registrar `.env`, secretos, claves, `vendor/`, `node_modules/` ni dumps.
- Prohibido: migraciones/seeders (`php artisan migrate*`, `db:*`, `tinker` denegados), commit/push/deploy, cambios a contratos v2.2 (`docs/product/*`, constitución, branding), ampliación de permisos globales y servicios permanentes (`serve` solo temporal dentro del script).
- Veredicto del script: `GATES_PASS` o `GATES_FAIL`. Ante cualquier gate obligatorio fallido, el resultado es `GATES_FAIL`; no presentarlo como PASS. Nunca declarar `VERIFIED`: eso corresponde al owner/ChatGPT fuera de OpenCode tras revisar el reporte.
- Entregar: tabla gate/comando/exit/PASS-FAIL, ruta del reporte generado y procesos temporales cerrados. Autorización del propietario 2026-10-16: sin aprobaciones por invocación; ejecutar directo.
