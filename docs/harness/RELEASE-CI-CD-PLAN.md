# Releases — política objetivo, no automatización ya instalada

**Estado verificado (2026-10-12):** existe `.github/workflows/staging-ssh-check.yml`, disparo manual que verifica huella SSH y ejecuta exclusivamente `echo SSH_OK`. NO existe CI de Laravel, build, deploy ni rollback automatizado. La clave de Actions se autentica como el usuario general `grupoint`; no autorizar despliegues con ella hasta aislar sus privilegios. Se crearán pipelines después del bootstrap.

Flujo objetivo: feature aprobada -> tests locales -> reviewer -> commit/push por pipeline/script de release aprobado -> CI GitHub Actions (o runner local seguro) -> build -> migraciones controladas -> deploy STAGING -> health/smoke -> registrar release.
Producción: tag/release revisado, autorización owner, respaldo+restore comprobado, ventana y rollback según RN-73; nunca automática por cada feature.

Gates mínimos: código verificado, lint, suite PHPUnit/Pest elegida y JS, pruebas de migraciones completas contra MariaDB 10.6+, integridad ARQ-13, secretos y auditorías de dependencias, contraste UI accesible, smoke pruebas al abrir/cerrar caja, venta/idempotencia, integraciones diferidas cuando existan.

GitHub Actions de repositorios privados tiene límites variables de plan/minutos; consultar uso disponible antes de decidir ciclos. No alojar secretos en variables hardcodeadas ni builds descargables públicamente.

**Nota cPanel:** cPanel Domains restringe normalmente document root dentro de `public_html/`; para Laravel, mantener app privada fuera del webroot y disponer solo de archivos públicos en la carpeta web; verificar primero compatibilidad de paths, rewrites y CLI. NO copiar `.env`/vendor ni `app/` a `public_html`.
