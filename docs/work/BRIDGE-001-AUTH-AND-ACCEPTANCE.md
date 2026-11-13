# BRIDGE-001 — Aislamiento comprobado; rechazo DeepSeek 401 pendiente

## Estado vigente — 2026-11-13

Codex autenticado con ChatGPT y preparación real completada. OpenCode registra
DeepSeek con API key propia, pero la llamada real devolvió APIError HTTP 401:
la credencial fue rechazada por el proveedor. Registrar una credencial no prueba
que el servidor la acepte. No cambiar a un modelo gratuito como sustituto.

Run: `bd181a1a9c6f444397507d5bc1453dd3`.
Sesión Codex: `01a1d2da-9e96-7280-a96c-aa27bde9877b`.
Sesión OpenCode: `ses_e2d24e2f1ffe2QjZPq54QJvdbP`.
Modelo del proveedor directo disponible en catálogo: `deepseek/deepseek-flash`.

Diagnóstico y reconciliación ejecutados realmente como StockFlashAgent, sin
inferencia nueva: export nativo confirma 401, ninguna herramienta ejecutada,
ninguna implementación creada y proceso terminado. Job marcado AUTH_REJECTED;
la preparación Codex queda cacheada y la reanudación conservará ambas sesiones.
Máximo tres intentos de autenticación, sin reejecución ciega del trabajo.
No se ha alcanzado el checkpoint IMPLEMENTED ni ejecutado gates/QA del ciclo.

Pendiente humano: corregir la clave del proveedor directo mediante opencode-login,
solo en la terminal privada. No publicar claves ni salidas crudas de autenticación.
Evidencias saneadas: exchange/bridge-diagnostic.json y recibo persistente
opencode-implement-auth-reconciliation.json dentro del directorio de ese run.

Los archivos de dependencias generados por la CLI se registraron con hashes en
control/opencode-runtime.json antes de ejecutar modelos. No se permite agregar
arbitrariamente archivos ignorados ni modificar ese runtime durante el ciclo.
Las 17 pruebas originales y las 12 del transporte pasan bajo la cuenta dedicada;
las últimas incluyen rechazo de runtime alterado y reconciliación solo ante 401
sin herramientas ejecutadas. No equivalen a gates integrales o QA aprobado.

Las secciones inferiores describen preparación y comandos; este estado prevalece
sobre las menciones anteriores a autenticación todavía no registrada.

## Evidencia comprobada como StockFlashAgent

- Provision run: b322493309c44df7bd72a616c0081e63. Recibo y aislamiento coinciden.
- isolation.json: ISOLATION_PASS; controles de perfil/original/Git privado,
  escritura, datos sintéticos, vault, junction, C$, publicación local y entorno.
- Después de preparar la copia Git independiente, también PASS para publicación
  hacia esa copia local y metadatos/política/binarios de solo lectura.
- core-tests.txt: 17 pruebas existentes PASS bajo cuenta estándar.
- transport-tests.txt: 12 pruebas nuevas PASS bajo la misma identidad. Son pruebas
  deterministas con procesos falsos, no se presentan como sesiones LLM reales.
- Codex 0.167.2 y OpenCode 1.19.0 arrancan realmente con esa cuenta; py -3 resuelve
  Python 3.12 reutilizado. No se copiaron credenciales del propietario.

Reportes locales: `C:\StockFlashAgent\exchange\isolation.json`, `core-tests.txt`,
`transport-tests.txt`, `cli-status.json`. No publicar archivos de autenticación.

## Ajuste real de herramientas

C:\Program Files\nodejs es un enlace NVM al perfil del propietario. Su acceso
denegado era correcto; no se abrió ese perfil. Se copiaron únicamente binarios y
recursos estáticos de la instalación existente a tools\cli, verificando hashes.
Los ejecutores usan rutas nativas .exe fijas y argumentos separados, sin shell
para transportar mensajes del modelo. No hay otra instalación ni nuevo proveedor.

## Integración preparada (ciclo LLM iniciado, todavía incompleto)

- Núcleo sf_bridge.py y sus 17 pruebas preservados; validación real de SID,
  controles de solo lectura, packet APPROVED e índice inmutable añadidos.
- sf_bridge_transport.py reutiliza Ledger: instrucciones estables, hash de
  invocación, PID/creación, sesiones guardadas al llegar el primer evento JSONL,
  recibos atómicos y reutilización de resultados. No repite un proceso activo o
  interrumpido sin reconciliación; detecta recibos guardados alterados.
- Modo piloto del MISMO sf-run/orchestrator, implementer y reviewer. Python no
  implementa gates ni sustituye QA: exige la llamada nativa al verificador actual,
  su reporte JSON completo y artefacto idéntico a la respuesta del reviewer nativo.
- Correcciones técnicas solicitadas por Codex vuelven al mismo coordinador con
  el contador persistente, máximo tres ciclos totales. Decisiones reservadas se
  guardan con authorization_granted=false y detienen el avance.
- sf_verify.py conserva ejecución única; BRIDGE-001 agrega contratos del transporte
  y tarea técnica a GATES_BY_PACKET y salida JSON opcional del mismo resultado.
- No hay remoto ni commits en la copia. Índice/metadatos Git y política son de
  solo lectura para el ejecutor; publisher denegado. No tarea programada permanente.

## Única aceptación prevista

Codex prepara instrucción acotada → Python → sf-run piloto → Task implementer
crea SOLO scripts/sf_bridge_message.py, reutilizando validate → checkpoint
IMPLEMENTED → salida 75 deliberada del transporte → reinicio → misma sesión
OpenCode continúa gates y Task reviewer SIN repetir implementación → misma sesión
Codex revisa externamente artefactos, hashes y fuente. Revisión CORRECT activa
corrección en alcance con presupuesto compartido; no concede autorización externa.

La tarea consiste en CLI JSON stdin/stdout acotada y está especificada en
tests/bridge_acceptance/test_message_cli.py. No es conectividad ni función comercial.
Ese archivo de implementación debe crearlo OpenCode en la aceptación, no Codex.

## Intervenciones humanas necesarias ahora

Codex: autenticación ChatGPT comprobada por `codex login status`, ejecutado como
StockFlashAgent: exit 0, estado CHATGPT. No repetir este inicio de sesión.
Se corrigió la creación de CODEX_HOME bajo esa identidad y el launcher ahora
informa el resultado nativo real, en lugar de dar por exitoso un proceso terminado.
Comando conservado para futuras sesiones que realmente necesiten autenticarse:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Users\Mario\PhpstormProjects\stockflash-codex-pilot\scripts\bridge_windows\Invoke-Agent.ps1" -Operation codex-login
```

OpenCode: el propietario confirmó DeepSeek con API key propia. Corregir la
credencial rechazada bajo StockFlashAgent con el comando inferior: seleccionar DeepSeek
e ingresar la clave exclusivamente en la terminal privada. No usar un modelo
gratuito de OpenCode como sustituto ni compartir la clave en el chat.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Users\Mario\PhpstormProjects\stockflash-codex-pilot\scripts\bridge_windows\Invoke-Agent.ps1" -Operation opencode-login
```

Último estado nativo saneado: Codex ChatGPT autenticado y proveedor DeepSeek
registrado en el perfil dedicado, pero HTTP 401 en su llamada real. No se almacenan
salidas crudas de auth ni se lee/copia auth.json del usuario original. El catálogo
incluye deepseek/deepseek-flash y deepseek/deepseek-v4-pro del proveedor directo.

## Estado honesto

Gates globales y QA de BRIDGE-001 NO aprobados todavía: falta autenticación y
aceptación integral real, incluida recuperación de ambas sesiones. El bloqueo
efectivo de publicación local sí está demostrado por procesos reales; las pruebas
unitarias no se atribuyen como prueba LLM. No se inició implementación comercial,
no se migró BD, no se desplegó ni se tocó main. Publicación de Markdown en piloto
no es publicación funcional ni VERIFIED. Fuentes Python/PowerShell siguen locales.
