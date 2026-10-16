# STAGING-001 — Despliegue automatizado via cPanel API (diseño + puesta en marcha)

## 1. Decisión y mecanismo seleccionado

Despliegue automatizado con **un solo ZIP via API de cPanel** (UAPI `Fileman` + API2 `Fileman::fileop`), misma cuenta, sin FTPS por archivo, sin SFTP, sin usuarios nuevos, sin cambios a cPanel global.

Capacidades verificadas el 2026-10-16 (sin auth, sin asumir):
- API base `https://cp012.servidoresph.com:2083` con TLS válido (cert Let's Encrypt para ese hostname; conectar por `ftp.tusitioexpress.cl:2083` falla hostname — el script usa el hostname del certificado).
- Página de login cPanel responde 200 (mecanismo disponible).
- Funciones usadas (`upload_files`, `list_files`, `make_directory`, `fileop` mkdir/copy/move/rename/unlink/rmdir/extract) se **validan en vivo** por `--probe` antes de tocar la app; ante forma de respuesta desconocida el script se detiene (BLOCKED), no asume éxito.
- Alternativa SSH evaluada: **no implementada** — el harness la deniega (`ssh/scp/sftp` en `deny` para todos los agentes) y el alcance vigente es FTPS/API con la cuenta existente. Queda como fallback manual del propietario vía ADR, no como ruta automática.
- Estado staging al verificar: `GET https://stockflash-dev.tusitioexpress.cl/` resetea la conexión (TCP reset persistente en dos intentos). El smoke del pipeline reintenta 5 veces con backoff y reporta el resultado real; no se asume 200.

## 2. Piezas implementadas (harness intacto, sin Feature Packet nueva)

- `scripts/sf_credstore.py` — credenciales DPAPI por usuario en `%LOCALAPPDATA%\StockFlash\creds\` (cero dependencias, fuera de Git). Probado roundtrip local.
- `scripts/sf_deploy_staging.py` — pipeline 12 pasos: version → pregates → build → zip → backup → upload+extract → reconcile (cuarentena, sin borrar) → boot (`.env`/`storage/` solo si ausentes) → probe PHP → smoke → evidencias → rollback automático si hay backup recuperable. Modos `--check/--probe/--deploy/--rollback/--smoke`. `--check` PASS local el 2026-10-16 (HEAD `54073d04` = origin/main, manifest 8911, validadores OK, credenciales pendientes).
- `.opencode/commands/sf-deploy-staging.md` — comando reutilizable `/sf-deploy-staging` (gates y QA del harness vigente, jamás VERIFIED).
- `.gitignore` — excluye el ZIP y artefactos de despliegue.
- Autorización del propietario 2026-10-16: `opencode.json` sin `ask` (solo `deny` administrativos); el comando ejecuta directo.

## 3. ÚNICA acción del propietario (una vez, luego todo automático)

1. Entrar a cPanel de `grupoint` → **Seguridad → Tokens de API → Crear** con nombre `stockflash-deploy` (alcance de archivos). Copiar el token (se muestra una sola vez).
2. En una terminal interactiva del proyecto, una sola vez:
   `py -3 scripts/sf_credstore.py set cpanel_user` → escribir `grupoint`
   `py -3 scripts/sf_credstore.py set cpanel_token` → pegar el token
   (Opcional si cambia el servidor: `py -3 scripts/sf_credstore.py set cpanel_host`.)
3. Avisar en este chat. Desde ahí OpenCode ejecuta `/sf-deploy-staging` completo (probe → deploy → smoke → gates → QA → publish) sin más intervenciones.

Rotación: si el token se expone, revocarlo en el mismo panel y repetir el paso 2.

## 4. Primera instalación (estado actual: parciales del FTPS masivo)

- El backup previo `backup-20261015-212305/` se conserva siempre (reconcile y rollback lo ignoran).
- La extracción del ZIP sobrescribe los parciales con contenido correcto; los ajenos a la lista se mueven a `quarantine-<ts>/` (ningún borrado).
- `.env` remoto y `storage/` con datos jamás se sobrescriben; si `.env` falta se crea una vez desde `.env.example` + `APP_KEY` generada. Sin migraciones R3.
- ZIPs futuros completos mientras el tamaño sea razonable; misma operatoria sin Packet nueva por cada publicación.
