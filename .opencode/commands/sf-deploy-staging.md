---
description: Owner-triggered cPanel staging deployment only; never part of ordinary feature work.
agent: build
---

**Requires a distinct explicit owner staging deployment instruction.** This command must never run via `ok`, publication, tests or token-audit activation. It does not implement features, approve QA or declare VERIFIED.

When specifically authorized, follow `docs/work/STAGING-001-DEPLOY-AUTO.md`, `docs/harness/RELEASE-CI-CD-PLAN.md`, and checked `STAGING-001` packet, credentials stored outside Git. Existing ZIP/cPanel API flow uses `scripts/sf_deploy_staging.py --check`, then a scoped `--probe`, then `--deploy` and health/review only if preconditions and privileges are confirmed. Never use general user SSH credentials, read/print secrets, run migrations/seeds, alter global cPanel accounts or widen permissions to suppress a deny. Block on failures with documented recovery; no silent deploy/rollback assumptions.

The local development feature flow is separate. Stage/production permission is NOT inherited from AUTO_GIT or token audit. All instructions English; owner-visible status in Spanish.
