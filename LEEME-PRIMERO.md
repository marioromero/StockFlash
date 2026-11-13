# StockFlash — LEEME PRIMERO

Este repositorio contiene el contrato documental, el entorno de agentes y el trabajo en curso de **StockFlash** con OpenCode CLI. El scaffold Laravel está versionado en GitHub (`main`), con **BOOT-001 VERIFIED** y publicado. Para el estado vigente consultar `docs/work/CURRENT.md`.

## Fuente de verdad

1. `AGENTS.md` y `docs/governance/CONSTITUTION.md`: reglas de trabajo, permisos y escalamiento.
2. `docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md`: requisitos y reglas de negocio.
3. `docs/product/especificacion-v2.2-parte-2-CONSOLIDADO-EN-REVISION.md`: modelo de datos.
4. `docs/product/especificacion-v2.2-anexo-CONSOLIDADO-EN-REVISION.md`: integraciones y operaciones.
5. `docs/product/StockFlash-Control-SPEC-v0.1.md`: administración central de instalaciones, planes, licencias y suscripciones.

Las tres especificaciones v2.2 están **consolidadas en revisión**, no representan un PUNTO 0 definitivo. Las versiones v2.1 dentro de `docs/product/historico-v2.1/` se conservan exclusivamente para trazabilidad y no sustituyen a v2.2.

## Decisiones vigentes relevantes

- Marca **StockFlash**, gris grafito `#303846` y etiquetas comerciales **POS, Bodega, SyncWP, Fiscal, Compras, Analítica**; **Control** es administración interna.
- Productos contratables de forma independiente; sus componentes técnicos compartidos no dan acceso comercial a otros productos.
- Demo de exhibición y plan gratuito persistente son modalidades distintas.
- Plan gratuito: referencia inicial de **50 variantes vendibles activas** y **500 ventas mensuales**. La semántica de cómputo y bloqueo sigue abierta.
- Suscripciones gestionadas centralmente, con cobro manual inicial.
- POS inicial con inventario y caja; registro de ventas sin integración DTE, con referencia fiscal externa opcional y sin afirmar que un comprobante interno sea un documento tributario.
- Venta en espera recuperable desde la estación correspondiente; interfaz híbrida táctil/teclado/lector.

Revisar `docs/governance/DECISIONES-ABIERTAS-v2.2.md` antes de implementar una función dependiente de esas decisiones. Ningún agente puede inventarlas.

## Estado del proyecto y siguiente trabajo

- Repositorio privado GitHub `marioromero/StockFlash`, rama `main` vinculada localmente.
- OpenCode CLI 1.19.0 instalado y `/sf-diagnostico` ejecutado satisfactoriamente.
- En cPanel se comprobó PHP web 8.3, PHP CLI 8.3.35, Composer 2.8.10, Node.js 20.19.3, npm 10.8.2 y Git 2.56.0.
- Staging `stockflash-dev.tusitioexpress.cl`: HTTPS habilitado; listado de directorios desactivado.
- Rutas de staging: aplicación privada `/home/grupoint/stockflash-dev-app` y raíz pública `/home/grupoint/stockflash-dev.tusitioexpress.cl`.
- Se creó una base de datos DEV separada. El acceso SSH por clave al puerto 2200 fue comprobado.
- Se configuraron variables y secreto de GitHub Actions para staging; el workflow de verificación SSH se incorporó al repositorio.
- **BOOT-001 está VERIFIED y publicado** (scaffold Laravel). Consultar `docs/work/CURRENT.md` para los estados vigentes y las tareas activas.

## Uso con OpenCode

1. Abrir Warp en la raíz del repositorio y ejecutar `opencode`.
2. No ejecutar `/init`: el proyecto ya tiene `AGENTS.md` y configuración propia.
3. Usar `/sf-diagnostico` cuando haga falta diagnosticar un entorno nuevo o un cambio real; no repetir los preflights ya confirmados. Para feature nueva seguir `/sf-plan <ID>` y `/sf-feature <ID>` según su estado APPROVED.
4. No permitir cambios a especificaciones, esquema de base de datos, licencias, reglas fiscales o dependencias sin decisión explícita documentada.
5. No ejecutar migraciones ni desplegar hasta contar con código probado, configuración y plan de despliegue aprobado.

## Seguridad

No guardar contraseñas, tokens, llaves privadas, datos reales de clientes ni archivos `.env` en el repositorio o en prompts. Las credenciales históricamente compartidas deben rotarse antes de una puesta en servicio.
