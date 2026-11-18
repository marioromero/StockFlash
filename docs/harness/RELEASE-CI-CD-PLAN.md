# Release and CI boundaries — no automatic deployment

**The historical "CI not installed" line from 2026-10-12 is obsolete.** Current GitHub CI PHP/Node/smoke/contracts exists; verify actual runs by exact SHA. This is the governing release boundary, not permission to deploy.

- Local approved feature development: code/tests/QA with no automatic remote DB changes.
- Git publishing: `stockflash-publisher` alone selects files and commits/pushes `origin/main` after GATES_PASS + independent APROBADO.
- CI: GitHub Actions tests code and documentation on pushed SHA; does not deploy, migrate or seed.
- Staging deployment: **only upon explicit owner instruction** through the separately controlled `/sf-deploy-staging` pipeline and staging packet. Never inferred from the word "publish". No use of old general SSH credential.
- Production deployment: independent owner approval, backup/restore verified, rollout window, minimal privileges, health checks and rollback path. Automated Git push is not production approval.
- Keep Laravel private application files outside the public document root; expose only `public/`. Protect .env, binaries, backups and secrets. Node build occurs in CI/local, not necessarily cPanel.
- Do not infer PHP/MariaDB runtime validation from SQLite CI, or fiscal/legal readiness from a UI mock.

The approved core targets: PHP 8.3+, Laravel 13, Vue 3/Inertia/TypeScript/Vite, MariaDB 10.6+ with ARQ-13 predeploy validation, one scheduler cron per minute and bounded database workers. See STACK-Y-COMPATIBILIDAD.md.
