# STAGING-001 — Diagnóstico fallo masivo FTPS (92 subidos / 8819 errores) y corrección

Estado: **BLOCKED** (sin smoke, sin VERIFIED). Despliegue parcial del 2026-10-15: subidos=92, errores=8819, respaldados=0, backup=`backup-20261015-212305/`. No repetir el bulk anterior; reanudar con el transportador corregido.

## 1. Causa probable

Error predominante `EOF occurred in violation of protocol (_ssl.c:2406)` en `storbinary()`.

- El servidor es Pure-FTPd con FTPS explícito y exige (o favorece) **reutilización de la sesión TLS del canal de control en cada canal de datos**. `ftplib.FTP_TLS` estándar no la reutiliza: cada STOR/RETR negocia sesión nueva y el servidor aborta el canal de datos. Los primeros archivos pasan (92) y luego el canal queda inservible.
- El script anterior usaba **una sola conexión de control para ~8911 archivos con RETR+STOR por archivo** (~18000 canales de datos), sin reconexión ni reintentos: tras el primer canal roto, todos los STOR siguientes fallan en cascada (8819).
- No es cuota (`.ftpquota` intacto, 92 archivos sí entraron) ni credenciales (auth OK, marcador OK). Timeout/NAT contribuye, pero el patrón EOF masivo apunta a sesión TLS no reutilizada.
- Además se asumía integridad por `storbinary()` sin error; con parciales, hay que verificar por SIZE y publicar por rename.

## 2. Corrección aplicada (`scripts/staging_ftps_one_shot.py`, misma cuenta, mismo manifest, mismo confinamiento)

- `ReuseFTP_TLS.ntransfercmd()`: envuelve cada socket de datos con `session=self.sock.session` (reúso de sesión TLS). TLS sigue activo y verificado; ningún flag lo desactiva; no se ignoran errores SSL indiscriminadamente (solo se reconecta ante errores recuperables tipificados).
- Reconexión automática: ante error recuperable se cierra, se re-autentica con la **misma contraseña introducida una vez** y se sigue en la misma raíz virtual. La contraseña vive solo en memoria del proceso.
- Reintentos limitados con pausas progresivas: 3 reintentos/archivo (2s/5s/10s) + **cortacircuito a 20 fallos consecutivos**: detiene en vez de generar miles de errores.
- Verificación real: tras STOR se exige `SIZE` remoto == tamaño local; si difiere o no hay SIZE, el archivo cuenta como fallido y queda en pendientes. En resume, `SIZE` igual = verificado y se omite (sin re-subir).
- Subida segura: `STOR <ruta>.part` + `SIZE` + `RENAME` a definitiva + `SIZE` final; el `.part` se borra ante fallo. Los parciales nunca quedan como definitivos.
- Backup que preserva el original: en resume con `--backup-dir backup-20261015-212305` no se pisa lo ya respaldado (el backup del primer despliegue queda como punto de restauración válido; respaldados=0 es esperable en primera instalación).
- Resumen por ejecución: total/verificados/transferidos/reintentados/fallidos/pendientes + backup usado + primeros pendientes.
- Confinamiento, exclusiones (`.env`, `storage/`, `.ftpquota`, `.git/`, `node_modules`, `tmp-boot-001/`) y rollback por lista intactos.

## 3. Prueba controlada antes del bulk (mismo directorio autorizado)

- ` --probe`: K transferencias consecutivas STOR+SIZE+RETR+DELE de archivos efímeros bajo `backup-probe-<ts>/` (misma raíz virtual, sin tocar la app). Si falla, no se inicia el bulk.
- `--limit N` / `--probe-first`: permite validar con las primeras N entradas reales del manifest antes del total.

## 4. Reanudación del parcial (stateless, por SIZE)

El resume no re-sube lo verificado: compara cada `SIZE` remoto con el local y solo completa faltantes/diferentes. Reutiliza `backup-20261015-212305/` sin pisarlo. Orden: no-`public/` primero, `public/` al final.

Comando exacto (terminal interactiva, la contraseña se pide una vez por ejecución):

```powershell
Set-Location 'C:\Users\Mario\PhpstormProjects\stockflash'
py -3 scripts/staging_ftps_one_shot.py --probe --probe-files 5
py -3 scripts/staging_ftps_one_shot.py --deploy --manifest scripts/staging-manifest-20261016.txt --resume --backup-dir backup-20261015-212305
```

Secuencia: si `PROBE_PASS 5/5`, ejecutar el resume; si `PROBE_FAIL`, detenerse y reportar las últimas líneas (sin contraseña). No ejecutar smoke ni declarar VERIFIED hasta completar el resume.

## 5. Contingencia (misma cuenta, sin usuarios nuevos ni otro protocolo)

Si `ReuseFTP_TLS` siguiera siendo incompatible con el servidor, el plan B es `lftp` con la **misma cuenta FTPS** (`lftp -e 'mirror --reverse ...' admin@stockflash-dev.tusitioexpress.cl`), que implementa reuso de sesión y reintentos nativos. Solo si el probe Python falla de forma persistente; no se crea ningún usuario ni se habilita otro protocolo.
