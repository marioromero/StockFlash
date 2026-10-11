---
name: stockflash-domain-guards
description: Protege operaciones de stock, caja, venta, reserva, idempotencia y fronteras de integración.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-domain-guards


Validar RN-15..32, RN-33..37, RN-38..48 y RN-49..57 sólo según alcance. Kardex físico inmutable; reservas independientes; pagos e inventario transaccionales; 2 trabajos no duplican cambios. WooCommerce es canal, no dominio. No alterar fuente única stock/precios/DTE ni reutilizar efecto externo sin dedupe. Conservar límites fase P0/P1/P2.
