# BRIDGE-001 — Aislamiento comprobado; autenticación e integral pendientes

## Evidencia comprobada como StockFlashAgent

- Provision run: b322493309c44df7bd72a616c0081e63. Recibo y aislamiento coinciden.
- isolation.json: ISOLATION_PASS; controles de perfil/original/Git privado,
  escritura, datos sintéticos, vault, junction, C$, publicación local y entorno.
- Después de preparar la copia Git independiente, también PASS para publicación
  hacia esa copia local y metadatos/política/binarios de solo lectura.
- core-tests.txt: 17 pruebas existentes PASS bajo cuenta estándar.
- transport-tests.txt: 10 pruebas nuevas PASS bajo la misma identidad. Son pruebas
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

## Integración preparada (todavía sin prueba LLM real)

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

OpenCode: el propietario confirmó DeepSeek con API key propia. Falta conectar ese
proveedor bajo StockFlashAgent mediante el comando inferior: seleccionar DeepSeek
e ingresar la clave exclusivamente en la terminal privada. No usar un modelo
gratuito de OpenCode como sustituto ni compartir la clave en el chat.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Users\Mario\PhpstormProjects\stockflash-codex-pilot\scripts\bridge_windows\Invoke-Agent.ps1" -Operation opencode-login
```

Último estado nativo saneado: Codex ChatGPT autenticado y proveedor DeepSeek
todavía no conectado en el perfil dedicado. No se almacenan salidas crudas de auth
ni se lee/copia auth.json del usuario original. El catálogo disponible solo ofrece
IDs DeepSeek de OpenCode; se resolverá el ID del proveedor directo tras autenticarlo.

## Estado honesto

Gates globales y QA de BRIDGE-001 NO aprobados todavía: falta autenticación y
aceptación integral real, incluida recuperación de ambas sesiones. El bloqueo
efectivo de publicación local sí está demostrado por procesos reales; las pruebas
unitarias no se atribuyen como prueba LLM. No se inició implementación comercial,
no se migró BD, no se desplegó ni se tocó main. Publicación de Markdown en piloto
no es publicación funcional ni VERIFIED. Fuentes Python/PowerShell siguen locales.
