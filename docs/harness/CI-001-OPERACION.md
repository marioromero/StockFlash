# CI operation — PHP/Node and contract checks, no deploy

Live GitHub CI is triggered by push to main, pull_request and workflow_dispatch. Workflow `.github/workflows/ci.yml` checks PHP Composer/test, Node24 typecheck/build and an es-CL GET / smoke. Separate documentation contract/hash workflow checks approved docs. **Both must be SUCCESS on the same exact SHA** before closing a feature; never infer status from previous SHA or old local tests.

Workflow Git permissions are read-only; no deployment credentials or database migration. Node/PHP build artifacts are validation evidence, not production deployment. The smoke job may be skipped/blocked if a dependency job fails: inspect the failing job first.

For local CI checks use `py -3 scripts/sf_verify.py CI-001`; per-feature gates live in `scripts/sf_verify.py` and require matching packet/fingerprint. If composer/npm lock validation fails, repair the lock coherently and rerun. If smoke fails, inspect only relevant server log excerpt. Never introduce real .env/secrets, production DB or SSH credentials to "fix CI".

The historical October note that PHP/Vue CI was not yet installed is superseded by the tested workflow and current CURRENT.md.
