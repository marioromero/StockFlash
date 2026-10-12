# Estado de ejecución — StockFlash 2026-10-12

- DOC-001: **corrección documental implementada** (ver `docs/adr/ADR-DOC-001.md`, `docs/governance/AUDITORIA-Y-TRAZABILIDAD-DOC-001.md`). No implica código Laravel terminado ni decisiones ADR-01..06 resueltas.
- Feature activa: **BOOT-001 APPROVED, plan técnico revisado por ChatGPT y owner**. Se autoriza implementar en LOCAL la feature conforme `docs/work/features/BOOT-001.md`; no repetir aprobaciones por cada subtarea ordinaria.
- `/sf-feature BOOT-001` debe proceder a construir Laravel + Vue3 + Inertia + TypeScript desde un scaffold temporal/fusión controlada. La ausencia actual de `artisan`, `composer.json` y `public/` es esperada, NO es motivo de bloqueo.
- OpenCode respeta permisos `ask` de herramientas, colisiones de archivos, nuevas dependencias fuera de alcance, protección de contratos, tests y revisión QA. No commit/push/deploy autónomos todavía.
- Fuente normativa: las tres especificaciones `docs/product/especificacion-v2.2-*CONSOLIDADO-EN-REVISION.md`, Parte 1 > Parte 2 > Anexo; fuentes v2.1 históricas preservadas intactas. Para Control aplicar `StockFlash-Control-SPEC-v0.1.md` exclusivamente en su alcance.
- ADR-01..06 **abiertos**: límites Gratis (50 variantes/500 ventas), permisos Gratis, fiscal externo multireferencia, Fiscal autónomo, suspensión/gracia, Compras/Analítica. No bloquean BOOT-001 ni deben ser inventados.
- Laravel aún no creado. PHP local 8.3.4, Composer 2.8.9, Node24.8.0; cPanel PHP8.3.35, Composer 2.8.10, Node20.19.3. CI proyecta build de assets Node24; el servidor no requiere compilar.
- GitHub Actions actual: verificación SSH manual (`staging-ssh-check.yml`) y comprobación de contratos (`contract-check.yml`, Python). **Sin workflow de tests Laravel, build Vue ni despliegue**. Staging HTTPS y BD DEV creados, nunca utilizados por OpenCode en BOOT.
- **Bloqueo de deploy:** SSH en GitHub autentica como usuario general de cPanel `grupoint`; no usarlo para subir/ejecutar código hasta limitar privilegios y rotar/reemplazar credencial. Rotar otras credenciales compartidas antes de explotación real.
- Próximo paso del propietario, solo tras validación final DOC-001: `git pull --ff-only origin main`, abrir OpenCode y `/sf-feature BOOT-001`. No migraciones ni efectos remotos.
