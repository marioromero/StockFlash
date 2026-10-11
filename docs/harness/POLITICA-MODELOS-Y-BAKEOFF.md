# Política de modelos gratuitos — pendiente de medición
No fijar IDs de modelo a ciegas. OpenCode usa `proveedor/id-exacto`; comprobar `/models` en instalación actual.
Candidatos declarados disponibles por el usuario: Muse Spark 1.3 Free, LongCat 2.5 Preview Free, MiMo-V2.6-Flash Free, Nemotron 3.5 Lightning Free. El usuario acepta condiciones de uso de Muse, pero NO autoriza envío de secretos ni datos de clientes.

## Benchmark práctico (0 datos reales)
Usar mismas specs breves y fixtures sintéticos para 3 tareas independientes: (1) navegación/contrato UI pequeña, (2) test de precio/idempotencia con mock, (3) encontrar un defecto sembrado o revisión de diff ficticio. Elegir después de que exista esqueleto mínimo; no gastar tokens en cuatro implementaciones completas del producto.
Para cada modelo: versión e ID, tipo de tarea, tokens entrada/salida, wall time, tests logrados, errores, intervención humana, tamaño de diff y nº de reintentos. Selección por **menor costo de contexto por feature aprobada**, no por publicidad.
Asociar modelo fijo a cada agente/feature; si cambia la disponibilidad: guardar checkpoint y reanudar con nuevo modelo desde Feature Packet, NO fallbacks automáticos silenciosos.
No «reciclar» cookies de servicios ni eludir cuotas/condiciones con routers; usar proveedores oficiales y quotas permitidas.

## Estado
No se ha ejecutado bake-off. Recomendación preliminar sin garantía: Muse implementer, MiMo reviewer, LongCat para grandes contextos, Nemotron tareas auxiliares no sensibles.
