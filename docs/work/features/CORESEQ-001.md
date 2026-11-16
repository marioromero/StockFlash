# CORESEQ-001 — Asignación de numeración transaccional (CORE-13 / RN-74)

status: VERIFIED
approval_basis: existing_approved_contract_owner_delegation_2026-11-16
new_business_decisions: NONE
publication_mode: AUTO_GIT
execution_mode: ONE_SMALL_WAVE
stage: R3_CORE
active: docs/work/CURRENT.md

## Propósito y fuentes existentes

Implementar el servicio técnico de numeración que utilizarán POS/Bodega/Compras después, sin introducir venta, stock, impresiones, pantallas ni un producto nuevo. Reutilizar la tabla ya migrada `document_sequences` y NO cambiar esquema.
- Parte 1 §7.11 RN-74: número visible único **por ámbito real**. Sucursal para ticket, sale, return, transfer (origen), count, goods_receipt, cash_session; instalación para purchase_order. Comprobante interno de venta reutiliza el sale_number, no secuencia fiscal distinta.
- Parte 2 §4.14: `document_sequences(doc_type, scope_type, scope_id, prefix, next_number, padding)`, UNIQUE `(doc_type,scope_type,scope_id)`; doc_type enum `ticket`, `sale`, `return`, `transfer`, `count`, `goods_receipt`, `cash_session`, `purchase_order`. `scope_id=0` en instalación; en sucursal referencia a `branches.id`. Prefijo por defecto código de sucursal; padding 6, next_number 1, prefix max 10.
- Parte 1 §8.1 CORE-13 asignación bajo bloqueo. Sin nuevas opciones comerciales.

## Implementación autorizada

1. Crear UN servicio interno acotado (p. ej. `app/Support/DocumentSequenceAllocator.php`), sin interfaz/rutas públicas ni capa genérica. Entrada tipada: doc_type válido y branch_id requerido para tipos por sucursal; `purchase_order` solo instalación. Devolver string visible y, si es útil, número correlativo, de forma documentada. No asignar correlativos desde controladores inexistentes.
2. Asignación **atómica** en transacción DB, con fila creada idempotentemente si falta y `lockForUpdate` para reservar/incrementar. Índice UNIQUE protege creación concurrente. Usar patrón compatible con Laravel y MariaDB y retry limitado de deadlock si procede, no implementar locks propios ni mecanismos de distribución. Integrarse correctamente en transacciones exteriores: si la operación receptora falla, la numeración se revierte cuando corresponda; no afirmar garantía de ausencia total de saltos ante rollback ya consolidado.
3. Respetar ámbitos: dos sucursales y dos doc_type diferentes no comparten secuencia; transferencia usa la sucursal de **origen**; orden de compra usa `installation/0`. Solo usar sucursales existentes, sin eliminar/reutilizar secuencias. Para sucursal con código mayor que `prefix VARCHAR(10)`, **NO truncar silenciosamente** ni alterar contrato: exigir prefijo válido explícito o documentar bloqueo de ese caso sin inventar política comercial; escalar solo si la contradicción exige decisión de producto.
4. Prefix/padding: conservar valores de la fila existente sin sobrescribirlos; al crear, prefijo código sucursal si cabe, padding 6, next_number 1. Formatear según prefix + número rellenado con ceros; evitar colisiones visibles por truncamientos, overflows, datos corruptos; devolver error controlado antes de mutar si el formato no es seguro. No cambiar códigos/propiedades de sucursales.
5. Pruebas de servicio y BD: primera reserva y siguiente incremento, prefix/padding, dos sucursales separadas, todos doc_type del enum, purchase_order ámbito instalación, transferencia origen, rollback y error por tipo/ámbito inválido, filas existentes preservadas, idempotencia de creación, único del esquema. Pruebas de simultaneidad real con MariaDB aislada SOLO cuando exista ambiente DEV autorizado; en CI SQLite probar secuencia/atomicidad y documentar límite (no afirmar prueba de concurrencia MariaDB). Se prohíbe acceso o migración a BD remota.
6. Reutilizar el sistema vigente `scripts/sf_verify.py` añadiendo gates declarativos del packet, regresión AUTH/BRANCH/USERS/CFG001-003, suite completa PHP, TS, build y chequeo de contratos. QA independiente, publicación `AUTO_GIT` al pasar + CI exacta SHA. El manual HTML NO requiere una nueva entrada: es servicio interno invisible; actualizar metadata de seguimiento solo si aporta información.

## Criterios EARS

- CUANDO se solicita un doc_type y ámbito válidos, EL SISTEMA DEBE entregar el correlativo siguiente bajo bloqueo y guardar el siguiente número de forma atómica.
- CUANDO dos ámbitos o tipos difieren, EL SISTEMA NO DEBE compartir estado ni prefijo indebidamente.
- CUANDO hay rollback del proceso receptor antes de commit, LA NUMERACIÓN DEBE conservar consistencia transaccional.
- CUANDO el tipo, la sucursal, el prefijo o la capacidad numérica son inválidos, EL SISTEMA DEBE fallar sin asignación ni cambio parcial.
- CUANDO otro módulo utilice el servicio dentro de su transacción, EL SERVICIO DEBE respetar ese límite transaccional sin inventar persistencia adicional.

## Límites

No modificar contratos v2.2 ni tablas/migraciones, no endpoints públicos, no fiscal integrado/DTE, no implementar ticket/cotización/venta/devolución/compra/stock/caja, no nuevas dependencias/agentes/scripts/locks externos/servicios web, no desplegar, no tocar BD remota, secretos, `.env`, manual HTML o roadmap HTML (autoría GPT). Si surge una decisión comercial material genuina, documentar alternativas/recomendación y bloquear solo la parte afectada. No pedir aprobación de este orden técnico ni crear nuevos protocolos.

## Cierre técnico externo (2026-11-16)

status: VERIFIED
verificador_externo: ChatGPT
registro: `docs/work/CORESEQ-001-CLOSURE-20261116.md`
code_sha: `ceb50e02616d9d1185e4f849c7f2a8760d80f6fc`
resultado: 16/16 gates PASS, 282 pruebas PHP / 1936 aserciones, QA independiente APROBADO, CI 2/2 SUCCESS
limites: concurrencia simultánea real sobre MariaDB NO ejecutada; pendiente predespliegue. Sin DB remota ni deploy.
