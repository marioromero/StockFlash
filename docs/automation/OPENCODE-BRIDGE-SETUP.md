# StockFlash — puesta en marcha del puente Work / OpenCode

**Piloto no autónomo; no modificar el harness.** Leer primero `docs/automation/WORK-BRIDGE.md`, luego `AGENTS.md` y `docs/harness/RUN-REPORT-CONTRACT.md`.

## Compatibilidad verificada

Se revisaron en el repo real: `opencode.json`, `.opencode/commands/sf-run.md`, `sf-publish.md`, `sf-handoff.md`, `scripts/safe_git_pull.py`, `docs/work/CURRENT.md`, `docs/work/features/USERS-001.md` y workflows `ci.yml` / `contract-check.yml`. El puente usa el mismo comando `/sf-run` con el mismo Feature Packet; no cambia los permisos ni delega en otro publisher. No se toca producción, despliegues ni migraciones. `USERS-001` permanece DRAFT.

## Preparación inicial (Windows PowerShell)

Desde la raíz del clone de StockFlash:

1. Verificar `py -3 --version`, `opencode --version`, `git status --short` y `gh auth status`. Si `gh` no existe, instalar GitHub CLI o proporcionar `GH_TOKEN` temporal mediante variables de entorno del usuario. **Nunca copiar un token al chat, al repo, ni al prompt de OpenCode.**
2. Integrar el PR de implementación después de revisar sus archivos y pasar CI; luego, en main limpio, ejecutar `py -3 scripts/safe_git_pull.py`. Si indica SYNC_BLOCKED, detenerse; no usar reset, stash o clean.
3. Ejecutar pruebas sin red: `py -3 -m unittest discover -s tests -p test_sf_work_bridge.py -v`. Se esperan 6 tests.
4. Preparar un PR independiente de control con base main y título `[SF-WORK-CONTROL] Transporte Work y OpenCode — NO MERGEAR`. Mantenerlo abierto. Su número se usa como PR_CONTROL. **Este PR no contiene código para fusionar.**
5. Con la cuenta de GitHub conectada en el equipo y permiso de crear comentarios, enviar una prueba:
   
   ```powershell
   py -3 scripts/sf_work_bridge.py signal --pr PR_CONTROL --feature USERS-001 --report-id SMOKE-001 --smoke
   ```

   El script publica un comentario inofensivo referenciando el HEAD actual de main. `--smoke` no consulta CI y **no autoriza USERS-001**.
6. Configurar la tarea Work usando el prompt exacto de `WORK-BRIDGE.md`. Elegir evento **comentario nuevo de PR**, filtrar por marcador `sf-oc-report-v1` y por el PR de control. Si el evento no aparece en tu cuenta, detener y reportar limitación; no sustituirlo silenciosamente por un cron. Tras crear la tarea puede hacer falta otro aviso `SMOKE-002` para activarla.
7. Confirmar que Work publicó un comentario `sf-work-instruction-v1` con acción STOP. Si Work no puede **escribir** en GitHub, esto no quedó automatizado; no ejecutar código.
8. Consultar el resultado sin lanzar OpenCode:

   ```powershell
   py -3 scripts/sf_work_bridge.py watch --pr PR_CONTROL --trusted-login marioromero --once
   ```

   Si hay una instrucción STOP válida debe mostrarla. Si se quiere escuchar indefinidamente mientras Windows está encendido, quitar `--once`: espera 30 segundos entre consultas. Para finalizar, Ctrl+C. No usar todavía `--execute`.

## Informe real y activación posterior

Cuando OpenCode implemente una **feature previamente APPROVED**, debe continuar con el procedimiento existente de gates → reviewer → publisher, esperar CI verde para el SHA de main y crear/publicar el informe versionado `docs/work/<FEATURE>-RUN-REPORT-<ID>.md` conforme al contrato existente. Si el contrato RUN-REPORT aún no está operativo, primero implementarlo y probarlo mediante su packet propio; no falsificar reportes.

Luego, ejecutar (reemplazar campos por ID, ruta y número reales):

```powershell
py -3 scripts/sf_work_bridge.py signal --pr PR_CONTROL --feature FEATURE-001 --report-id FEATURE-001-run-001 --report-path docs/work/FEATURE-001-RUN-REPORT-001.md
```

Para la señal real el script verifica la presencia del Markdown en GitHub en el SHA HEAD y CI SUCCESS de PHP 8.3, Node 24, smoke GET / y contratos. Si algún check está pendiente o falla, devuelve BRIDGE_BLOCKED y **no** publica el aviso de trabajo.

Para una fase futura, solo después de probar entrada/salida y controles económicos:

```powershell
$env:SF_WORK_ALLOW_EXECUTE="YES"
py -3 scripts/sf_work_bridge.py watch --pr PR_CONTROL --trusted-login marioromero --execute
```

Esto invoca el comando OpenCode documentado `opencode run --file <JSON local> --command sf-run FEATURE`, pero la forma exacta debe validarse contra `opencode run --help` instalado en tu OpenCode 2. No cambiar `/sf-run` por prompts de shell. El JSON recibido se guarda en `docs/work/.run`, ignorado por Git. Work no modifica archivos del proyecto en este puente; si necesita actualizar manual/roadmap/contexto debe seguir el flujo editorial de GPT existente.

## Diagnóstico y condiciones de parada

- Sin evento de Work: verificar Work → Programadas → activador, condición, repo privado autorizado y comentario nuevo (uno anterior a la tarea no sirve como prueba).
- Work leyó pero no comentó: es un **bloqueo de permiso de escritura**, esperado como posibilidad según documentación oficial. No declarar éxito E2E.
- `watch` no ve STOP: revisar login del autor real en GitHub. No aceptar comodín; `--trusted-login` debe ser un login exacto.
- SHA diferente, CI no verde, spec DRAFT, worktree sucio o error de OpenCode: detener; no reintentar en bucle, no forzar Git. Estado persistido en `docs/work/.run/sf-work-bridge-state.json`.
- El polling GitHub no consume cuota GPT. La revisión Work sí. No se implementaron aún límites monetarios, temporales ni conteo de tokens DeepSeek: **mantener el modo sin ejecución** hasta tenerlos y probarlos.
