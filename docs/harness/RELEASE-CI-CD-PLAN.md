# Releases — política objetivo, no automatización ya instalada

## 1. Reglas permanentes (desarrollo local-first)

- Todo desarrollo de funcionalidades se ejecuta localmente. La BD DEV remota sigue autorizada bajo sus condiciones vigentes.
- GitHub (`origin/main`) es el repositorio de código, no el servidor de ejecución de la aplicación.
- Los commits y el push NO autorizan despliegue. Staging se actualiza exclusivamente cuando el propietario solicita `/sf-deploy-staging`.
- No ejecutar migraciones ni desplegar sin instrucción expresa del propietario.

## 2. Capas distinguidas

| Capa | Qué hace | Cómo se activa | Automatización vigente |
|------|----------|----------------|------------------------|
| Desarrollo local | Code, tests, gates, seeds en BD DEV remota | Cada prompt del propietario | Scripts locales (`sf_verify.py`, `sf_db.py`) |
| Publicación Git | `git add` selectivo, `git commit`, `git push origin main` sin force | `/sf-publish` tras gates + review | `stockflash-publisher` (operación A) |
| CI GitHub Actions | Verificación de especificaciones, índice y hashes (`contract-check.yml`); huella SSH manual (`staging-ssh-check.yml`) | Push a `main` | `.github/workflows/*` (no es pipeline de deploy) |
| Despliegue opcional a staging | ZIP único vía API de cPanel hacia `/home/grupoint/stockflash-dev-app` | Solo `/sf-deploy-staging` por instrucción del propietario | `scripts/sf_deploy_staging.py` (manual, no automático) |

## 3. Estado verificado (2026-10-12)

Existen `.github/workflows/staging-ssh-check.yml` (manual: huella SSH + `echo SSH_OK`) y `.github/workflows/contract-check.yml` (verifica especificaciones, índice y hashes). NO existe CI de Laravel, build Vue, deploy ni rollback automatizado. La clave de Actions se autentica como el usuario general `grupoint`; no autorizar despliegues con ella hasta aislar sus privilegios. Se crearán pipelines después del bootstrap.

## 4. Flujo objetivo por feature

Feature aprobada -> tests locales -> reviewer -> commit/push por pipeline/script de release aprobado -> CI GitHub Actions (o runner local seguro) -> build -> migraciones controladas -> deploy STAGING (solo si el propietario lo ordena) -> health/smoke -> registrar release.

Producción: tag/release revisado, autorización owner, respaldo+restore comprobado, ventana y rollback según RN-73; nunca automática por cada feature.

## 5. Gates mínimos

Código verificado, lint, suite PHPUnit/Pest elegida y JS, pruebas de migraciones completas contra MariaDB 10.6+, integridad ARQ-13, secretos y auditorías de dependencias, contraste UI accesible, smoke pruebas al abrir/cerrar caja, venta/idempotencia, integraciones diferidas cuando existan.

## 6. Límites de GitHub Actions

Repositorios privados tienen límites variables de plan/minutos; consultar uso disponible antes de decidir ciclos. No alojar secretos en variables hardcodeadas ni builds descargables públicamente.

## 7. Nota cPanel

cPanel Domains restringe normalmente document root dentro de `public_html/`; para Laravel, mantener app privada fuera del webroot y disponer solo de archivos públicos en la carpeta web; verificar primero compatibilidad de paths, rewrites y CLI. NO copiar `.env`/vendor ni `app/` a `public_html`.
