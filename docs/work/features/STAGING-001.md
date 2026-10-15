# STAGING-001 — Preparación de staging aislado StockFlash

status: DRAFT
owner_approval: Pendiente para acciones de infraestructura. Se permite solo el análisis documental y lectura de recursos públicos. Ningún SSH, despliegue, migración ni prueba de conexión al hosting se autoriza por este packet.
etapa: R2_STAGING
responsable_implementacion: Por asignar después de aprobación externa.
revision: Arquitectura y QA de infraestructura antes de activar ejecución.

## Objetivo
Pasar de CI local/remota verificada a un entorno staging cPanel real, aislado de producción, reproducible y con rollback. No implementar módulos de negocio. No confundir este documento preparatorio con autorización para desplegar.

## Estado de partida comprobado
- CI-001 VERIFIED en GitHub Actions: PHP 8.3, Node 24, Smoke GET /, y hashes de contratos, todos SUCCESS sobre `218de3a`.
- Hosting compartido cPanel: PHP web/CLI 8.3; Node 20 del hosting es distinto de Node 24 en CI pero **no exige** compilar en hosting si se despliegan artefactos precompilados.
- Staging HTTPS y base DEV fueron reportados, pero su aislamiento real no se verificó.
- Diagnóstico SSH actual usa cuenta amplia de cPanel `grupoint`: no aprobada para despliegue. No almacenar ni reproducir claves aquí.
- MariaDB versión exacta no verificada; la puerta R3 exige MariaDB 10.6+ y esquemas probados después de las decisiones de dominio.

## Alcance futuro por aprobar
1. Inventario NO INVASIVO de capacidades del hosting: aislamiento de staging, rutas, document root de Laravel (`public/`), versión y extensiones PHP, cron, supervisor/queue-worker factible, DB DEV separada, TLS y protección de staging.
2. Selección de mecanismo de despliegue acorde con hosting compartido: preferir paquetes precompilados con versiones fijas y release atómico o secuencia reversible donde el proveedor lo permita.
3. Usuario/credencial de privilegio mínimo para despliegue, acceso acotado a staging; si cPanel no soporta aislamiento efectivo, registrar bloqueo y alternativas sin inventar capacidad.
4. Verificación de la cadena de suministro desde CI: artefactos generados y aprobados, integridad/hash, separación de secretos y controles de release.
5. Preparar respaldo/restauración y rollback comprobable del staging; registrar tiempos y responsables.
6. Smoke staging sobre URL autorizada, sin datos reales ni cargas sobre producción.
7. Observabilidad mínima y evidencia de ejecución; no tratar CI en verde como autorización automática de producción.

## Restricciones
- No leer, copiar, imprimir ni commitear credenciales o archivos `.env`.
- No reutilizar usuario general `grupoint` para automatizar deploy ni conceder acceso a producción por comodidad.
- No migraciones, seed, envío de correos de prueba a destinatarios reales, cron o workers productivos.
- No alterar contratos v2.2, ADR ni alcance de R3.
- No activar un workflow de deploy hasta un nuevo packet aprobado, entorno y rollback comprobados.

## Gates para salida de preparación
- Capacidad de staging independiente y document root validada.
- Identidades y permisos acordados en el hosting; credencial mínima disponible sin publicarse.
- Procedimiento de entrega + rollback ensayables documentados.
- Cuenta y costos/limitaciones del hosting confirmados.
- Evidencia técnica, QA independiente, autorización externa de despliegue.

## Próxima decisión
Antes de pasar de DRAFT a APPROVED: verificar con el propietario qué aislamiento real permite su cPanel y si desea usar staging en el mismo hosting o un entorno alternativo. No desplegar mientras esto no se determine.
