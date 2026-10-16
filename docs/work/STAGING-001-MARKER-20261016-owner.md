# STAGING-001 — Evidencia marcador público (G4, ejecución del propietario 2026-10-16)

Estado: **MARKER_PASS** (ejecución owner-reported, no repetida por instrucción expresa).

- URL comprobada: `https://stockflash-dev.tusitioexpress.cl/sf-marker-<token>.txt` -> HTTP 200 con contenido del marcador.
- Ubicación FTPS: `public/` bajo raíz virtual `/` (= `/home/grupoint/stockflash-dev-app`; sin concatenar ruta física).
- Raíz virtual confirmada: la sesión FTPS presenta `stockflash-dev-app` como `/` (PWD + LIST del propietario).
- Marcador **retirado** tras la comprobación (retirado por el propietario; nada pendiente en `public/`).
- Efecto: Document Root del subdominio sirve `.../stockflash-dev-app/public` (verificado por marcador, no solo por reporte de cambio).
- Fuente: reporte del propietario 2026-10-16 (auth FTPS correcta, marcador publicado en `public/`, HTTP 200, marcador eliminado). No se repite `--marker` ni se solicitan credenciales nuevamente.
- Restricciones: sin secretos en este archivo; sin `.env`; sin cambios fuera de la raíz autorizada.
