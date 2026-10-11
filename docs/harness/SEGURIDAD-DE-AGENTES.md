# Seguridad de agentes y secretos — hardening progresivo

## Bootstrap (actual)
`opencode.json` tiene permisos conservadores; `edit` pregunta para cambios no prohibidos, `bash` pregunta salvo comandos de inspección. `git commit/push`, SSH/FTP y migraciones ejecutadas por el agente quedan denegadas. Abrir sin `--auto`.
**Estas reglas son defensa en profundidad, no un sandbox**. Bash, plugins, comandos compuestos y MCPs pueden evitar restricciones si obtienen permisos. Nunca dar al agente acceso del sistema operativo a secretos/producción.

## Separaciones obligatorias antes de automatizar
1. Cuenta DEV MariaDB con permisos de app, distinta de cuenta read-only MCP; ninguna conecta a producción.
2. Credenciales previas de FTP y BD rotadas; nuevas en secret manager del pipeline, no Git.
3. Clave SSH del deploy aislada, preferible solo staging y directorios limitados por usuario/host; probar permisos y servidor real.
4. Copia de seguridad externa verificada; nunca alojar repo ni credenciales productivas dentro del webroot.
5. Staging con datos falsos y acceso restringido; `APP_DEBUG=false` en ambientes públicos.
6. Owner aprueba nuevas dependencias y cualquier operación destructiva.
7. CI controla release con tests, auditorías y build; la IA no posee token de deploy.
8. Git secrets scanning local/CI y `.gitignore` robusto cuando exista Laravel; no sustituye rotación.
9. En el entorno de trabajo de OpenCode mantener permisos mínimos en el OS: nada de directorios de copias de seguridad, SSH o bases de clientes montados en workspace.

## Eventos que detienen inmediatamente la feature
Hallazgo de secreto, necesidad de ver datos reales, solicitud de despliegue no autorizado, modificación silenciosa a documentos normativos, tests críticos fallidos, ausencia de backup para migración irreversible.
