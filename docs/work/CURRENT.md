# Estado de ejecución — 2026-10-12

- Feature autorizada: **BOOT-001** (`docs/work/features/BOOT-001.md`), aprobada por el propietario.
- Estado: **APPROVED — pendiente de planificación y ejecución local**.
- Fase: bootstrap Laravel + Vue 3 + Inertia, sin implementar aún dominios de negocio.
- Contrato vigente: 3 documentos `docs/product/especificacion-v2.2-*CONSOLIDADO-EN-REVISION.md`; `StockFlash-Control-SPEC-v0.1.md` para Control.
- Las versiones v2.1 en `docs/product/historico-v2.1/` solo son históricas.
- ADR pendientes: `docs/governance/DECISIONES-ABIERTAS-v2.2.md`; BOOT-001 no debe decidirlos.
- Entorno verificado: Warp Windows, PHP local 8.3.4, Composer 2.8.9, Node 24.8.0, OpenCode CLI 1.19.0. cPanel PHP 8.3.35, Composer 2.8.10, Node 20.19.3, SSH por clave puerto 2200.
- GitHub remoto `main` establecido, workflow de prueba SSH instalado. Aplicación Laravel todavía no creada.
- Próxima acción: sincronizar repositorio local (`git pull --ff-only origin main`) y ejecutar **`/sf-plan BOOT-001`** en OpenCode para revisar plan; NO invocar implementación hasta revisión del plan.
- No commits, push ni deploy de agentes; staging y BD intactos. No ejecutar migraciones.
