# Constitución de desarrollo StockFlash v1.1

## Precedencia obligatoria
1. **Contrato de trabajo v2.2** Parte 1 (negocio) → Parte 2 (persistencia) → Anexo (integración) de `docs/product/`.
2. `docs/product/StockFlash-Control-SPEC-v0.1.md` rige exclusivamente la aplicación central, subordinado a restricciones v2.2 y seguridad.
3. ADR aprobados posteriores pueden corregir puntual y expresamente v2.2, nunca de manera tácita.
4. Feature Packets aprobados ejecutan el contrato, no pueden modificarlo.
5. v2.1 en `docs/product/historico-v2.1/` es referencia histórica; **no prevalece sobre v2.2**. `MEMORY.md` y Engram son solo ayuda de contexto.
6. Las secciones marcadas **ABIERTAS** en v2.2 requieren ADR. No completar silenciosamente alcance, semántica de límites o política fiscal.

## Invariantes
- Una instalación independiente por cliente, un único esquema operacional por instalación; Control central tiene esquema separado y jamás accede directamente a ventas individuales.
- Los productos comerciales se contratan por separado; sus dependencias técnicas se resuelven sin compra forzada de otro producto. Servidor verifica entitlements comerciales, RBAC, activación y estado operativo.
- Kardex físico consistente, reservas independientes, eventos idempotentes, concurrencia controlada y DTE solo por autoridad fiscal integrada cuando está habilitada. En modo externo el comprobante interno no es documento tributario.
- Demo Vitrina reseteable y sintética; Gratis Persistente no reseteable, con datos reales y límites locales.
- No alterar privacy/security/licencia con telemetría comercial adicional; LIC-02 conserva lista blanca exacta. Sin secretos en prompts, agentes ni logs.
- Diseño corporativo constante y accesible, uso del PNG oficial StockFlash y etiqueta activa. Owner+ChatGPT gobiernan diseño y producto, OpenCode implementa y prueba.
- No modificar contratos consolidados, esquemas adicionales ni dependencias críticas sin nueva revisión y ADR. Implementación por Feature Packet pequeño y validación independiente.
- Staging/producción separados; CI comprueba diffs, pruebas y seguridad antes de desplegar. Producción requiere respaldo y aprobación humana. Nada de push ni deploy autónomo directo por agente en bootstrap.
