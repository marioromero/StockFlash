# BRIDGE-001 — Preparación de Docker Desktop / WSL 2

**HISTÓRICO / DESCARTADO por decisión posterior del propietario.** No ejecutar
los comandos de este documento. La solución vigente es StockFlashAgent:
ver `BRIDGE-001-WINDOWS-SETUP.md`. La habilitación DISM nunca fue autorizada
ni ejecutada por esta sesión.

Fecha: 2026-11-12. Entrega local sin commit ni push nuevos.

## Autorización y puerta pendiente

El propietario aprobó alternativa A, preferentemente Docker Desktop con WSL 2,
incluida instalación si hace falta. Antes de elevación, reinicio o cambios
importantes de Windows exige informar la operación y obtener confirmación puntual.
La aprobación general no equivale a esa confirmación. No cambiar configuración
de arranque, servicios ni características adicionales por inferencia.

Confirmación solicitada en esta sesión: habilitar estas dos características,
con elevación, sin reiniciar:

```powershell
dism.exe /Online /Enable-Feature /FeatureName:VirtualMachinePlatform /All /NoRestart
dism.exe /Online /Enable-Feature /FeatureName:Microsoft-Windows-Subsystem-Linux /All /NoRestart
```

Estado de esta confirmación al preparar el documento: PENDIENTE.
No se ejecutó DISM ni se lanzó instalador. Si queda reinicio pendiente, solicitar
otra confirmación antes de reiniciar. No cambiar Hyper-V/BCD/BIOS por esta solicitud.

## Compatibilidad comprobada (solo lectura)

- Windows 11 Pro x64, build 26200, versión 10.0.26200.
- CPU Intel Core i7-8850H, virtualización de firmware y SLAT habilitados.
- Memoria: 34.083.934.208 bytes (aproximadamente 32 GiB).
- WSL 2.1.5.0, kernel 5.15.146.1-2; sin distribuciones instaladas.
- VirtualMachinePlatform y Microsoft-Windows-Subsystem-Linux: InstallState=2
  (deshabilitadas). HypervisorPlatform también deshabilitada; no se solicita
  habilitarla para esta preparación de WSL 2.
- HypervisorPresent=false. No se ha diagnosticado ni modificado el arranque.
- LanmanServer en ejecución, inicio automático.
- Sesión actual sin elevación administrativa.
- Docker Desktop no encontrado en PATH, instalación estándar de Program Files
  ni listado exacto del paquete Docker.DockerDesktop en origen winget.
- C: con aproximadamente 10,2 GiB libres; no hay otro disco de archivos enumerado.
  No se hizo limpieza ni se descargaron imágenes/instaladores. Antes de una
  descarga grande, revisar espacio y tamaño real; no asumir capacidad suficiente.

Una consulta inicial de winget quiso usar msstore y se canceló al no aceptar
contratos de ese origen. La comprobación posterior limitó --source winget;
no se aceptaron contratos ni se instaló software.

## Preparación prevista tras superar la puerta de Windows

1. Verificar características y necesidad de reinicio; no instalar distribuciones
   Ubuntu adicionales si Docker puede gestionar su backend sin ellas.
2. Resolver espacio y consultar versión real del instalador oficial; verificar
   firma Authenticode y procedencia. Preferir instalación por usuario si la
   versión disponible la admite; no crear servicio privilegiado innecesario.
3. Pedir confirmación puntual si ese instalador necesita elevación o otro cambio
   importante. No aceptar condiciones comerciales o suscripciones nuevas por
   inferencia ni habilitar inicio automático sin necesidad.
4. Exportar copia selectiva del piloto mediante manifiesto/hashes, incluyendo
   núcleo Python y sus 17 pruebas locales. Nunca usar el repositorio entero como
   contexto Docker ni montar sus rutas, perfil del usuario o socket Docker.
5. Ejecutar Python, Codex CLI y OpenCode dentro del aislamiento. El host solo
   prepara/transporta artefactos mediante operaciones fijas revisadas; no ejecuta
   comandos que lleguen en respuestas de modelos.
6. Probar aislamiento con red deshabilitada primero. Para autenticación e
   inferencia, habilitar solo el acceso a proveedores necesario, sin acceso a
   servicios del host ni credenciales/endpoints de publicación.
7. Superar comprobaciones negativas y solo después completar adaptadores,
   sesiones reales y aceptación única con el orquestador y verificador actuales.

## CLI y autenticación

Versiones npm comprobadas mediante metadatos oficiales del registro, sin instalar
en Windows: @openai/codex 0.167.2 y opencode-ai 1.19.0 disponibles.
Mantener esas versiones inicialmente para no introducir una migración de CLI.

Codex local confirma `login --device-auth`; la documentación oficial lo propone
para entornos sin navegador. Realizar login NUEVO dentro del entorno aislado y
mantener la caché en almacenamiento exclusivo del contenedor; no copiar auth.json
del host. El propietario realizará la autorización de identidad necesaria sin
pegar códigos/tokens en el chat o en evidencias.

DeepSeek se conectará mediante el mecanismo nativo de OpenCode y credencial
introducida en terminal privada, dentro del entorno aislado. Confirmar proveedor
y modelo efectivos dentro de la versión 1.19.0 antes de ejecutar inferencia;
no leer ni copiar las credenciales existentes del host. Si requiere credencial
no disponible o cambio de facturación, detener esa pieza y solicitarlo.

No se ha realizado ningún login, inferencia, handshake nuevo ni agente sobre
código durante esta preparación. La autenticación todavía no está demostrada.

## Fuentes consultadas

- Docker: https://docs.docker.com/desktop/setup/install/windows-install/
- Microsoft: https://learn.microsoft.com/en-us/windows/wsl/install-manual
- Codex: https://learn.chatgpt.com/docs/auth
- OpenCode: https://opencode.ai/docs/providers/

Las páginas actuales pueden describir versiones posteriores a las CLI fijadas;
comprobar su ayuda real en el contenedor. No sustituir evidencia de ejecución por
esta preparación documental. GATES_PASS global, QA y VERIFIED siguen pendientes.
