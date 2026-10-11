# Decisiones ABIERTAS v2.2 — no inventar implementación

Estas preguntas no niegan decisiones del owner: **500 ventas/mes está aprobado**, pero su semántica exacta aún no.

| Ref | Cuestión que debe decidir el propietario | Lo que sí está aprobado | Consecuencia |
|---|---|---|---|
| ADR-01 | Para el cupo mensual: ventas anuladas/reversadas, tickets pendientes, importaciones Woo, ventas pagadas parcialmente, período calendario y huso horario, y qué ocurre al alcanzar cupo durante el cobro | `free=500` ventas/mes, otros planes configurables | No implementar bloqueo del checkout ni contador de concurrencia sin definición |
| ADR-02 | Catálogo EXACTO de capacidades básicas Gratis Persistente y diferencia entre acceso a POS y Bodega | `free`: 50 variantes activas y 500 ventas/mes, persistente | No vender/mostrar capacidades no especificadas |
| ADR-03 | Si el operador declara varios documentos fiscales externos para una venta y cómo registrar pago mixto + voucher y conciliación | Referencia voluntaria, sin necesidad de folio; venta se confirma sin motor DTE | No declarar validación ni completar flujo multireferencia automáticamente |
| ADR-04 | Soporte para emitir documentos manuales con Fiscal como producto autónomo, si no existe venta en POS/SyncWP | Ningún producto obliga comercialmente a otro | No prometer Fiscal autónomo completo sin contrato de entrada |
| ADR-05 | Comportamiento comercial de bloqueo/suspensión: vencimiento, gracia, aviso y continuidad operativa del comercio | Sin borrado de datos; licencia stale offline conserva comportamiento aprobado LIC-04 | No imponer nuevos cortes operativos arbitrarios |
| ADR-06 | Alcances finales de Compras y Analítica en lanzamiento | Serán productos independientes cuando se implementen | No confundir módulos P2 con funcionalidad entregada |

**Regla de avance:** el bootstrap, diseño base, auth, inventario estructural y despliegue local pueden comenzar. Una feature que depende de cualquiera de estas decisiones queda bloqueada solo en ese aspecto, no detiene todo el proyecto.
