# Preparación cPanel StockFlash — guía operativa por fases

**No hacer todos los cambios de una sola vez.** Este archivo es el mapa, pero en la conversación se completará cada paso y se pedirá confirmación antes del siguiente.

## Paso 0 — Seguridad inmediata
- Las contraseñas de FTP/BD se compartieron anteriormente. Considerarlas comprometidas y ROTAR antes de desplegar; si la cuenta es usada por algún servicio actual, actualizar dependencias coordinadamente para evitar caída.
- Mantener claves nuevas fuera de chat, Git, herramientas IA y archivos de configuración incluidos en este ZIP.

## Estado real confirmado — 2026-10-12
- Subdominio `stockflash-dev.tusitioexpress.cl` HTTPS operativo; listado del directorio deshabilitado (403 esperado).
- Carpetas creadas: privada `/home/grupoint/stockflash-dev-app`; webroot `/home/grupoint/stockflash-dev.tusitioexpress.cl`.
- PHP web 8.3 y CLI 8.3.35, Composer 2.8.10, Node 20.19.3, npm 10.8.2, Git 2.56.0.
- Base DEV `grupoint_stockflash_dev` y usuario con permisos. Versión exacta MariaDB NO verificada automáticamente.
- SSH puerto 2200, clave autenticada y fingerprint confirmado. En GitHub existen prueba SSH y chequeo documental; **no tests/build Laravel ni deploy**.
- Advertencia: clave SSH en Actions usa cuenta general `grupoint`; no usarla para despliegue sin aislamiento de privilegios.
- Laravel aún no instalado. Rotar contraseñas FTP/DB históricamente compartidas antes de usar servicios reales.

## PASO 1 — Diagnóstico histórico (no repetir pasos ya completados)
Desde **cPanel > Software > MultiPHP Manager**, mirar versiones PHP disponibles y versión asignada al dominio principal. No cambiar todavía: una modificación al dominio actual puede afectar otros sitios.
Desde **cPanel > Domains / Dominios**, verificar que puede crear un subdominio sin compartir document root. No crear aún.
Desde **cPanel > Terminal** (si está habilitada) ejecutar solo lectura:
```
php -v
php -m
command -v php
command -v composer
composer --version
command -v node
node --version
command -v npm
npm --version
command -v git
git --version
pwd
```
Estas salidas no deberían contener contraseñas; revisar antes de compartir. `php -v` CLI puede diferir de la versión PHP web configurada por dominio.
Desde **cPanel > Bases de datos**, ver si existe opción para crear otra DB/usuario separado; no capturar credenciales ni dumps.
**Enviar al asistente:** PHP web disponible/activo, PHP CLI, Composer, Node, Git, si aparece Terminal/SSH, si Dominios deja elegir raíz y qué versión MariaDB muestra phpMyAdmin. No enviar contraseñas, IP privadas, cookies ni tokens.

## PASO 2 — Separar DEV y público (sólo después del diagnóstico)
- Evaluar `stockflash-dev.tusitioexpress.cl` con document root independiente y SSL AutoSSL.
- Definir layout Laravel sin servir directorios privados. cPanel suele obligar a document roots dentro de `public_html`, por lo que el deploy deberá mapear únicamente **contenido de `public/`** al webroot y mantener código fuera; validar rutas concretas antes de escribir configuración.
- Control central privado y staging tienen identidad/DB propias; no usar BD comercial real para pruebas.

## PASO 3 — Entorno y MariaDB
- Elegir versión Laravel estable compatible con PHP WEB Y CLI verificados (Laravel 13 necesita PHP >=8.3 al momento de redactar esta guía).
- DB DEV y usuario de app DEV; usuario read-only separado para MCP (cuando sea necesario). No conexión MCP a BD productiva.
- Confirmar extensiones PHP (pdo_mysql, mbstring, openssl, fileinfo, curl, xml, intl según funcionalidad, etc.), límites de memoria/ejecución, espacio y permisos en directorios `storage` y `bootstrap/cache`.
- Semillas totalmente sintéticas y modo demo dry-run; no enviar correos, DTE o pedidos a servicios reales.

## PASO 4 — Seguridad servidor
- `APP_DEBUG=false` en staging público; secretos fuera de webroot; HTTPS, permisos mínimos, bloqueo de directorio `.git`, logs internos y backups fuera de webroot.
- Protección de acceso staging (usuario/password web o IP allowlist cuando sea viable) sin romper callbacks de pruebas cuando se habiliten.
- Validar cron cada minuto y permisos CLI antes de instalar schedulers, no iniciar daemon persistente en hosting compartido.

## PASO 5 — GitHub y despliegue
- Probar primero vía SSH por clave con usuario restringido. Si no es viable, revisar FTPS explícito con credenciales nuevas, solo como transporte de archivos, sin asumir que puede ejecutar migraciones.
- GitHub secrets y environments según capacidad real del plan. Modelo nunca recibe claves de deploy.
- Checks de CI y pruebas de restauración antes de automatizar despliegue de cada feature a staging.

## PASO 6 — Capacidades P1★ / producción
- Probar recuperación real de DB y XML, control fiscal externo apropiado, MFA, privacidad/retención, costos del hosting y monitoreo. La salida comercial nunca se habilita por el mero hecho de funcionar el POS.

## Documentación de referencia
- cPanel MultiPHP Manager: https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/
- cPanel Domains: https://docs.cpanel.net/cpanel/domains/domains/create-a-new-domain/
- Laravel deployment: https://laravel.com/docs/13.x/deployment (versión por confirmar tras preflight)
