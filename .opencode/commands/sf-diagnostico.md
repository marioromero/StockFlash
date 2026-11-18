---
description: Read-only environment diagnosis only when a new or changed environment warrants it.
agent: plan
---

Do a small read-only check of repository path/branch, known local PHP/Node/OpenCode version, installed Laravel scaffold and relevant governance/active packet. No recursive reread of everything, no repeated preflight when earlier verified evidence still applies. If there is no APPROVED active packet, say implementation is not authorized. Do not modify, install, commit, push, deploy, read .env/secrets or run migrations.
