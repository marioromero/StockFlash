# BRIDGE-001 — Ejecutor Windows dedicado

Decisión definitiva del propietario: cuenta estándar StockFlashAgent.
Docker y WSL quedan descartados; no ejecutar DISM ni instalar sus componentes.
No activar tarea programada permanente antes de aceptación integral.

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
