# Agent and secret security — active principles, historical notes clarified

Permission configuration is defense in depth, **not an operating-system sandbox**. Read actual `opencode.json` and role-specific permissions for effective actions; do not assume markdown denies can block shell access. Runtime permission behavior is version dependent and needs focused testing before a security config migration.

- Keep real .env, credentials, client records, SSH keys, RUT, database dumps and production mounts outside agent reach. Agents may handle synthetic test configs only.
- Nonpublisher agents never push, and the publisher uses only exact `git push origin main` after matching final gates and independent QA. No reset/stash/clean or forced history.
- Safe Git sync is `scripts/safe_git_pull.py`, once per active orchestration run, with SYNC_BLOCKED preserving all work.
- Owner approval required for real DB operations, migration/seeding, deployment, production, new commercial/security policies and destructive changes.
- Never bypass a permission deny by opening shell/alternative tool. Check API permissions and secrets at the OS level. Use synthetic staging data and APP_DEBUG=false for any public environment.
- An historical blocker involved a general cPanel SSH user (`grupoint`), unsafe for unattended deploy. Treat it as blocked unless current owner-approved least-privilege credentials are independently verified. No old secret reuse.
- Stop on exposed secrets, real customer data, critical gate failure, unapproved scope, or missing backup before irreversible operations.
- Audit logs are sanitized metadata, not a license to record raw commands with sensitive arguments or private model reasoning.

**Known issue to test:** the baseline opencode.json allowed reading `*.env`, despite a strict policy against it. Tightening patterns must be verified against actual OpenCode permission precedence, especially to avoid breaking synthetic .env.example access or publisher push. See HARNESS-AUDIT-20261117.md.
