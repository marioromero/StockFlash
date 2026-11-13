# USERS-001 — revisión externa ChatGPT (2026-11-13)

status: CAMBIOS_NECESARIOS
feature: USERS-001
reviewed_sha: 882f281668793116ea18db6ab31be53b7a312d76
authority: docs/work/features/USERS-001.md; AGENTS.md §11; PRV-06 / CORE-10
ci_runs: https://github.com/marioromero/StockFlash/actions/runs/43490387571 ; https://github.com/marioromero/StockFlash/actions/runs/43490387483
note: ChatGPT inspeccionó código e informes publicados y CI remota; no ejecutó PHP localmente

## R1 — filtración de nombre/correo en valores de auditoría (BLOQUEANTE para VERIFIED)

El SHA publicado tiene gates 11/11 PASS, QA OpenCode APROBADO y CI GitHub Actions SUCCESS. Sin embargo, `app/Http/Controllers/UserController.php` define `auditValues(User)` que devuelve las claves `name` y `email` **con valores reales**, y utiliza esa salida para `user.created` y `user.updated` en `audit_logs.old_values`/`new_values`. Los registros de lectura (`recordRead()`) están minimizados, pero los eventos de escritura siguen almacenando esos identificadores. Contradice la decisión de no copiar nombres/correos al log de auditoría, así como la regla 11 de `AGENTS.md` (sin PII en logs).

La suite actual comprueba que no se registren contraseñas ni PIN y que los eventos de lectura no lleven PII, pero no cubre valores PII de eventos de creación/edición. No interpretar CI verde como aceptación de esta condición.

## Solicitud de corrección dentro de USERS-001 aprobado

1. Retener la trazabilidad requerida: actor, fecha, tipo e ID de usuario, operación, `is_personal_data_access`, `is_security_event`, IP/user-agent y, opcionalmente, nombres de campos modificados o booleanos (`name_changed`, `email_changed`), **sin persistir valores de nombre, email ni identificadores personales** en old/new, ni secretos/hashes.
2. Conservar el cálculo de si cambió PII comparando en memoria valores originales y nuevos **antes** de producir una carga sanitizada para el audit log. Si se modifica nombre/correo, mantener `is_personal_data_access=1`. La auditoría de creación/edición debe seguir en la misma transacción y revertir la operación ante fallo.
3. Añadir regresión en `tests/Feature/Users/UserAuditTest.php`: crear y editar con nombre/email sintéticos únicos; enumerar los eventos registrados y comprobar ausencia de los valores originales/nuevos en `old_values`/`new_values`. Verificar que flags PII y eventos de seguridad sean correctos y que las demás pruebas existentes sigan pasando.
4. Ejecutar nuevamente `py -3 scripts/sf_verify.py USERS-001`, QA independiente sobre el estado exacto; tras PASS+APROBADO publicar automáticamente mediante `stockflash-publisher` (AUTO_GIT). Enviar por SFH1 SHA final, pruebas, QA y CI de ese mismo SHA. No tocar contratos, deploy, migraciones/DB remota ni declarar VERIFIED.

## Deudas no bloqueantes conocidas

- Test de concurrencia real sobre MariaDB aislada y validación visual/responsive en navegador no ejecutados. No afirmar que se ejecutaron.
- Doble evaluación de `VerifySession` en grupo web + alias: deuda menor de rendimiento del QA anterior.
- Manual HTML y roadmap siguen como pendientes editoriales exclusivos de ChatGPT; no tocarlos desde OpenCode.

Nota: No hay despliegue registrado de este cambio. Cualquier saneamiento de logs reales en otra instalación requeriría revisión y autorización separadas; no se borran datos desde esta feature.
