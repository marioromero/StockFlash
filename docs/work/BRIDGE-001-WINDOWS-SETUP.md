# BRIDGE-001 — Ejecutor Windows dedicado

Decisión definitiva del propietario: cuenta estándar StockFlashAgent.
Docker y WSL quedan descartados; no ejecutar DISM ni instalar sus componentes.
No activar tarea programada permanente antes de aceptación integral.

## Procedimiento vigente revisado — ejecución desde el escritorio

Abrir una ventana **normal, sin elevación**, de PowerShell del propietario y ejecutar:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Users\Mario\PhpstormProjects\stockflash-codex-pilot\scripts\bridge_windows\Start-Provision.ps1"
```

Aceptar UAC en ese escritorio. No ejecutar el lanzador desde una consola ya
elevada ni deshabilitar UAC. Bypass se limita a estos procesos PowerShell;
no modifica la política de ejecución permanente. Los scripts están guardados
LOCALMENTE en el piloto; los commits documentales no incluyen sus fuentes.

El lanzador hace preflight sin cambios, toma bloqueo exclusivo, espera al proceso
administrativo y exige un recibo de éxito del MISMO run_id. Después, desde el
token normal del propietario, verifica cuenta habilitada, ausencia del grupo
Administradores y acceso habitual de lectura/escritura a AGENTS de ambos árboles
(abre/cierra handles sin escribir bytes).

Prepara runtime Python 3.12 por copia selectiva de biblioteca estándar/binarios,
sin site-packages ni credenciales; exporta fuentes permitidas, sin `.git` ni
`.env`. No sobrescribe archivos del workspace modificados fuera del manifiesto.
Prepara sentinels sintéticos y remoto Git local protegido que faltaban en la
versión anterior. No contacta GitHub ni crea commits en StockFlash.

Finalmente ejecuta probe y las 17 pruebas del núcleo como StockFlashAgent,
verificando SID y token no administrativo. El entorno del hijo es una lista
permitida independiente: no borra ni modifica variables de la consola del usuario.
Solo muestra `STOCKFLASH_AGENT_SETUP_PASS` si todas esas operaciones terminan bien.
Ese resultado NO significa aceptación integral, GATES_PASS global, QA ni VERIFIED.

Evidencia esperada:

- `C:\StockFlashAgent\exchange\isolation.json`, con run_id vigente.
- `C:\StockFlashAgent\exchange\probe-execution.txt`.
- `C:\StockFlashAgent\exchange\core-tests.txt`, con 17 pruebas y salida exitosa.
- Estado administrativo privado: `%LOCALAPPDATA%\StockFlash\bridge\provisioning\desktop-status.json`.

No compartir archivos de credenciales ni backups SDDL. Ante fallo no reintentar
si hay proceso activo; el recibo indica etapa fallida y el puente conserva el bloqueo.

## Revisión de seguridad y validación efectivamente realizadas

- Se corrigieron espera/exit code, recibos obsoletos, rutas con espacios y el
  falso éxito del primer lanzador. No se lanzó UAC desde Codex en esta revisión.
- Reejecución comprueba marcador/identidades, contraseña nativa y pertenencias;
  no adopta otra cuenta ni una carpeta no marcada. Un estado parcial no verificable
  se bloquea, sin resetear contraseñas, borrar trabajo ni aplicar permisos generales.
- Rechaza reparse points en rutas administradas y origen/destino de copias.
- ACL privadas añaden Deny solo al SID nuevo. Se comparan propietario, grupo y
  TODAS las reglas de otros principales antes/después; se conserva backup privado.
- Se detectó Modify para Usuarios autenticados en `C:\php`: se deniega solamente
  escritura/borrado/cambio de permisos a StockFlashAgent, manteniendo lectura y
  ejecución. El acceso del propietario no se elimina. El probe también comprueba
  handles de escritura denegados sobre PHP, Node y Git compartidos.
- Captura de pruebas usa ProcessStartInfo: evita que stderr normal de unittest
  se interprete como error PowerShell antes de comprobar el exit code.
- Se validó sintaxis de seis scripts PowerShell y del probe Python; bindings C#
  compilan sin acceder a credenciales. Preflight real sin cambios:
  `PROVISION_PREFLIGHT_PASS_NO_CHANGES`.
- AST confirma 17 pruebas existentes, sin modificaciones. No se repitieron en el
  token del propietario ni se ejecutó probe como sustituto de la identidad dedicada.
- Última comprobación: cuenta aún inexistente, sin recibo ni reporte de aislamiento.
  Falta ejecutar el comando de escritorio; no hay aislamiento aprobado.

Las siguientes secciones conservan el diseño inicial; cuando describen pasos
pendientes, el flujo vigente anterior los automatiza después de aceptar UAC.

## Preparación administrativa concreta

`scripts/bridge_windows/Start-Provision.ps1` solicita UAC y lanza solamente
`Provision-Agent.ps1`; no lanza modelos ni pruebas con token administrativo.
La contraseña de la cuenta es aleatoria, se guarda en Windows Credential Manager
del propietario y nunca se incluye en línea de comandos, repo o logs.
La elevación debe conservar la identidad del propietario: ante otra identidad
administrativa el script se detiene, evitando guardar el secreto en otro vault.

La preparación crea `C:\StockFlashAgent` con:

- control y tools: lectura/ejecución para StockFlashAgent, sin escritura.
- workspace, state y exchange: modificación para esa cuenta.
- Propietario, SYSTEM y administradores mantienen control de esas carpetas.

Añade únicamente ACE Deny para el SID de StockFlashAgent en el perfil privado,
original, `.git` compartido y worktree piloto. No elimina ACE existentes ni cambia
el propietario de los repositorios; guarda SDDL anterior fuera del repo en el
perfil privado del propietario. La confirmación de acceso habitual del propietario
forma parte de la comprobación posterior.

## Estado real

Primer intento de Start-Process -Verb RunAs: Windows devolvió cancelación de UAC.
El comando inicial mostró una confirmación incondicional de inicio con PID nulo;
esa salida NO acredita ejecución. Se creó Start-Provision.ps1 para corregirlo.
No hay prueba aún de cuenta creada ni de aislamiento. Se solicitó al propietario
la interacción UAC necesaria, sin pedir otra aprobación del alcance ya autorizado.

## Preparación y ejecución bajo identidad estándar (pendientes)

Tras provisionar: reutilizar runtime estándar Python 3.12 mediante copia selectiva
de binarios y biblioteca estándar a tools, excluyendo site-packages/configuración
personal. PHP, Node, Git y CLI globales se reutilizan si resultan accesibles;
no copiar configuraciones ni credenciales del propietario.

Invoke-Agent.ps1 obtiene solo la credencial del ejecutor del vault del propietario,
limpia el entorno heredado y crea un proceso con -Credential y -LoadUserProfile.
Agent-Entry.ps1 verifica identidad StockFlashAgent y token no administrativo antes
de cualquier operación. No acepta comandos libres de los modelos.

isolation_probe.py comprueba lecturas y handles de escritura denegados sobre
archivos no sensibles del original/piloto, metadatos Git, sentinel personal y
.env sintético; no lee `.env` reales. Comprueba además vault del propietario,
ACL de tools/control, acceso por junction y share administrativo y push hacia
un remoto LOCAL sintético protegido. No contacta GitHub.
El commit de fixture se limita al repositorio sintético de esa prueba fuera del
piloto; no crea commits en StockFlash ni publica código.

Estas herramientas todavía no se han ejecutado bajo StockFlashAgent. No afirmar
ISOLATION_PASS por su existencia ni por validación sintáctica. Las 17 pruebas del
núcleo están preservadas; no se sustituyen por este probe ni por otro harness.

Una vez superada esta puerta: login nativo de ambas CLI en el perfil dedicado,
adaptadores persistentes, modo piloto del orquestador existente y aceptación real.
Autenticaciones iniciales son interacciones humanas de identidad, sin traslado
manual de instrucciones/resultados y sin secretos por este chat.
