# Warp + OpenCode — Inicio actualizado (2026-10-12)

El repositorio Git y OpenCode CLI YA están configurados; no extraer ZIP de nuevo, ni ejecutar `git init` o `/init`.
1. En Warp, abrir la raíz StockFlash.
2. Revisar `git status --short --branch`; con árbol limpio, `git pull --ff-only origin main`.
3. Abrir `opencode`, que ya reconoce `AGENTS.md` y su configuración.
4. Consultar `docs/work/CURRENT.md`: BOOT-001 está aprobado y su plan ya fue revisado.
5. Ejecutar `/sf-feature BOOT-001`: scaffold local de Laravel + Vue3 + Inertia + TypeScript, sin sobrescribir el harness. La ausencia de Laravel inicial es esperada.
6. Permisos `ask` de herramientas siguen vigentes; solo escalar cambios fuera del Feature Packet o colisiones no previstas.
7. Revisar pruebas, `git diff` y `/sf-review BOOT-001` antes de registrar versión.
8. No instalar Engram/MCP adicionales ni exigir WSL2; no acceder a cPanel/DB, ni migrar/desplegar durante BOOT-001.

Estado: PHP8.3 local/cPanel, Node24 local, Node20 en cPanel. CI futuro compila assets con Node24, no en cPanel.
