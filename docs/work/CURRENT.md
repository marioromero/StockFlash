# Estado operativo — StockFlash (2026-10-14)

## Hitos cerrados y estado vigente
- **R0 DOC-001:** base documental y contrato v2.2 preparado, sujeto a ADR-01..06 abiertos y controles pendientes antes de dominio.
- **R1 BOOT-001: VERIFIED** el 2026-10-14; Laravel 13.35.0 + Vue 3 + Inertia 3.5.1 + TypeScript/Vite. Gates `BOOT-001-VERIFY-20261013-234609.md` PASS; QA `BOOT-001-REVIEW-20261013-235005.md` APROBADO; scaffold remoto `bcbf16f`. No deploy ni migraciones.
- **HARNESS-001: VERIFIED del entregable** (2026-10-14), comandos `/sf-run`, `/sf-verify`, `/sf-review`, `/sf-publish` y segregación implementer/reviewer/publisher. No se atribuye ejecución E2E completa si la evidencia no la demuestra. La invocación directa del coordinador encontró una restricción Task durante BRAND-001; comprobar delegación nativa real en CI-001, sin relajar verificaciones.
- **BRAND-001: VERIFIED del prototipo SVG provisional** (2026-10-14). Ocho assets publicados, última corrección `cdc993f`; GATES_PASS `VERIFY-20261014-173659`, QA APROBADO `REVIEW-20261014-174500`, conformidad del propietario al avanzar. Los SVG NO son oficiales ni se usan como fuente primaria: mantener fallback textual; PNG oficiales prevalecen.

## Feature activa
- **CI-001: APPROVED — implementación publicada, aún NO VERIFIED.** Commit `c39e0cc2c04ebb9bea768f961c6f2cea3cdccc0b`; GATES_PASS C1–C3, QA APROBADO. Primera corrida de CI GitHub Actions (`38644443485`) y workflow de contratos (`38644442118`) finalizaron `startup_failure`, con **0 jobs**, sin ejecutar PHP, Node o tests. Patrón afecta 47 de 48 runs consultados, y comenzó el 2026-10-12 antes de CI-001. Pendiente diagnóstico de GitHub Actions en cuenta/repositorio (billing/pagos/presupuesto/política/soporte); no afirmar éxito ni cerrar CI-001 hasta ejecución verde real.
- **Próxima subentrega de R2:** staging seguro, solo tras revisar credenciales de menor privilegio, SSH y rollback. No confundir CI aprobada con deploy autorizado.
- R3 Core (usuarios/RBAC, sucursales, licencias técnicas, catálogo y BD) empieza después de puertas R2 correspondientes; ADR de dominio continúan abiertos.

## Reglas operativas
- `main` y `origin/main` con sincronización fast-forward mediante `scripts/safe_git_pull.py`; ante `SYNC_BLOCKED` no descartar trabajo local. Al cargar comandos actualizados reiniciar OpenCode si la sesión conserva una versión anterior.
- Agentes: `default_agent: build`, autorización Task exacta `stockflash-orchestrator`, `subagent_depth: 2` para permitir Build → coordinador → implementer/reviewer/publisher. Implementer y reviewer conservan prohibición de invocar coordinador y publisher. Comprobar E2E en feature futura; no declarar probado solo por configurar.
- Feature Packet `APPROVED` + activa → implementación → `GATES_PASS` → QA independiente `APROBADO` → publisher selectivo sin force → verificación SHA. `VERIFIED` únicamente por decisión externa, no por el agente. El modo `--auto` aprueba `ask`, pero no anula `deny`.
- Prohibido leer/publicar secretos, sobrescribir contratos protegidos, ejecutar migraciones o desplegar sin autorización específica. Mantener `tmp-boot-001/` ignorado, sin borrado automático.
- Contractos vigentes: `docs/product/especificacion-v2.2-*-CONSOLIDADO-EN-REVISION.md` (Parte 1 > Parte 2 > Anexo); `docs/branding/brand-contract.md` y `docs/branding/module-identity-map.md` preceden al inventario SVG.

## Infraestructura y bloqueos reales
- CI contractual `.github/workflows/contract-check.yml` y CI PHP/Node `.github/workflows/ci.yml` implementadas; ambas están BLOQUEADAS actualmente por `startup_failure` de GitHub Actions (0 jobs), antes de ejecutar pruebas. Diagnóstico SSH manual `.github/workflows/staging-ssh-check.yml` no constituye pipeline de despliegue.
- Staging HTTPS y DB DEV reportados, **no utilizados** para BOOT ni BRAND. Versión exacta de MariaDB no verificada.
- **Bloqueo de deploy:** credencial SSH del usuario general cPanel `grupoint`; no emplearla para publicar/ejecutar código hasta credencial de privilegio mínimo, rotación y hardening, respaldo/rollback y gate de autorización.
- Roadmap canónico: `docs/harness/ROADMAP-STOCKFLASH.md`. R2 es el siguiente hito formal; CI-001 es una feature dentro de R2, no una fase nueva.
