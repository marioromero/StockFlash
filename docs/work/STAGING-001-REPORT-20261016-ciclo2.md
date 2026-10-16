# STAGING-001 — Reporte tecnico ciclo 2 (20261016)

Estado: **BLOCKED** (un unico impedimento accionable; cero bytes desplegados, no hay VERIFIED).
SHA esperado del codigo: `54073d0447e60ad876ff7134d73ad28acd21e50b` (HEAD local = `origin/main`).
SHA desplegado: **ninguno** — no se transfirio ningun archivo al servidor en ciclo 2.

## 1. Stages ejecutados (sin sesion FTPS, sin exponer secreto)
- Revalidacion paquete G2: `composer.lock` 311245 B + `package-lock.json` 84467 B presentes;
  `vendor/autoload.php` presente; `public/build/manifest.json` con `resources/js/app.ts` y
  `resources/css/app.css` (`assets/app-BVfdHwKA.js`, `assets/app-BZvL8UBI.css`). Sin secretos en paquete.
- Preflight FTPS sin auth: TCP `ftp.tusitioexpress.cl:21` OK, banner `Pure-FTPd [privsep] [TLS]`,
  `AUTH TLS` -> `234 AUTH TLS OK`. Login NO intentado (variable `STAGING_FTPS_PASSWORD` ausente;
  no se simula auth ni se toca el prompt interactivo en este entorno).
- Smoke HTTPS baseline real: `GET https://stockflash-dev.tusitioexpress.cl/` -> **200**, 1160 B,
  cuerpo `Index of /` (autoindex LiteSpeed), sin `StockFlash`, sin `public/build`. App aun no servida;
  Document Root reportado sigue UNVERIFIED (requiere marcador).
- Verificador: `py -3 scripts/sf_verify.py STAGING-001` -> **GATES_FAIL** (S1/S2/S3/S7 PASS, S4/S5/S6 FAIL),
  reporte generado `docs/work/STAGING-001-VERIFY-20261015-210428.md`. Sin commit/push/deploy.
- Plan rollback: `docs/work/STAGING-001-ROLLBACK-PLAN-20261016.md` (procedimiento inventario+manifest+
  backup/rollback, `.env` y `storage/` remotos preservados; ejecucion pendiente de sesion).

## 2. Resultado G1-G7 esperado tras ciclo 2
- S1 (G1 aprobado y activo): **PASS** — `status: APPROVED` + activa en `CURRENT.md`.
- S2 (G2 paquete determinista): **PASS** — revalidado arriba.
- S3 (G3 FTPS y transportador): **PARCIAL** — transportador verificado PASS local + TLS confirmado;
  sin sesion autenticada, sin lista de raiz virtual, sin transferencia.
- S4 (G4 marcador publico): **FAIL** — requiere sesion: subir marcador a `public/`, `GET` HTTPS 200 con
  contenido, retirar. Artefacto exigido: `STAGING-001-MARKER-*.md` con MARKER_PASS + URL + retirado.
- S5 (G5 manifest/rollback): **FAIL** — requiere inventario remoto + manifest + backup antes de
  sobrescribir. Artefacto exigido: `STAGING-001-MANIFEST-*.md` con ROLLBACK_READY + inventario + `.env`.
  El plan de este ciclo NO cuenta como evidencia (a proposito no usa ese nombre).
- S6 (G6 smoke HTTPS): **FAIL** — baseline 200 autoindex sin StockFlash. Exigido: SMOKE_PASS_200 +
  StockFlash + URL en `STAGING-001-SMOKE-*.md` tras despliegue unico.
- S7 (G7 reporte + revision): **PARCIAL** — este reporte + package + plan + VERIFY fechados; falta QA
  independiente APROBADO con evidencia de servidor. No se declara VERIFIED.

## 3. SHA desplegado / bytes
Cero bytes transferidos. Nada que revertir en servidor. Trabajo ciclo 1 conservado intacto en working tree.

## 4. Evidencia smoke
`GET https://stockflash-dev.tusitioexpress.cl/ -> 200`, `Index of /`, 1160 B, sin StockFlash ni assets Vite.

## 5. Artefactos ciclo 2 (sin secretos)
- `docs/work/STAGING-001-PACKAGE.md` (revalidado ciclo 2, ver nota al pie).
- `docs/work/STAGING-001-ROLLBACK-PLAN-20261016.md` (nuevo, plan pendiente de sesion).
- `docs/work/STAGING-001-REPORT-20261016-ciclo2.md` (este archivo).
- `docs/work/STAGING-001-VERIFY-20261015-210428.md` (generado por `sf_verify.py`, GATES_FAIL honesto).
- Conservados ciclo 1: `scripts/staging_ftps_one_shot.py`, `scripts/sf_verify.py` (modificado),
  REPORT ciclo 1, VERIFYs previos.

## 6. BLOCKED — unico impedimento accionable
**Falta el secreto FTPS en tiempo de ejecucion.** El operador debe aportarlo SOLO via entorno o prompt
seguro y ejecutar la secuencia (una linea por paso, en orden): `--list` (G3+S5 inventario), `--marker`
(G4 Document Root + retiro), despliegue unico con manifest/rollback (G5), smoke HTTPS (G6),
`sf_verify.py STAGING-001` (GATES_PASS), QA independiente y publicacion Git ordinaria.

Comando exacto por paso (operador local, secreto nunca en historial visible como valor):
`$env:STAGING_FTPS_PASSWORD='<valor>'` en sesion privada, luego
`py -3 scripts/staging_ftps_one_shot.py --list` y a continuacion `--marker`, despliegue, smoke y verify.

## 7. Advertencia de seguridad (Paso 0 docs/setup/CPANEL-PASO-A-PASO.md)
La contrasena FTPS del propietario quedo expuesta en historial de chat: considerarla comprometida y
ROTARLA tras el staging conforme al Paso 0, actualizando coordinadamente cualquier servicio que la use.
Ningun agente debe transcribirla, guardarla ni pasarla como argumento visible o a logs.
