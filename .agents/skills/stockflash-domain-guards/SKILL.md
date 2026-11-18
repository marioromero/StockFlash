---
name: stockflash-domain-guards
description: Protect inventory, cash, sales, reservations, idempotence and integration boundaries.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-domain-guards

Read only applicable RN-15..57 and approved packet. Physical stock movements immutable; reservations are distinct from stock; transactions and locks protect inventory, cash and split payments; external side effects are idempotent. WooCommerce is a channel adapter, not stock's authority. Do not introduce a new stock/price/fiscal source of truth or expand P0/P1/P2 scope silently.
