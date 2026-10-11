# Releases — política objetivo, no automatización ya instalada

**No existen workflows ni scripts de despliegue en este paquete.** Deben generarse y probarse cuando el esqueleto Laravel, la cuenta staging y los secretos rotados estén listos.

Flujo objetivo: feature aprobada -> tests locales -> reviewer -> commit/push por pipeline/script de release aprobado -> CI GitHub Actions (o runner local seguro) -> build -> migraciones controladas -> deploy STAGING -> health/smoke -> registrar release.
Producción: tag/release revisado, autorización owner, respaldo+restore comprobado, ventana y rollback según RN-73; nunca automática por cada feature.

Gates mínimos: código verificado, lint, suite PHPUnit/Pest elegida y JS, pruebas de migraciones completas contra MariaDB 10.6+, integridad ARQ-13, secretos y auditorías de dependencias, contraste UI accesible, smoke pruebas al abrir/cerrar caja, venta/idempotencia, integraciones diferidas cuando existan.

GitHub Actions de repositorios privados tiene límites variables de plan/minutos; consultar uso disponible antes de decidir ciclos. No alojar secretos en variables hardcodeadas ni builds descargables públicamente.

**Nota cPanel:** cPanel Domains restringe normalmente document root dentro de `public_html/`; para Laravel, mantener app privada fuera del webroot y disponer solo de archivos públicos en la carpeta web; verificar primero compatibilidad de paths, rewrites y CLI. NO copiar `.env`/vendor ni `app/` a `public_html`.
