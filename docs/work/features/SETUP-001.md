# SETUP-001 — Asistente de primer inicio CORE-02 (una instalación)

status: APPROVED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Alcance aprobado

Implementar **en una unidad funcional coherente** el onboarding `/setup` de CORE-02 P0 sin improvisar políticas comerciales ni reescribir el instalador. Fuente contractual Parte 1 §8.1 CORE-02, SEC-02/04/05/09, CORE-03/04/05/09/10; Parte 2 §3.1 `is_setup_completed`, `business_name`, región/moneda/redondeo, responsable, datos tributarios; modelos existentes `users`, `roles`, `branches`, `system_configs`, `audit_logs`. Dependencias AUTH-001/BRANCH-001/USERS-001/CFG-002/CFG-003 VERIFIED. El proyecto tiene un comando local de administrador DEV preexistente: no eliminarlo.

El onboarding sirve **una instalación nueva cuyo esquema y semillas ya están preparados en un entorno autorizado**, separadamente del instalador `/install` CORE-17. No ejecutar ni automatizar migraciones, seeds, archivos `.env`, pruebas de conexión con credenciales, instalaciones remotas ni deploy. No declarar cumplimiento de la puerta de **recuperación de secretos BKP-08 P1★** para producción: no se implementa en este packet y no se simula una descarga.

## Función integrada

1. Ruta/Página Inertia `/setup` responsiva y accesible (es-CL), con pasos en una experiencia continua y validación de avance: datos del comercio y responsable, preferencias país/moneda/decimales/zona horaria/idioma/redondeo e inclusión de impuestos, datos tributarios del comercio, sucursal inicial y credenciales del primer Admin Central. Usar campos y validaciones contractuales del `system_configs`, CFG-002/003, `branches` y `users`. No duplicar utilidades en un framework de formularios genérico, ni crear una segunda fuente de permisos.
2. **Exclusividad segura del primer inicio:** todo tráfico hacia operaciones privadas administrativas/comerciales debe redirigir o bloquear mientras `is_setup_completed=false` de forma server-side, con excepciones acotadas para `/setup`, assets, login/sesión según diseño y rutas públicas `/docs/`/status legítimas. Configuración no preparada o sin esquema: falla controladamente, no ejecuta migraciones para arreglarla. Una vez completado, `/setup` no revela/edita datos ni permite re-onboarding; prohibir reuso por GET/POST y prevenir carrera de dos finalizaciones. No permitir que un usuario malicioso restablezca `is_setup_completed` mediante payload.
3. **Administrador inicial:** crear **una sola** cuenta Admin Central activa con contraseña robusta según AUTH-001, hash seguro y rol realmente existente de semillas, sin secretos por defecto. Sin cuentas duplicadas, sin reset de usuario previo, sin cambiar el RBAC ni debilitar el bloqueo de intentos. Datos de contraseña y PII nunca en logs, auditoría, reportes ni respuestas Inertia posteriores. No permitir vía pública crear administradores adicionales. Si ya existen usuarios reales o la instancia no está virgen, bloquear reapertura/creación pública y ofrecer salida segura informativa sin destruir nada (no auto-marcar setup como completo).
4. **Sucursal y negocio:** crear una sola sucursal inicial válida (tipo contractual), y configurar solo claves de instalación aprobadas desde pasos del wizard; preservar todas las otras claves, licencia y roles, sin tocar fiscal externo ni documentos tributarios. Guardar el conjunto final de claves + sucursal + primer Admin + auditorías + `is_setup_completed=true` de forma transaccional e idempotente. Si falla una validación, auditoría o FK, rollback integral y no dejar instalación marcada como completada.
5. **Seguridad ante paralelismo:** protección del flujo no autenticado por estado de instalación, token CSRF, serialización/lock adecuado y protección contra finalizaciones simultáneas; no filtrar PII ni estado de otros clientes. Cubrir fail-closed cuando se intenta manipular el orden de pasos, IDs, rol, `is_demo` o claves extra. Usar únicamente permisos y guards existentes.
6. **BKP-08 P1★:** para producción no demo el contrato prohíbe completar onboarding sin descarga verificable del paquete de recuperación, característica no implementada todavía. No crear un bypass silencioso ni afirmar producción lista. Diseñar borde explícito/requisito pendiente; si para satisfacer P0 sin romper acceso local es necesario un modo DEV/test seguro basado en entorno **no producción**, registrarlo con pruebas, sin cambiar `is_demo`, licenciamiento ni la política de producción. Si la arquitectura requiere una decisión comercial nueva real, detener solo el paso afectado y presentarla, no pedir reaprobar el packet.
7. Integrar sin romper AUTH (sesiones/must_change_password), BRANCH, USERS, CFG y catálogo ya VERIFIED. Los entornos existentes con datos no se deben reinicializar ni modificar automáticamente al desplegar código. No cambiar semántica de `is_setup_completed` global en pruebas existentes de forma que esconda fallos; para test suite se debe preparar el fixture correcto y comprobar rutas protegidas.

## Evidencia exigida

- Tests SQLite `:memory:` para GET inicial, validaciones es-CL, CSRF/flujo manipulado, campos permitidos/no permitidos, privacidad, verificación de clave/rol Admin, sucursal inicial, valores de configuración, audit log sin PII ni contraseña, rollback con fallo de auditoría y duplicados, bloqueo de rutas antes del setup, bloqueo de `/setup` después, instalación existente/no virgen sin reiniciar, concurrencia lógica y regresiones globales. No afirmar concurrencia real MariaDB sin test autorizado.
- Gates declarativos `SETUP-001` en `GATES_BY_PACKET` de `scripts/sf_verify.py` sin runner adicional; PHP suite, `npm run typecheck`, `npm run build`, contratos, archivos protegidos. Revisor independiente `APROBADO`; `stockflash-publisher` `AUTO_GIT`; CI 2/2 SUCCESS sobre SHA exacto o informar estado auténtico. Actualizar metadatos de guía/roadmap como pendientes para ChatGPT; no editar `public/docs/index.html`, `resources/roadmap/index.html` ni `docs/harness/CONTEXTO-CHATGPT.md`.
- Entrega SFH1 breve y salida chat ordinaria **exactamente `OK`**, salvo bloqueo/acción humana real, según `AGENTS.md` regla 24.

## EARS de aceptación

- CUANDO la instalación virgen sin setup abre `/setup`, DEBE mostrar el asistente y no permitir operaciones privadas hasta finalizarlo.
- CUANDO se completa válidamente en entorno autorizado, DEBE persistir configuración, primera sucursal y Admin Central exactamente una vez con auditoría segura; `is_setup_completed` cambia únicamente junto con el commit íntegro.
- CUANDO se intenta repetir el proceso o hay un usuario ya existente, NO DEBE crear otro admin ni borrar/restablecer datos.
- CUANDO una escritura/auditoría falla, NO DEBE quedar configuración, sucursal, usuario o marca de finalización parcial.
- CUANDO se intentan manipular permisos, parámetros, secretos o la puerta de producción BKP-08, DEBE rechazar sin bypass silencioso.

## Exclusiones

`/install` CORE-17, actualización de Laravel/cPanel, migraciones/seeders, claves de servidor, recovery package real BKP-08, datos de prueba remotos, emisión SII/DTE, creación masiva de sucursales/usuarios, UI de suscripciones, POS/inventario, cambios comerciales/ADR pendientes, nuevas dependencias o autores HTML ajenos. Sin BD DEV remota ni deploy sin autorización explícita del propietario. Un bloqueo de producción por BKP-08 se registra como `NOT_READY_FOR_PRODUCTION` sin falsear VERIFIED técnico en ambiente DEV si los criterios P0 se prueban correctamente.
