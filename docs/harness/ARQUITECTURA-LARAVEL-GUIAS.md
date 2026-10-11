# Directrices técnicas (no código) — confirmadas/propuestas

## Base técnica heredada por v2.2
Laravel + Vue 3 + Inertia, MariaDB InnoDB, aplicación modular por registro, API pública externa sólo para callbacks y webhooks concretos; sin API general innecesaria. Contratos desacoplados Woo y Fiscal, jobs idempotentes, colas database y scheduler cPanel, UTC interno / America/Santiago para reportes, seguridad RBAC propia y PII protegida.

## Decisiones de organización para OpenCode
- `app/Domain/<Modulo>` y `app/Integrations/<Adaptador>` según Parte 1 §4.5; no inventar otra estructura que contradiga documentación.
- UI usa componentes compartidos, tokens de estilo, un shell backoffice y otro shell POS; evitar CSS duplicado y dependencias UI voluminosas sin autorización.
- Dependencias de desarrollo oficiales o bien mantenidas, fijadas por lockfiles; respetar Laravel estable **compatible con la versión PHP real de cPanel**, pendiente preflight, no fijar major sin comprobación.
- Preferencia por mecanismos nativos de Laravel/Vue antes que paquetes externos para auth/RBAC/cola.
- No generar código nuevo ni tablas antes del Feature Packet aprobado; no crear 80 migraciones en un solo prompt.
- Mantener pruebas unitarias y feature pertinentes, y contratos MariaDB 10.6+ conforme ARQ-13 desde infraestructura CI.
- Toda nueva dependencia debe anotarse como solicitud estructurada: propósito, alternativas sin dependencias, licencia, tamaño, mantenimiento, seguridad, compatibilidad cPanel.
