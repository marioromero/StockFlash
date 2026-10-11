---
name: stockflash-pos-ux
description: Contrato ergonómico para terminal POS híbrido táctil, lector, teclado, split pay y pausas por estación.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-pos-ux


Consultar Parte 1 POS-01..POS-08, SAL-02/03/05/08 y §7.3/7.6, más `docs/governance/DELTAS-PENDIENTES.md` para límites abiertos. Grilla/lista configurable, búsqueda foco compatible lector, carrito estable; modal PIN y cobro sin abandonar venta. Venta en pausa sólo se recupera desde misma estación; no confundir `held` sin reserva con ticket pendiente que reserva. Pagos parciales por método y saldo pendiente, sin doble confirmación. No simular soporte offline (P3). No implementar delta abierto de persistencia sin ADR.
