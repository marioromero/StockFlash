# Warp + OpenCode, primer arranque (sin gasto innecesario de tokens)
1. Abrir proyecto local con Git; no montar home SSH/cPanel dentro del workspace.
2. Extraer ZIP y revisar `.opencode/`, `.agents/`, `AGENTS.md`, `opencode.json`.
3. Confirmar `opencode --version` y `/models`; no instalar modelos de pago ni activar `--auto`.
4. Elegir modelo gratuito disponible para lectura (ej. Muse Spark 1.3 si sus condiciones están aceptadas); no fijar ID de modelo inventado.
5. Ejecutar `/sf-diagnostico` solo para resumir estructura/estado actual. Si propone implementar Laravel sin Feature Packet aprobado, detenerlo.
6. Configurar selección final por agente cuando terminemos benchmark controlado.
7. No ejecutar `/init` ni `engram setup opencode` antes de validar si sobrescriben configuración existente.
8. No hacer push/deploy hasta tener la política CI/CD instalada y validada.

**Notas:** Windows Warp puede ejecutar PowerShell, CMD, WSL o Bash; no asumir que todos los comandos Bash funcionan en PowerShell. Los comandos de inspección remota se ejecutan en Terminal cPanel/SSH, no contra servidor desde el modelo.
