---
name: stockflash-database-contract
description: Validate schema and MariaDB invariants against the approved Part 2 model.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-database-contract

Read only the involved Part 2 §1.2 migration batch and exact table. Preserve FK/UNIQUE/DECIMAL/timezone/append-only/generated-column constraints. ARQ-13 requires MariaDB 10.6+ verification before first deployment; SQLite-only tests do not prove it. Unapproved columns/indices require a concrete owner decision. Never connect production/remote DB or execute real migrations from normal agent work.
