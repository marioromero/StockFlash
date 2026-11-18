---
name: stockflash-cpanel-release
description: Check hosting and staged-release compatibility without revealing credentials.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-cpanel-release

Read docs/setup/CPANEL-PASO-A-PASO.md and the live release policy only for an explicitly authorized deployment review. PHP web and CLI may differ; only public/ may be served. One cron every minute with bounded database queue workers. Never touch cPanel, run migrations/deploy or read credentials without separate explicit owner authorization. Git publication alone is not deploy permission.
