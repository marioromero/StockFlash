# StockFlash milestone roadmap — evidence-based gates, no promised dates

| Stage | Deliverable | Required gate |
|---|---|---|
| R0 DOC-001 | Coherent v2.2 business/data/integrations, indexed IDs and contract hashes | `scripts/check_contracts.py`, CI contract workflow; keep open ADR visible |
| R1 BOOT-001 | PHP 8.3+/Laravel 13, Vue3/Inertia/TypeScript and text brand shell | Composer validation, PHP tests, Node build and GET / es-CL; no real DB bootstrap |
| R2 CI / staging readiness | GitHub PHP/Node24/docs CI, smoke, isolated staging transport | Exact SHA CI; distinct approved deploy, least-privilege credentials, rollback |
| R3 Core | Installer, setup, auth/RBAC, users, branches, settings, license core, catalog base | Secure access, approved migrations schema tests, MariaDB 10.6+ predeploy |
| R4 Bodega | Products, variants, physical Kardex, balances, adjustments, transfers, reports | Locking/idempotence/stock consistency, permission/UX review |
| R5 Control | Independent central customers/plans/Ed25519 licenses, manual billing, demo/free controls | ADR-01/02/05 and central contracts before commercial claims |
| R6 POS without integrated DTE | Sales, tickets, cash, split tender, returns, station-held carts | ADR-03 if affected; transactional cash/stock, no fiscal_documents from external-mode sale |
| R7 First commercial pilot | Sellable POS+Bodega with subscription control, backups and security | Restore, privacy, authorization, fiscal external/legal validation, pilot readiness |
| R8 SyncWP | Woo REST/webhooks/polling, product/stock/order/refund integration | Replay, reconciliation and idempotent error handling |
| R9 Fiscal | Authorized DTE adapter, XML, credit notes, fiscal documents | Legal/tax contract, retries/reconciliation, retention/backups and ADR-04 where needed |
| R10 Compras / Analítica | Independent future commercial products | ADR-06, separate scope and quality gates |

R5 may overlap R4/R6 after central contracts are fixed; CI can exist before separate staging deployment privileges. R7 is not reached merely because a demo works. **Current actual progress comes from `docs/work/CURRENT.md`, not from this planned row order.** OpenCode autonomous execution covers only APPROVED packets and safe Git, not new ADRs or deployment.
