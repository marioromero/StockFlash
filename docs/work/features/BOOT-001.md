# BOOT-001 — Bootstrap Laravel + Vue 3 + Inertia

status: APPROVED
owner_approval: Aprobación del propietario en la conversación de StockFlash, 2026-10-12 (BOOT-001).
etapa: BOOTSTRAP
responsable_implementacion: OpenCode
revision: Independiente, antes de dar por VERIFIED.

## Qué / por qué
Crear la base ejecutable **local** de StockFlash dentro del repositorio que ya contiene el harness, sin sobrescribir los contratos, Git ni sus herramientas. Validar PHP/Laravel + Vue 3 + Inertia + Vite y una pantalla inicial mínima, sobria y accesible. Este hito no entrega un producto comercial.

## Incluye (alcance aprobado)
1. Seleccionar y fijar una versión estable de Laravel compatible con PHP local 8.3.4 y cPanel 8.3.35; registrar versión y justificación en la entrega, sin alterar documentos canónicos.
2. Integrar Vue 3, Inertia y Vite mediante sus dependencias oficiales necesarias, con lockfiles. Usar JavaScript o TypeScript según configuración mínima coherente; si la elección implica alterar la arquitectura aprobada, escalar antes.
3. Mantener y respetar .git, AGENTS.md, opencode.json, .agents, .opencode, docs y .github existentes. En particular, **NO ejecutar create-project sobre la raíz no vacía** ni sustituir los archivos actuales. Si se crea un scaffold temporal, mantenerlo dentro del repositorio, ignorarlo en Git y eliminarlo sólo después de revisar los archivos a conservar, con consentimiento para operaciones destructivas.
4. Proveer una ruta web raíz que renderice una página Vue vía Inertia con identidad textual temporal «StockFlash», en gris #303846 / azul #0856FF, diseño corporativo sencillo y responsive. No inventar logos PNG ni declarar un producto comercial activo antes de que exista autorización de módulos.
5. Preparar organización de carpetas según Parte 1 §4.5 sin generar implementaciones de dominio ficticias. Configurar pruebas mínimas que comprueben arranque de ruta y respuesta, además de compilar assets.
6. Documentar en la evidencia comandos y resultados de verificación y advertir bloqueos reproducibles.

## Queda expresamente fuera
- No implementar login, RBAC, POS, Bodega, ventas, licencias, planes, cliente ni StockFlash Control.
- No crear las 80+ migraciones canónicas, tablas de dominio, seeders, integración fiscal, WooCommerce ni API públicas.
- No ejecutar migraciones, seeders ni operaciones sobre bases de datos locales, staging o producción.
- No desplegar, hacer commit/push desde agentes ni usar secretos de GitHub/cPanel.
- No sobrescribir los contratos PUNTO 0 consolidados ni cambiar ADRs.
- No instalar starters preconfigurados de autenticación, UI kits, Tailwind u otras librerías de terceros sin autorización explícita. Las dependencias directas oficiales requeridas para Laravel/Vue/Inertia/Vite quedan autorizadas para este bootstrap.
- Las migraciones de ejemplo incluidas en un scaffold de Laravel **no son modelo aprobado de StockFlash**; evitar incorporar migraciones de users/cache/jobs como esquema definitivo. No ejecutarlas. Documentar su exclusión o dejarlas fuera del entregable.

## Referencias precisas
- `AGENTS.md` y `docs/governance/CONSTITUTION.md`: precedencia y límites.
- `docs/product/especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md`: §4.1 (arquitectura modular), §4.5 (estructura de código), §5 (stack Laravel, Vue/Inertia, MariaDB, hosting).
- `docs/branding/brand-contract.md` y `docs/ux/UI-UX-CONTRACT-v0.1.md`: colores, identidad temporal y componentes.
- `docs/harness/ARQUITECTURA-LARAVEL-GUIAS.md`: organización y no introducir dependencias inesperadas.
- `docs/governance/DECISIONES-ABIERTAS-v2.2.md`: no resolver ADR-01..06 en este hito.
- Modelo de datos v2.2: **no se modifican tablas ni columnas**.

## Contratos y casos límite
- Trabajar en el repositorio ya inicializado; NO suponer carpeta vacía.
- Si hay un archivo con nombre coincidente, inspeccionarlo y pedir aprobación antes de reemplazarlo.
- No leer, mostrar ni incluir en resultados `.env`, claves, tokens, base de datos o ficheros privados.
- Al finalizar, `git status` debe mostrar únicamente cambios explicables del scaffold y sus tests/documentación operacional, nunca dependencias `vendor` o `node_modules`.
- El sitio de staging sigue intacto; el navegador local puede usar `php artisan serve`, sin servicios permanentes en cPanel.

## Criterios de aceptación (EARS)
- CUANDO se instalan dependencias en desarrollo, el sistema DEBE construir assets con `npm run build` y generar los lockfiles correspondientes.
- CUANDO se ejecutan las pruebas locales pertinentes, DEBEN finalizar sin errores vinculados con este hito.
- CUANDO un navegador abre la ruta raíz local, DEBE renderizar una pantalla Vue mediante Inertia y mostrar la marca temporal StockFlash.
- CUANDO se inspecciona Git, NO DEBEN figurar secretos, dependencias instaladas ni documentos canónicos alterados.
- CUANDO el entorno local se inicializa, NO DEBE exigir MariaDB ni ejecutar migraciones del dominio para mostrar la pantalla inicial.

## Procedimiento del agente
1. Iniciar con `/sf-plan BOOT-001`; máximo 10 subtareas y verificar compatibilidad de versiones.
2. Antes de `/sf-feature BOOT-001`, revisar el plan, riesgos y cualquier dependencia adicional.
3. Implementar **una subtarea por vez**, con permisos del harness; pedir confirmación ante comandos bash que el proyecto marque como `ask`.
4. Evaluar con `/sf-review BOOT-001`. El estado VERIFIED solo se adopta tras evidencias concretas y revisión.

## Gates
- [ ] Versión Laravel elegida compatible y documentada
- [ ] composer install / PHP pruebas pertinentes
- [ ] npm install / npm run build
- [ ] Inertia+Vue sirve una página local
- [ ] Sin migraciones ni escrituras DB
- [ ] Sin modificaciones a specs, contrato visual, .env ni secretos
- [ ] Sin despliegue o commit de agentes
- [ ] Reviewer independiente completado
