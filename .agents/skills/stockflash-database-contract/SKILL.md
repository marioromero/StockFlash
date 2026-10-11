---
name: stockflash-database-contract
description: Verifica migraciones y restricciones MariaDB contra Parte 2 sin reinterpretar el esquema.
compatibility: opencode
metadata:
  product: stockflash
---

# stockflash-database-contract


Leer Parte 2 §1.2 y tabla implicada. Respetar lotes, claves, restricciones, DECIMAL, UTC, append-only y unique en columnas generadas. ARQ-13 prueba MariaDB 10.6 mínimo antes del primer despliegue. Si feature requiere nuevo campo/índice no definido, emitir solicitud de decisión y NO alterar Parte 2. Nunca conectarse a DB producción ni ejecutar migraciones remotas desde agente.
