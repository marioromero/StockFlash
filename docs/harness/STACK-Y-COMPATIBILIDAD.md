# Stack de referencia verificado — DOC-001 (2026-10-12)

- Backend: PHP 8.3+ (Windows 8.3.4; cPanel web 8.3, CLI 8.3.35). Elegir versión Laravel **estable compatible** en BOOT-001 y fijarla mediante `composer.lock`. No asumir versión exacta antes de Composer.
- Frontend: Vue 3, Inertia, TypeScript y Vite. Aprobados por el propietario para el bootstrap; no usar kits de autenticación o UI sin Feature Packet.
- Local: Node 24.8.0 y npm 11.6.0. cPanel: Node 20.19.3/npm 10.8.2, sin obligación de compilación en servidor.
- CI objetivo: Node 24 LTS para compilar y publicar `public/build` con manifest; PHP tests, seguridad y dependencias validados antes de artefacto.
- BD objetivo MariaDB InnoDB 10.11 LTS recomendado / mínimo 10.6 por contrato; no se ha verificado automáticamente la versión exacta del hosting.
- La aplicación privada vive fuera de document root, y solo el contenido de `public/` se expone en staging. No copiar .env, llaves, backups o vendor al webroot.

## Gates BOOT-001
`composer validate`, lockfiles, tests PHP y ruta Inertia sin DB, `npm run build`, evidencia navegador, revisiones sobre diff. No migraciones, ni despliegue. No instalar herramientas no requeridas.
