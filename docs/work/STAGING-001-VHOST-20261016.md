# STAGING-001 — Estado despliegue automatizado + vhost caído (20261016)

## Archivos: DESPLEGADOS y verificados en servidor

- 9 partes ZIP (8911 archivos) extraídas con centinelas OK; `artisan`, `public/index.php`, `public/build/{manifest.json,assets}`, `vendor/autoload.php` presentes.
- Reconciliación limpia; duplicado `stockflash-dev-app/` anidado y `public/public/` movidos a `quarantine-20261015-230221/` (sin borrados); probes y ZIP único retirados.
- Backup verificado por listado: `backup-20261015-231022/` (11 rutas) + anteriores intactos.
- `.env` creado una vez (overwrite=0; segunda subida rechazada = preservado, no clobber). `storage/*` y `bootstrap/cache` creados. Sin migraciones.
- SHA publicado: `54073d0447e60ad876ff7134d73ad28acd21e50b`.

## Red: vhost staging CAÍDO (no es la app, no es este cliente)

- `GET https://stockflash-dev.tusitioexpress.cl/` (y `/robots.txt`, y `http://`) → reset TCP persistente, incluso tras 4 min sin tráfico y desde egress externo (webfetch también falla). cPanel :2083 y FTPS :21 en la misma IP responden bien.
- Conclusión: el vhost está caído a nivel servidor (config/crash/throttling de la cuenta). Ningún 500 de Laravel resetea TCP; ni siquiera estáticos responden.
- Por eso NO hubo rollback (fallo NET ≠ fallo de app) y NO se declara nada más. Gates: S1–S5/S7 PASS, S6 FAIL honesto.

## Lecciones API ya aplicadas en `scripts/sf_deploy_staging.py`

UAPI plano `{status,data}` (sin envoltura `result`); mutaciones solo vía API2 `fileop` (`destfiles`, `doubledecode=1`, rutas ABSOLUTAS); `unlink/rmdir` no sirven (result 1 falso) → `trash` + re-listado; `list_files` nunca muestra dotfiles → `.env` solo con `overwrite=0` (sin leer, sin clobber); extracciones largas mueren → 9 partes con centinela; fallos NET no disparan rollback.

## Siguiente paso (una acción, sin hammering)

1. Propietario: revisar el vhost en cPanel (o pedir al hosting que lo revise): `stockflash-dev → DocumentRoot .../stockflash-dev-app/public`, sin redirecciones raras; si hay inmunify/fail2ban, desbanear. No tocar archivos.
2. Avisar aquí. Yo corro `--smoke` UNA vez: si 200 StockFlash → gates → QA → publish. Si sigue RST → escalar al hosting con esta evidencia.
