# Engram, MCP y CLI: implantación gradual

## Fase 0 (paquete actual)
No se instalan ni habilitan MCPs; `opencode.json` NO incluye servidor MCP. Ningún secreto de DB/FTP/SSH aparece aquí. `git` CLI funciona sin MCP.

## Engram
Engram es memoria local consultiva, NO fuente normativa. Guardar únicamente aprendizajes de errores reproducibles, notas de compatibilidad cPanel, solución documentada, enlace de ADR/commit/feature, nunca contraseñas, datos de clientes, base operativa ni transcripciones completas.
- Confirmar versión OpenCode y versión estable Engram; descargar SOLO desde repo del desarrollador verificado.
- Primera evaluación: CLI/consulta puntual con almacenamiento local (sin plugin invasivo), en una carpeta/perfil de usuario separado.
- Segunda evaluación: integración `engram setup opencode` tras revisar qué configuración global modifica y cuántas herramientas carga. Respaldar opencode global primero.
- Comparar A/B consumo de tokens, recuperación de contexto y calidad; mantener opción más eficiente.
- La configuración de Engram puede variar entre versiones y ejecutar `engram setup opencode` cambia archivos en perfil del usuario: NO ejecutarlo automáticamente desde un agente.

## Otras herramientas
- Navegador: Playwright CLI prioritaria para smoke tests; DevTools MCP solo para diagnóstico difícil.
- MariaDB MCP: instancia DEV, cuenta exclusiva SELECT/SHOW limitada a esquemas sanitizados; preferir además configurar read-only en el servidor MCP. JAMÁS cuenta de app/producción.
- Context7 apagado salvo tarea con documentación técnica fresca.
- Caveman: sólo después de métricas reales; comprimir salidas ruidosas, nunca especificaciones ni logs de errores necesarios.
- FTP, SSH, MariaDB productiva, servicios DTE, GitHub Secrets: fuera del contexto y de los permisos de los agentes.

## Criterio de alta
Cada MCP nuevo requiere ficha: propósito, versión, permisos, comandos ejecutables, seguridad, coste de tokens, benchmark y procedimiento de apagado. No habilitar MCPs en cascada.
