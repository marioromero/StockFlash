# StockFlash — reglas de agentes (v1.1, contratos integrados)

**Adenda BRIDGE-001 aprobada por el propietario, 2026-11-12 (piloto local):**
Codex CLI sucede a ChatGPT en revisión técnica externa, preparación de packets,
autoría del manual `public/docs/index.html`, autoría íntegra del roadmap
`resources/roadmap/index.html` y mantenimiento de `CONTEXTO-CHATGPT.md`.
Esta decisión actualiza las atribuciones de los puntos 14 y 17–19; OpenCode
conserva sus límites editoriales, coordinador, QA independiente y publisher.
Los avisos `*_PENDIENTE_CHATGPT` existentes son compatibles como pedidos a Codex.
Mientras el puente esté bloqueado no existe entrega automática operativa.
BRIDGE-001 está autorizado solo en el piloto, sin publicación funcional,
migraciones, deploy ni modificaciones al original. Los cambios administrativos
de StockFlashAgent y sus ACL están autorizados por la decisión posterior del owner.
No invocar agentes sobre código hasta demostrar aislamiento efectivo.
Activación normal y VERIFIED requieren decisión externa; USERS-001 sigue DRAFT.

**Excepción documental puntual (solicitud posterior del propietario, 2026-11-12):**
publicar únicamente los Markdown del avance BRIDGE-001 en
`pilot/codex-bridge-20261112`, mediante commit selectivo y push sin force desde
Codex. Esta entrega de documentos queda fuera del publisher limitado a main;
no publica código, no concede GATES_PASS/QA/VERIFIED ni habilita automatización.
Se mantiene prohibido publicar en main o desplegar. Ejecutar agentes sobre código
solo dentro de StockFlashAgent tras comprobar el aislamiento real.

**Autorización documental continuada del propietario (solicitud posterior):**
durante BRIDGE-001, publicar las actualizaciones Markdown de este trabajo en
`pilot/codex-bridge-20261112` para consulta dinámica desde GPT, sin pedir aprobación
por cada entrega. Mantener selección explícita de archivos, revisión de contenido,
commit documental y push sin force; verificar SHA remoto. No incluye código,
scripts, credenciales, publicación funcional, cambios a main ni activación del
puente. Decisión vigente de aislamiento: cuenta estándar StockFlashAgent;
Docker/WSL descartados. Elevación solo administrativa, nunca para ejecutar agentes.
La aprobación definitiva permite crear esa cuenta y configurar sus ACL y
herramientas; sustituye la prohibición inicial de esos cambios del sistema.
No activar tarea permanente hasta superar la aceptación integral.

**Estado vigente del aislamiento:** aprovisionamiento completado y pruebas
negativas reales aprobadas bajo StockFlashAgent (run b322493309c44df7bd72a616c0081e63).
Ambas CLI arrancan con esa identidad mediante binarios reutilizados en tools,
sin acceso al NVM privado del propietario. La copia tiene Git independiente,
sin remoto ni commits y con metadatos de solo lectura para el ejecutor. Se permite
continuar BRIDGE-001 en esa copia; autenticación y aceptación integral aún deben
demostrarse. Este avance no concede GATES_PASS global, QA ni VERIFIED.

1. Comunicación y UI en español es-CL; código/tablas en inglés; nunca tocar producción.
2. Contratos de trabajo prioritarios **v2.2**: negocio=`docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md`; datos=`docs/product/especificacion-v2.2-parte-2-CONSOLIDADO-EN-REVISION.md`; integración=`docs/product/especificacion-v2.2-anexo-CONSOLIDADO-EN-REVISION.md`. Los tres documentos v2.1 en `docs/product/historico-v2.1/` son evidencia histórica, NO instrucciones vigentes si difieren.
3. v2.2 consolida decisiones comerciales del owner (demo vs free, derechos por producto, fiscal externo, ventas pausadas, branding). No interpretarlas como simples propuestas: están aprobadas. **Las partes rotuladas ABIERTAS requieren una decisión futura y no se implementan por intuición.**
4. `docs/governance/CONSTITUTION.md` define precedencias; `docs/governance/DECISIONES-ABIERTAS-v2.2.md` delimita bloqueos. `docs/requirements/INDEX-REQUISITOS.md` localiza IDs.
5. Implementar la Feature Packet aprobada y activa en `docs/work/CURRENT.md`, con sus subtareas y pruebas hasta completar o bloquearse por causa real. No pedir otra aprobación por cada subtarea ordinaria; mantener permisos `ask`, checkpoints y gates. En BOOT-001 la ausencia inicial de Laravel es esperada. Leer solo las secciones necesarias.
6. StockFlash + etiqueta comercial activa (POS/Bodega/SyncWP/Fiscal/Compras/Analítica/Control). Caja/Clientes/Precios son funciones internas, nunca productos vendidos por separado.
7. Un cliente no necesita CONTRATAR otro producto para usar uno habilitado. Se activan servicios técnicos compartidos; la autorización exige licencia comercial + capacidad + RBAC en servidor.
8. Dos modalidades: Demo Vitrina = ficticia y reiniciable; Gratis Persistente = datos reales no reseteables, 50 variantes vendibles y 500 ventas/mes; NO inventar qué ventas computan o política al agotarse el cupo (ADR pendiente).
9. En POS modo externo, una venta cobra, actualiza inventario y caja SIN DTE integrado ni folio obligatorio. No afirmar emisión fiscal ni registrar documento emitido automáticamente.
10. Marca PNG oficiales en `public/images/brand/`: si no existen, usar identidad textual provisional y no inventar imágenes.
11. PII/RUT/secretos nunca en Engram, prompts, repositorio ni logs. No leer `.env` reales, dumps ni claves SSH.
12. Probar idempotencia, permisos, concurrencia, caja, límites y stock por transacción cuando corresponda. Nunca declarar terminado sin pruebas.
13. Cambios de reglas, columnas, contratos y dependencias no previstos en v2.2 requieren propuesta/ADR. Agentes implementadores no modifican estos contratos.
14. Dudas fuera del alcance: registrar solicitud (`docs/work/DECISION-REQUEST-TEMPLATE.md`) y detener SOLO la pieza afectada; propietario conserva las decisiones reservadas y Codex revisa técnicamente. El puente local transportará pedidos/resultados tras aceptación; no existe conexión automática al chat web ni autorización humana por inferencia.
15. Publicación Git solo vía `stockflash-publisher` (`/sf-publish`) tras `APROBADO` + `GATES_PASS` vigente, en rama `main`, `git push origin main` sin force; prohibidos force push, reset destructivo, clean, borrado ramas remotas, deploy, migraciones remotas, DB destructiva y acceso a producción. CI/deploy solo tras gates y credenciales aisladas. Publicar no es VERIFIED.
16. Skills bajo demanda `.agents/skills`; MEMORY es consultiva, no override. Leer `LEEME-PRIMERO.md` al iniciar trabajo nuevo. `/sf-diagnostico` se realiza una vez o ante cambios reales de entorno; no repetir un preflight ya completado ni finalizar por mera necesidad de permiso `ask`. No ejecutar `/init`.
17. **Manual HTML de usuarios:** aplicar `docs/harness/GUIA-HTML-USUARIO.md` y `docs/work/GUIA-HTML-ESTADO.md` si cambia UX. Codex, sucesor editorial de ChatGPT por decisión del propietario, redacta `public/docs/index.html`; OpenCode solo integra/valida/publica. Detectar faltantes y emitir `GUIA_PENDIENTE_CHATGPT` como alias compatible de pedido a Codex, con SHA y evidencia. Entrega automática solo cuando el puente esté validado; nunca afirmar invocación del chat web.
18. **Continuidad:** Codex mantiene `docs/harness/CONTEXTO-CHATGPT.md` después de revisar entregas reales. El nombre se conserva por compatibilidad. OpenCode lo consulta sin editarlo ni delegar autoría; aporta SHA, QA/CI, funciones y pendientes por el puente validado o handoff mientras esté pendiente. Nueva sesión recupera ese archivo y comprueba HEAD, sin tomar snapshots históricos como estado vigente.

19. **Roadmap visual:** aplicar `docs/harness/ROADMAP-HTML-PROYECTO.md` y registrar cobertura en `docs/work/ROADMAP-HTML-ESTADO.md`. Codex conserva autoría íntegra y exclusiva de `resources/roadmap/index.html` como sucesor de ChatGPT. OpenCode no lo edita, autogenera ni altera estados; mantiene integración, permisos, QA y metadatos. Si cambian hitos/comercialización, emitir `ROADMAP_PENDIENTE_CHATGPT` como alias de pedido a Codex, con SHA y evidencia. Sin progreso ficticio ni VERIFIED inferido; el mecanismo de entrega sigue el estado real del puente.
