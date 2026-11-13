# BRIDGE-001 — Fallo de recibo: causa y corrección

## Evidencia del fallo real

Run administrativo: `4a6b5001cb8f4dc9b56182964f96aad4`.
Estado privado consultado sin volcar credenciales/SDDL:
`status=FAILED`, `stage=receipt`, `reason=MethodInvocationException`.
El recibo principal quedó en PROVISIONING. El temporal del mismo run contiene
PROVISIONED_NOT_ISOLATION_APPROVED, cuatro raíces protegidas, una raíz de
herramientas de solo lectura y existing_owner_access_preserved=true.
Esto ubica el fallo al reemplazar el recibo final, después de configurar las ACL.

La cuenta StockFlashAgent SÍ existe y está habilitada; pertenece a Users y no
a Administrators. El preflight con la cuenta existente también comprueba que
la credencial nativa coincide y no hay otras pertenencias locales inesperadas.
No se reseteó la contraseña ni se modificó manualmente el recibo para simular éxito.

## Causa reproducida

La función Write-AtomicJson llamaba:

```powershell
[IO.File]::Replace($temp,$Path,$null)
```

Windows PowerShell 5.1 convierte ese argumento string sin tipo en cadena vacía.
Replace rechaza la ruta de backup vacía con ArgumentException, HRESULT
`-2147024809` (0x80070057): «La ruta de acceso no tiene un formato válido».
El wrapper anterior conservó solo MethodInvocationException y perdió el detalle.

Se reprodujo primero en archivos sintéticos y después en la FUNCIÓN REAL extraída
por AST, sin ejecutar el cuerpo administrativo. No fue necesario elevar ni usar
secretos. La misma prueba pasó tras cambiar exclusivamente el argumento a:

```powershell
[IO.File]::Replace($temp,$Path,[NullString]::Value)
```

## Corrección y verificación

- Provision-Agent.ps1 usa null string tipado; conserva reemplazo atómico.
- El diagnóstico privado ahora conserva tipo de excepción raíz, HRESULT y número
  de línea, sin mensaje/argumentos potencialmente sensibles.
- Start-Provision.ps1 muestra etapa/motivo del mismo run cuando falla el proceso,
  en lugar de limitarse a ADMINISTRATIVE_PROCESS_FAILED.
- Test-AtomicReceipt.ps1 prueba la función real, solo en directorio temporal:
  creación inicial y seis reemplazos sucesivos, incluidos FAILED y recuperación.
  Antes del cambio FAIL reproducible; después ATOMIC_RECEIPT_REGRESSION_PASS.
- Los siete scripts PowerShell pasan parseo. Las 17 pruebas del núcleo no cambiaron.

## Estado parcial y seguridad del reintento

Comprobado antes de entregar el comando:

- Sin procesos de aprovisionamiento detectados; desktop.lock se abre en exclusivo.
- ACL Deny específicas de StockFlashAgent presentes en perfil, original,
  metadatos Git y piloto; denegación de escritura/cambio de permisos presente en PHP.
- Comparación con los cinco backups SDDL: propietario, grupo y reglas de los
  demás principales preservados en todos los casos.
- El propietario abre/cierra handles ReadWrite de AGENTS de ambos árboles desde
  su token normal, sin escribir bytes.
- Preflight real con cuenta/marcador parciales: PROVISION_PREFLIGHT_PASS_NO_CHANGES.

El estado es reanudable mediante el lanzador. No se relanzó aprovisionamiento
desde Codex y no se borraron temporales/respaldos ni se maquilló el estado privado.
El reintento reutiliza cuenta y credencial; no pretende crear otra cuenta.

## Comando de escritorio

En PowerShell normal del propietario, aceptar UAC cuando aparezca:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Users\Mario\PhpstormProjects\stockflash-codex-pilot\scripts\bridge_windows\Start-Provision.ps1"
```

Si surgió otro proceso después de este diagnóstico, no ejecutar en paralelo.
El lanzador toma el bloqueo antes de pedir elevación. Tras completar recibos,
prepara archivos y ejecuta probe/pruebas bajo StockFlashAgent, nunca administrador.

No hay todavía prueba ejecutada como esa identidad, ISOLATION_PASS ni aceptación
integral. Sin agentes elevados, cambios a main, migraciones, despliegues o
publicación funcional. Solo Markdown se publica en rama piloto.
