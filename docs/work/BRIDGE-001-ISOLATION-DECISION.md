# BRIDGE-001 — Decisión de aislamiento pendiente

Estado: OWNER_DECISION_REQUIRED. Fecha: 2026-11-12.

## Bloqueo y evidencia

El piloto y original comparten `C:/Users/Mario/PhpstormProjects/stockflash/.git`.
La configuración de OpenCode permite bash general y lectura de `.env`; reglas
textuales y exclusión del publisher no son barrera del sistema operativo.
No se ejecutaron agentes sobre código ni intentos reales de commit/push.

Comprobaciones locales:

- `Get-Command docker,podman,wsl,bwrap -ErrorAction SilentlyContinue`: solo wsl.exe.
- `wsl --list --quiet`: sin distribuciones enumeradas.
- `Get-Item C:/Windows/System32/WindowsSandbox.exe -ErrorAction SilentlyContinue`:
  sin ejecutable encontrado en esa ruta.
- `py -3 -B scripts/sf_bridge.py inspect`: identifica Git compartido y reporta BLOCKED.
- `py -3 -B scripts/sf_bridge.py check-isolation`: rechaza ejecución antes de lanzar CLI.

La ausencia de estos ejecutables no demuestra que Windows carezca de toda opción
de virtualización. Sí impide usar inmediatamente las opciones inspeccionadas.
No se instalaron herramientas ni se cambiaron permisos, servicios o credenciales.

## Alternativas concretas

A. Autorizar preparar un contenedor local, mediante runtime compatible disponible
o instalación aprobada. Recomendación: exportación selectiva del piloto a volumen
de trabajo sin montar el original, `.git` compartido, perfil del usuario, `.env`
ni credenciales GitHub. Sin remoto publicable, acceso de red limitado a proveedores
necesarios y autenticación nativa aislada. Importar solo diferencias permitidas
tras validar inventario y hashes. Reutilizar exactamente el harness del piloto.
La instalación y nueva autenticación se revisan antes de realizarlas; no copiar
archivos de autenticación existentes ni asumir compatibilidad con ChatGPT Plus.

B. Usar una VM ya disponible o autorizar su preparación con las mismas restricciones.
Mayor preparación, pero evita desarrollar seguridad específica de Windows.

Una copia Git separada sin aislamiento de procesos no basta: todavía podría
acceder a rutas originales o credenciales del usuario. Un wrapper o hook tampoco.

## Decisión solicitada

Autorizar la preparación de A, o indicar un entorno aislado existente para B.
No se pide aprobación de subtareas ordinarias: falta una capacidad del entorno
cuya instalación/configuración y eventual autenticación exceden esta autorización.
No afecta contratos comerciales, datos ni fiscalidad.

Hasta entonces, conservar dispatch bloqueado y no ejecutar la aceptación real.
Estado humano: PENDIENTE; ninguna prueba o respuesta técnica concede autorización.
