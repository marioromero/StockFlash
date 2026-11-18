# Laravel architecture guidance — approved v2.2 mapping

Stack: PHP 8.3+ / Laravel 13, Vue 3, Inertia 3, TypeScript/Vite, MariaDB InnoDB. Register modules, avoid generic public API; expose only necessary authenticated routes/webhooks. WooCommerce and Fiscal integrate through decoupled contracts, idempotent jobs and bounded database queue/scheduler on cPanel. UTC storage and America/Santiago display where specified. RBAC/PII controls are server-side.

Use approved `app/Domain/<Module>` and `app/Integrations/<Adapter>` patterns where present; do not forcibly refactor working code. Shared backoffice and POS shells with approved brand tokens; no heavy unapproved UI dependencies or duplicated styles. Prefer Laravel/Vue built-ins to new packages. Lock dependencies with Composer/npm lockfiles, validate support for cPanel PHP 8.3 and local/CI Node; no server-side Node build requirement.

New dependencies require specific justification (purpose, alternatives, license, maintenance, security and hosting compatibility). No extra database columns/tables absent a packet/contract/ADR. Run tests and validate MariaDB 10.6+ / ARQ-13 before production as separate authorized work.
