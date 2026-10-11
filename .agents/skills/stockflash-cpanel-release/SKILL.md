---
name: stockflash-cpanel-release
description: Guía de compatibilidad cPanel, staging y controles de release sin exponer credenciales.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-cpanel-release


Consultar `docs/setup/CPANEL-PASO-A-PASO.md` y `docs/harness/RELEASE-CI-CD-PLAN.md`. El agente sólo describe/verifica, no toca cPanel. PHP web puede ser distinto a CLI; Laravel debe servir sólo `public/`, con `.env` privado. Cron único cada minuto + worker acotado en base a Anexo. Deploy automatizado sólo vía pipeline sin acceso del modelo a credenciales, tras gates. Producción requiere decisión explícita.
