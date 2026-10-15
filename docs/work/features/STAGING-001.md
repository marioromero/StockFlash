# STAGING-001 — Despliegue controlado a staging cPanel existente

status: APPROVED
owner_approval: Aprobación externa expresa del propietario (2026-10-15) para utilizar la cuenta FTPS ya creada y desplegar exclusivamente el scaffold actual de StockFlash en staging. El propietario confirmó además el cambio de Document Root; su efecto debe comprobarse técnicamente antes de transferir la aplicación completa.
etapa: R2_STAGING
responsable_implementacion: OpenCode conforme al harness vigente; owner + ChatGPT delimitan alcance y declaran VERIFIED.
revision: Gates locales y prueba real de staging, QA independiente y cierre externo. No equivale a autorización de producción.

## Objetivo
Publicar el scaffold R1 ya VERIFIED de Laravel 13.35.0 + Vue 3/Inertia/TypeScript en `https://stockflash-dev.tusitioexpress.cl/`, usando únicamente la cuenta FTPS que el propietario creó. No implementar funcionalidades R3 ni desplegar en producción.

## Decisiones y hechos de partida
- DQ-001: el propietario decidió conservar el hosting cPanel compartido `grupoint`. No reabrir la elección de proveedor ni exigir nuevas cuentas como condición genérica.
- Destino ÚNICO de transferencia de la cuenta FTPS: `/home/grupoint/stockflash-dev-app`. La sesión FTP puede presentar esa carpeta como raíz virtual `/`; NO concatenar la ruta física si así sucede.
- Endpoint: `ftp.tusitioexpress.cl:21`, FTPS explícito con TLS, usuario `admin@stockflash-dev.tusitioexpress.cl`. La contraseña fue facilitada al operador en conversación y **NO** se almacena en Git, este packet, scripts, evidencias, argumentos visibles ni registros.
- El propietario reportó haber cambiado el Document Root del subdominio a `/home/grupoint/stockflash-dev-app/public` el 2026-10-15. Este reporte no prueba por sí mismo que el servidor esté sirviendo ese directorio.
- Document Root anterior, solo como antecedente y **NO como destino autorizado de transferencia**: `/home/grupoint/stockflash-dev.tusitioexpress.cl`.
- CI-001 VERIFIED (PHP 8.3, Node 24, smoke y contratos). GitHub Actions no es un pipeline de deploy. No modificar el SSH general del cPanel ni usarlo para despliegue.
- Base DEV ya creada, versión exacta de MariaDB pendiente; ninguna migración ni seeder R3 autorizado. El scaffold inicial debe arrancar sin datos reales ni nuevas tablas.
- Fecha de aprobación/documentación no acredita despliegue. Estado efectivo del servidor = UNVERIFIED hasta evidencias.

## Alcance de ejecución autorizado
1. Verificar `origin/main` con sync fast-forward y estado local sin descartar modificaciones ajenas. Leer únicamente contratos relevantes, el harness y este packet.
2. Preparar un paquete reproducible del código existente para PHP 8.3: dependencias Composer para producción y `public/build` compilado desde lockfiles. Los artefactos compilados pueden ir en paquete FTPS, **no** al repositorio fuente salvo contrato previo. Identificar SHA del código desplegado.
3. Implementar un transportador FTPS manual/one-shot o procedimiento equivalente, con lista explícita de archivos bajo la raíz FTP restringida. Sin comandos recursivos de eliminación ni sincronizadores que borren automáticamente archivos remotos ajenos.
4. Preflight FTPS: probar autenticación y TLS, listar la raíz virtual y comprobar confinamiento operativo a la carpeta asignada; no explorar ni tocar otros sitios. Registrar únicamente éxitos/errores sin credenciales.
5. Preflight de ruta pública: subir marcador inocuo y temporal **solo** a `public/` bajo la raíz FTPS; comprobar por HTTPS el marcador en `https://stockflash-dev.tusitioexpress.cl/` y retirarlo después. Si falla, detener: no copiar el scaffold ni inventar otro webroot.
6. Antes de sobrescribir archivos bajo la raíz autorizada, inventariar lo existente y preparar un punto de restauración del despliegue anterior. No sobrescribir `.env`, claves, datos de `storage/` ni directorios ajenos. Si no existe mecanismo viable de respaldo/restauración para las rutas que se cambiarán, bloquear la transferencia de aplicación y reportar una alternativa específica.
7. Ejecutar **un único despliegue autorizado** por FTPS dentro de la raíz virtual de `stockflash-dev-app`; contenido `public/` permanece como subdirectorio de Laravel, sin copias a la carpeta antigua. Priorizar secuencia reversible para evitar archivos parciales.
8. Verificar configuración de ejecución que dependa de cPanel sin divulgar secretos. Si se necesita ejecutar un comando PHP remoto y FTPS no puede hacerlo, detener solo ese punto y señalar la acción mínima exacta para el propietario; no usar el SSH general por atajo.
9. Ejecutar smoke HTTPS real: página inicial responde 200, contenido StockFlash, carga de assets Vite y sin errores visibles. Un `403` previo a publicar `public/index.php` no acredita fallo de Laravel, pero `403` tras despliegue requiere diagnóstico.
10. Implementar/verificar gates específicos STAGING-001 dentro del mecanismo vigente; obtener `GATES_PASS` real, QA independiente `APROBADO` con evidencia de servidor, revisión de rutas/rollback y publicación Git ordinaria de scripts/reportes autorizados. El publisher Git y el despliegue FTPS son operaciones distintas: no presentar un push como deploy.

## Gates de aceptación
- G1: `status: APPROVED` y STAGING-001 activo en `docs/work/CURRENT.md`.
- G2: paquete determinista del SHA esperado, con `vendor/`, `public/build/` y archivos necesarios; no incluye secretos ni contenido de usuario.
- G3: FTPS explícito conectado, raíz virtual y lista de destino verificados, sin transferencia a ningún otro directorio.
- G4: marcador público comprobado sobre el subdominio y retirado; evidencia de que `public/` es raíz servida.
- G5: manifest de archivos/respaldos y procedimiento de rollback reproducible antes de modificar aplicación; prueba de restauración inocua o evidencia equivalente.
- G6: smoke real HTTPS 200, Laravel/StockFlash visible y assets resueltos sin error.
- G7: reporte técnico con SHA, inventario de cambios, resultado de cada gate y REVIEW independiente. No declarar VERIFIED por cuenta del agente.
- Ante ausencia de capacidad FTPS, raíz pública, persistencia necesaria o rollback, devolver `BLOCKED` con un único impedimento accionable; no afirmar éxito sin evidencia.

## Exclusiones vigentes
- No desplegar fuera de staging, ni modificar otros sitios, archivos de otras cuentas, cPanel global ni producción.
- No migraciones, seeds, cron/workers productivos, correos reales, DTE ni integraciones externas.
- No modificar contratos v2.2, ADR, `AGENTS.md`, `CONSTITUTION.md`, modelos/agentes o permisos generales del harness.
- No usar SSH de la cuenta general `grupoint` ni añadirlo al pipeline por conveniencia.
- No subir `.env` histórico, credenciales ni datos personales a Git/artefactos/evidencias. El secreto de FTPS se facilita solo al operador local y no se imprime.
- No habilitar deploy automático en cada push ni marcar VERIFIED automáticamente.

## Próximo paso operativo
OpenCode sincroniza la aprobación ya registrada y ejecuta `/sf-run STAGING-001` con el flujo vigente; si el orquestador no delega, mostrar el bloqueo real sin simular gates/QA. Owner + ChatGPT evalúan la evidencia al cierre.
