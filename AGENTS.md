# StockFlash — reglas de agentes (v1.1, contratos integrados)
1. Comunicación y UI en español es-CL; código/tablas en inglés; nunca tocar producción.
2. Contratos de trabajo prioritarios **v2.2**: negocio=`docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md`; datos=`docs/product/especificacion-v2.2-parte-2-CONSOLIDADO-EN-REVISION.md`; integración=`docs/product/especificacion-v2.2-anexo-CONSOLIDADO-EN-REVISION.md`. Los tres documentos v2.1 en `docs/product/historico-v2.1/` son evidencia histórica, NO instrucciones vigentes si difieren.
3. v2.2 consolida decisiones comerciales del owner (demo vs free, derechos por producto, fiscal externo, ventas pausadas, branding). No interpretarlas como simples propuestas: están aprobadas. **Las partes rotuladas ABIERTAS requieren una decisión futura y no se implementan por intuición.**
4. `docs/governance/CONSTITUTION.md` define precedencias; `docs/governance/DECISIONES-ABIERTAS-v2.2.md` delimita bloqueos. `docs/requirements/INDEX-REQUISITOS.md` localiza IDs.
5. Solo implementar una Feature Packet aprobada en `docs/work/features` y señalada en `docs/work/CURRENT.md`. Leer secciones precisas; evitar cargar las 3 specs completas en cada tarea.
6. StockFlash + etiqueta comercial activa (POS/Bodega/SyncWP/Fiscal/Compras/Analítica/Control). Caja/Clientes/Precios son funciones internas, nunca productos vendidos por separado.
7. Un cliente no necesita CONTRATAR otro producto para usar uno habilitado. Se activan servicios técnicos compartidos; la autorización exige licencia comercial + capacidad + RBAC en servidor.
8. Dos modalidades: Demo Vitrina = ficticia y reiniciable; Gratis Persistente = datos reales no reseteables, 50 variantes vendibles y 500 ventas/mes; NO inventar qué ventas computan o política al agotarse el cupo (ADR pendiente).
9. En POS modo externo, una venta cobra, actualiza inventario y caja SIN DTE integrado ni folio obligatorio. No afirmar emisión fiscal ni registrar documento emitido automáticamente.
10. Marca PNG oficiales en `public/images/brand/`: si no existen, usar identidad textual provisional y no inventar imágenes.
11. PII/RUT/secretos nunca en Engram, prompts, repositorio ni logs. No leer `.env` reales, dumps ni claves SSH.
12. Probar idempotencia, permisos, concurrencia, caja, límites y stock por transacción cuando corresponda. Nunca declarar terminado sin pruebas.
13. Cambios de reglas, columnas, contratos y dependencias no previstos en v2.2 requieren propuesta/ADR. Agentes implementadores no modifican estos contratos.
14. Dudas: registrar solicitud de decisión (`docs/work/DECISION-REQUEST-TEMPLATE.md`) y detener SOLO la pieza afectada; owner+ChatGPT responden fuera de OpenCode. No existe conexión automática a este chat.
15. No push, deploy, migraciones remotas, DB destructiva ni acceso a producción desde agentes. CI/deploy solo tras gates y credenciales aisladas.
16. Skills bajo demanda `.agents/skills`; MEMORY es consultiva, no override. `LEEME-PRIMERO.md` + `/sf-diagnostico` al iniciar. No ejecutar `/init`.
