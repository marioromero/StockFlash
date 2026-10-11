# Especificación General v2.2 — POS + Inventario Modular Omnicanal
## Anexo técnico · Integraciones y operación backend

**Estado:** v2.2 **CONSOLIDADO EN REVISIÓN** · Base histórica: 04-10-2026  
**Documentos normativos del proyecto:**
1. `especificacion-v2.2-parte-1-CONSOLIDADO-EN-REVISION.md`
2. `especificacion-v2.2-parte-2-CONSOLIDADO-EN-REVISION.md`
3. este Anexo.

Este documento no agrega un producto distinto. Convierte en contratos de implementación las decisiones ya congeladas para **Laravel + MariaDB + cPanel + WooCommerce + DTE**, de forma que otra IA o un desarrollador pueda implementar el backend sin reinterpretar la arquitectura.

---

## 0. Cómo leer estos tres documentos

### 0.1 Fuente de verdad por materia

| Materia | Fuente de verdad |
|---|---|
| Alcance, reglas de negocio, prioridad, qué debe hacer el producto | **Parte 1** |
| Persistencia, restricciones, claves, FK, migraciones y retención | **Parte 2** |
| Endpoints externos, secuencias técnicas, idempotencia HTTP, jobs, cron, reintentos y pruebas de contrato | **Este Anexo** |

Si aparece una contradicción entre documentos, **no se elige silenciosamente una interpretación**. Se detiene la implementación de esa pieza, se registra la contradicción y se corrige el documento de mayor nivel correspondiente. El Anexo nunca puede crear una regla de negocio que contradiga la Parte 1 ni una tabla inexistente que contradiga la Parte 2.

### 0.2 Principios no negociables del backend

1. **Una instalación por cliente.** No existe multitenancy lógico compartiendo datos de clientes diferentes.
2. **cPanel primero.** P1 funciona sin daemon, Supervisor, Redis ni Docker obligatorio.
3. **Escalable por contrato.** Cola, cache, almacenamiento y adaptadores se consumen mediante interfaces/configuración; Redis/workers persistentes/VPS no requieren reescribir dominio.
4. **Todo efecto externo es asíncrono e idempotente.** Una transacción de negocio no depende de que WooCommerce o el proveedor DTE respondan en ese mismo request salvo donde Parte 1 lo exija expresamente.
5. **WooCommerce es un canal, no el dominio.** Ningún modelo de inventario/venta conoce tablas internas de WordPress.
6. **Sin acceso directo a SQL de WordPress/WooCommerce.** Solo REST pública, webhooks y endpoints de autorización oficiales.
7. **Webhooks son señales, no fuente histórica confiable única.** Se complementan con relectura REST y polling con solape.
8. **No hay redondeo silencioso de stock.** Una incompatibilidad de cantidades se informa y bloquea solo la publicación afectada.
9. **Una sola autoridad por dato mutable.** En P1 el POS manda stock/precio; Woo manda estado/pago/reembolso monetario de pedidos online; FiscalEngine manda DTE.
10. **Datos externos se preservan antes de interpretarlos** cuando son necesarios para idempotencia, conciliación o evidencia técnica.
11. **UTC internamente.** Fechas de negocio/reportes se convierten a `America/Santiago` en límites explícitos.
12. **Secretos nunca en logs.** API keys, webhook secret, claves fiscales, TOTP y claves de cifrado se enmascaran/redactan.

---

## 0A. Complemento obligatorio v2.2 — orden de implementación (owner 10-10-2026)

1. La primera oferta comercial POS/Bodega puede operar **sin adaptador DTE integrado**: registrar venta/comprobante interno, descontar stock, imputar métodos de pago, abrir/cerrar caja. En esta modalidad `tax_documentation_mode=external`, NO generar documentos fiscales `pending`, NO invocar jobs de emisión, NO marcar como emitido. Folio externo optativo, si el usuario decide declararlo; no se requiere para completar la venta.
2. La ruta de adaptación DTE de este anexo es válida **solo cuando el producto Fiscal esté habilitado** y `tax_documentation_mode=integrated` esté autorizado/configurado. Los contratos de emisión, idempotencia, XML, retries y devoluciones fiscales originales permanecen para esa etapa. La documentación externa no se valida con la API del SII y no se mezcla con `fiscal_documents` del emisor integrado.
3. Las licencias gestionadas por StockFlash Control son **comerciales** (`products_enabled`); se traduce a módulos técnicos internos necesarios (`modules_enabled`) y se valida RBAC por operación. Clientes no necesitan comprar POS para contratar SyncWP ni comprar Bodega para contratar POS. Servicios técnicos compartidos sí pueden ser indispensables en ejecución sin cobrar otro módulo.
4. `Demo Vitrina` puede reiniciarse con datos sintéticos y ninguna integración productiva. `Gratis Persistente` nunca se reinicia automáticamente; límites de 50 variantes vendibles y 500 ventas/mes, conteo y manejo exactos sujetos al ADR abierto. El control de límites es LOCAL; el heartbeat no debe transmitir conteos comerciales hasta modificación expresamente aprobada de LIC-02.
5. **No usar este complemento para inferir conformidad tributaria** de una operación sin DTE integrado; la obligación documental del comercio permanece.



## 1. Perfil de ejecución inicial: cPanel sin procesos persistentes

### 1.1 Único cron del sistema

El hosting configura **un solo cron cada minuto**:

```text
* * * * * cd /ruta/app && php artisan schedule:run >> /dev/null 2>&1
```

No se configura un cron adicional que invoque `queue:work` directamente. Laravel Scheduler es el coordinador único.

### 1.2 Worker acotado

El scheduler ejecuta cada minuto un worker de cola `database` equivalente conceptualmente a:

```text
php artisan queue:work database --stop-when-empty --max-time=45 --tries=<config>
```

Contrato:
- se programa con `withoutOverlapping`;
- el lock usa el cache driver común de la instalación;
- en perfil cPanel el cache driver inicial puede ser `database`;
- `cache` y `cache_locks` deben existir antes de activar jobs;
- el worker termina cuando vacía la cola o al alcanzar el tiempo máximo;
- **no** debe sobrevivir indefinidamente entre ejecuciones del cron;
- un job individual debe tener timeout menor que el límite del proceso y ser suficientemente pequeño para reintentarse de forma segura.

La cifra `45` es una configuración operativa inicial, no una regla de dominio; debe quedar por debajo de un minuto para evitar acumulación de workers en hosting limitado.

### 1.3 Tareas programadas mínimas

| Tarea | Cadencia inicial | Regla |
|---|---:|---|
| Worker acotado | cada minuto | `withoutOverlapping` |
| Barrido de reservas | `reservation_sweep_seconds` (mínimo práctico 60 s en cPanel) | tickets vencidos se liberan; online se reconcilia |
| Polling canales | `channel_poll_minutes` | por canal activo; ventana solapada |
| Debounce de stock/precio | event-driven + cola | nunca requiere cron dedicado |
| Reparación/health de integraciones | cada 5 min aprox. | liviano; no bloquear ventas |
| Reconciliación nocturna | `channel_reconcile_time` | por canal; fecha local convertida a UTC |
| Agregados de reportes | `report_refresh_minutes` | incremental |
| Backup | `backup_time` | un solo proceso; lock |
| Réplica/verificación XML fiscal | frecuente/incremental | solo pendientes |
| Retención | diario | por políticas Parte 2 |
| Heartbeat central | diario | solo metadatos técnicos permitidos |

### 1.4 Zona horaria del scheduler

El servidor/aplicación opera en UTC. Evitar programar múltiples tareas con reglas dependientes de DST directamente en `America/Santiago`. Para horas configurables de negocio (`03:00`, `02:00`, etc.), el job coordinador calcula la ventana local correspondiente y la convierte a UTC; además guarda/usa una marca de última ejecución para impedir dobles ejecuciones durante cambios de horario.

### 1.5 Evolución a perfil Scale

Cuando el cliente supere el perfil compartido:
- `QUEUE_CONNECTION=redis` o cola externa;
- workers persistentes;
- cache/locks Redis;
- object storage;
- múltiples procesos/servidores si corresponde.

**No cambia:** `SalesChannelAdapter`, `StockPublisher`, `FiscalDocumentIssuer`, acciones de dominio, estados ni tablas de negocio. Los locks deben seguir usando un backend compartido entre workers.

---

## 2. Contrato HTTP general para integraciones

### 2.1 Cliente HTTP

Todo adaptador externo debe definir:
- timeout de conexión;
- timeout total;
- User-Agent identificable con nombre y versión del producto;
- correlación interna (`correlation_id`) en logs;
- redacción de secretos;
- clasificación consistente de errores.

### 2.2 Clasificación de respuestas

| Caso | Acción predeterminada |
|---|---|
| 2xx | éxito; validar schema mínimo antes de confirmar estado |
| 400 / 422 | error de datos/configuración; **no** reintentar ciegamente |
| 401 / 403 | credencial/permiso; marcar integración degradada/roja; no loop de reintentos |
| 404 de recurso esperado | revisar mapping o compatibilidad; no asumir borrado interno automáticamente |
| 409 / conflicto equivalente | reconciliar recurso actual antes de decidir |
| 408 / timeout / error de red | reintento con backoff si la operación es idempotente o verificable |
| 429 | respetar `Retry-After` cuando exista y reprogramar |
| 5xx | backoff exponencial con jitter; llevar a `dead`/alerta después del máximo |
| resultado fiscal incierto | **no reemitir**; consultar/reconciliar primero |

### 2.3 Backoff

Valor inicial sugerido para operaciones reintentables:
`30 s → 2 min → 10 min → 30 min → 2 h`, con jitter. El adaptador puede ajustar la secuencia, pero nunca crear un loop rápido que castigue cPanel o el servicio remoto.

---

## 3. WooCommerce: compatibilidad y límites P1

### 3.1 Interfaz usada

- REST actual: `/wp-json/wc/v3/`.
- Onboarding: `/wc-auth/v1/authorize`.
- Webhooks: REST `/wc/v3/webhooks` + callbacks entrantes.
- **No plugin propio en P1.**
- **No consultas directas** a `wp_posts`, `wp_postmeta`, tablas HPOS ni otras tablas WordPress.
- Requisito previo: HTTPS y REST Woo funcional; el preflight también verifica condiciones del sitio que impidan usar la API.

Esta decisión mantiene compatibilidad con HPOS porque el contrato es la API pública de Woo, no su almacenamiento.

### 3.2 Permiso de credenciales

P1 necesita credenciales `read_write` porque debe:
- leer pedidos/productos/refunds/settings disponibles;
- escribir stock/precio/productos cuando el módulo lo permita;
- crear/actualizar webhooks administrados;
- eventualmente escribir nota privada/estado de pedido según el flujo aprobado.

La API key queda asociada a un usuario Woo con capacidades suficientes. Se recomienda un usuario técnico dedicado cuando la operación del cliente lo permita, no reutilizar una cuenta personal del dueño si puede evitarse.

### 3.3 No usar API keys en query string por defecto

Con HTTPS, las credenciales se envían mediante el mecanismo soportado por Woo/cliente HTTP. Si un hosting/proxy elimina `Authorization`, el asistente reporta una incompatibilidad accionable. P1 **no** activa automáticamente un fallback de claves en query string porque puede exponerlas en logs o herramientas intermedias.

---

## 4. Asistente de conexión WooCommerce

### 4.1 Estado efímero de conexión

No se crea tabla nueva. Antes de redirigir a Woo se genera un intento efímero con:
- `connection_attempt_id`: aleatorio, alta entropía, un solo uso;
- admin que inició;
- `base_url` normalizada esperada;
- sucursal de cumplimiento elegida;
- timestamp de expiración (recomendado: 10–15 min);
- nonce/estado adicional si se implementa;
- callback y return URLs esperadas.

Puede residir en cache/database cache. Al consumirse o expirar se elimina.

### 4.2 Flujo `wc-auth`

1. Admin ingresa URL de tienda y sucursal de cumplimiento.
2. Backend normaliza URL y exige HTTPS.
3. Se crea el intento efímero.
4. Se redirige al navegador a:

```text
https://tienda.example/wc-auth/v1/authorize
  ?app_name=<nombre>
  &scope=read_write
  &user_id=<connection_attempt_id>
  &return_url=<url_resultado>
  &callback_url=<https_callback>
```

5. El usuario autoriza en WooCommerce.
6. Woo envía las credenciales generadas al `callback_url` HTTPS.
7. El callback:
   - valida que el intento exista/no esté usado/no esté vencido;
   - nunca escribe las claves en log;
   - cifra `consumer_key` y `consumer_secret` inmediatamente;
   - ejecuta una llamada de prueba al **mismo base_url esperado**;
   - solo después de una prueba válida marca la conexión utilizable.
8. `return_url` muestra éxito/fracaso y ejecuta/continúa el preflight.
9. El intento queda consumido.

**Importante:** `user_id` de `wc-auth` es una referencia de nuestra aplicación; no se interpreta como ID de usuario Woo.

### 4.3 Fallback manual

El admin puede pegar consumer key/secret generados manualmente en WooCommerce. Se aplican las mismas reglas:
- cifrar inmediatamente;
- nunca re-mostrar el secret completo;
- probar permisos;
- ejecutar preflight;
- fallar de manera accionable.

---

## 5. Preflight WooCommerce

El canal no pasa a “saludable” solo porque `GET /orders` responda. El preflight produce un conjunto de capabilities almacenado en `sales_channels.capabilities`.

### 5.1 Checks mínimos

1. `wc/v3` accesible.
2. credenciales válidas.
3. lectura de productos.
4. escritura de producto/variación requerida por las opciones elegidas (sin modificar datos durante la prueba salvo test controlado).
5. lectura de pedidos.
6. webhooks: listar/crear/actualizar según permisos.
7. refunds: endpoint legible cuando exista pedido apto o mediante introspección/test seguro.
8. Settings API: lectura cuando el usuario lo permita; si falla por permisos, la instalación puede continuar con confirmación manual de Hold Stock.
9. versión Woo/WordPress y datos de plataforma útiles para diagnóstico.
10. soporte de marcas u otras taxonomías opcionales.
11. modo de inventario de productos/variaciones mapeados.
12. compatibilidad de cantidades fraccionarias.

### 5.2 Capabilities orientativas

```text
wc_v3: true|false
orders_read: true|false
orders_write: true|false
products_read: true|false
products_write: true|false
variations_read: true|false
variations_write: true|false
refunds_read: true|false
webhooks_manage: true|false
settings_read: true|false
brands_supported: true|false
fractional_stock_supported: false  # Woo core P1
variation_stock_supported: true|false
parent_stock_mode_detected: true|false
platform_version: ...
```

No es obligatorio usar exactamente estas keys, pero el contrato semántico sí lo es y debe versionarse si cambia.

### 5.3 Capacidad decimal

WooCommerce core documenta `stock_quantity` como **integer**. Por ello:
- `fractional_stock_supported=false` por defecto en el adaptador Woo core;
- nunca se redondea `12.750` a `13` o `12`;
- una variante interna puede seguir vendiéndose/controlándose con decimal en el POS;
- si está mapeada a Woo y el stock publicable tiene fracción, el outbound queda bloqueado y el panel explica la incompatibilidad;
- soportar un plugin de cantidad decimal requiere un **subadaptador/capability explícitamente probado**; no basta detectar que el plugin está instalado.

---

## 6. Webhooks WooCommerce

### 6.1 Webhooks administrados por P1

Mínimo:
- `order.created`
- `order.updated`

El adaptador puede crear otros tópicos de catálogo si aportan valor, pero la sincronización debe seguir funcionando por polling/reconciliación aunque falte uno.

Los IDs, tópicos y estado esperado se guardan dentro de `sales_channels.config`, no en una tabla nueva.

### 6.2 Secreto

Al crear los webhooks, el POS suministra un secreto aleatorio dedicado y lo guarda cifrado en `webhook_secret_encrypted`. No se reutiliza la API consumer secret si no es necesario.

### 6.3 Receptor: algoritmo exacto

Para cada POST entrante:

1. aplicar límite de tamaño **antes** de parsear JSON (`webhook_max_body_kb`); exceso → `413`;
2. conservar el **cuerpo crudo**;
3. identificar el canal por endpoint/ruta y configuración, no por campos manipulables del body;
4. obtener `X-WC-Webhook-Signature`;
5. calcular HMAC-SHA256 sobre el cuerpo crudo con el secret del webhook y comparar en tiempo constante con la firma base64;
6. firma inválida → `401/403`, registrar metadatos mínimos y aplicar rate limit de firmas inválidas;
7. extraer `X-WC-Webhook-Delivery-ID`;
8. insertar `webhook_receipts` con UNIQUE de entrega;
9. si ya existe la entrega, responder `2xx` sin volver a ejecutar dominio;
10. si es nueva, encolar el procesamiento;
11. responder `2xx` **inmediatamente después de persistir**.

Un webhook válido no espera a importar pedido, sincronizar stock ni emitir DTE dentro del request.

### 6.4 Rate limiting

WooCommerce puede desactivar un webhook tras fallos consecutivos. Por tanto:
- no responder `429` a tráfico válido normal solo por superar un umbral arbitrario;
- `webhook_valid_warn_per_min` sirve para alerta/backpressure interna;
- aplicar protección más estricta a firmas inválidas/IPs abusivas;
- si existe un ataque volumétrico real, la protección de infraestructura puede limitarlo, pero no debe confundirse con una regla funcional normal.

### 6.5 Webhook como señal

El payload persistido sirve para diagnóstico/identidad del evento, pero el job de `order.updated` **puede y normalmente debe GETear el pedido actual** antes de aplicar una transición importante. Esto hace tolerables:
- webhooks fuera de orden;
- redelivery;
- demora;
- cambios posteriores al evento original.

### 6.6 Reparación

El job `repair_webhooks`:
1. lista webhooks gestionados;
2. compara tópicos, URL, status y configuración esperada;
3. si falta → crea;
4. si está `disabled`/incorrecto → reactiva o recrea;
5. actualiza IDs/config del canal;
6. registra el resultado en health, sin duplicar indiscriminadamente webhooks.

---

## 7. Polling a prueba de huecos

### 7.1 Regla general

El polling es deliberadamente **at-least-once**. Puede repetir recursos; no puede perderlos.

### 7.2 Pedidos

Al iniciar un ciclo:
- `T0 = now UTC` fijo para ese ciclo;
- `from = orders_cursor_at - channel_poll_overlap_minutes`;
- consultar en orden de modificación, paginado, hasta `T0`.

Forma conceptual:

```text
GET /wp-json/wc/v3/orders
  ?modified_after=<from ISO8601>
  &modified_before=<T0 ISO8601>
  &dates_are_gmt=true
  &orderby=modified
  &order=asc
  &per_page=100
  &page=N
```

Contrato:
- procesar todas las páginas (`X-WP-TotalPages` cuando esté disponible);
- cada pedido entra por el mismo `OrderIngestionService` idempotente que los webhooks;
- los empates de timestamp pueden repetirse por el solape;
- **solo después de terminar todas las páginas exitosamente** actualizar `orders_cursor_at=T0`;
- si falla cualquier página, no avanzar cursor.

### 7.3 Productos

Aplicar el mismo patrón a catálogo cuando el endpoint/capacidad soporte filtros por modificación. Si una versión/plugin no ofrece exactamente el filtro esperado, el adaptador usa una estrategia compatible que mantenga el principio: cursor seguro + solape + idempotencia, sin asumir SQL interno.

---

## 8. Mapeo de catálogo y productos variables

### 8.1 Producto simple

```text
channel_mappings.entity_type = variant
external_id = product_id Woo
external_sub_id = NULL
```

Stock/precio se publica sobre el endpoint del producto.

### 8.2 Variación

```text
external_id = parent product_id
external_sub_id = variation_id
```

Stock/precio se publica sobre:

```text
/products/{external_id}/variations/{external_sub_id}
```

### 8.3 Stock del padre

Si Woo devuelve para una variación `manage_stock='parent'`, P1 **no finge control individual**. Si el mapping interno necesita stock individual:
- bloquear `sync_stock` para ese mapping;
- mostrar la causa;
- permitir al admin alinear Woo a stock por variación;
- solo después activar publicación.

No se distribuye artificialmente un contador de stock padre entre múltiples variantes internas.

### 8.4 Backorders

P1 no implementa backorders como regla normal del canal. El asistente recomienda/valida `backorders=no` para los recursos administrados. Si se detectan backorders activos:
- no borrar el pedido que ya exista;
- importar y abrir la incidencia adecuada ante faltante;
- advertir configuración incompatible con la promesa de stock sincronizado P1.

---

## 9. Publicación de stock

### 9.1 El POS publica valores absolutos

Nunca se envía “+1/-1” como verdad final. Se recalcula:

```text
publicable = max(0,
  stock_fisico_sucursal
  - reservas_internas_relevantes
  - safety_stock
)
```

con la excepción de reservas `hold_owner='channel'` del mismo canal según RN-29/RN-30.

### 9.2 Serialización

Lock lógico sugerido:

```text
channel:{channel_id}:stock:{variant_id}
```

Secuencia del worker:
1. adquiere `Cache::lock`;
2. relee stock actual/reservas/safety stock;
3. recalcula publicable;
4. valida compatibilidad decimal;
5. compara con `published_quantity`;
6. si igual → `done` sin llamada remota;
7. si distinto → PUT remoto absoluto;
8. confirma respuesta y relee el dato crítico cuando el adaptador lo estime necesario;
9. actualiza `published_quantity`/`published_at`;
10. libera lock.

El payload del outbox puede contener IDs/razón, pero **no es la fuente del número que se publica**.

### 9.3 Cantidad fraccionaria

Si `publicable % 1 != 0` y `fractional_stock_supported=false`:
- no PUT;
- no cambiar `published_quantity`;
- job termina en estado que requiera intervención/configuración, no como éxito;
- health muestra el mapping afectado;
- jamás redondear.

---

## 10. Publicación de precio

### 10.1 Fuente

El worker consulta `PricingEngine` en el contexto del canal/sucursal/lista aplicable y obtiene el precio efectivo que P1 debe publicar.

### 10.2 WooCommerce

Si POS es autoridad (`sync_prices_enabled=1`):
- escribir `regular_price` con formato decimal aceptado por Woo;
- asegurar que `sale_price` y sus fechas no sigan alterando el precio efectivo;
- P1 no preserva una promoción Woo paralela que contradiga al POS;
- actualizar `published_price` solo tras confirmación.

Esto evita que el POS publique `regular_price=10.000` mientras Woo continúe vendiendo a un `sale_price` antiguo.

### 10.3 Promociones Woo administradas localmente

Quedan P2. Para habilitarlas se requerirá una regla explícita de precedencia; nunca se activa dejando dos autoridades ambiguas.

---

## 11. Ingesta de pedidos WooCommerce

### 11.1 Punto de entrada único

Webhook y polling llaman al mismo flujo:

```text
OrderIngestionService.ingest(channel_id, external_order_id)
```

El servicio GETea la representación actual del pedido cuando corresponda y opera por `(channel_id, external_order_id)`.

### 11.2 Identidad

- una `sale` por pedido externo;
- una `sale_item` por línea externa;
- una `sale_payment` principal de canal por venta P1;
- refunds son recursos aparte, no se infieren solo del `status` del pedido.

### 11.3 Estados

Usar la tabla de mapeo definida en RN-39. Un evento viejo **no puede retroceder** un pedido si la relectura actual muestra un estado posterior.

### 11.4 Importes y tributos del pedido

Para pedidos de canal:
- preservar los totales efectivos informados por Woo por línea y por pedido;
- no recalcular retrospectivamente los impuestos del pedido usando la configuración tributaria actual del POS;
- `unit_price` puede derivarse para presentación/consistencia, pero los totales de la operación importada no se sustituyen por un cálculo local que cambie lo cobrado;
- shipping/fees se conservan donde el modelo vigente lo contempla (snapshots/totales/revisión); si una diferencia afecta el documento fiscal, abrir incidencia antes de emitir.

### 11.5 Producto no vinculado

Si la línea remota no tiene mapping:
- crear `sale_item` con `variant_id=NULL`;
- conservar `external_line_id`, SKU/nombre snapshot, cantidad e importes;
- abrir `unlinked_item`;
- no crear efecto físico de stock para esa línea hasta vincular;
- nunca descartar la venta.

### 11.6 Stock insuficiente

Importar siempre. Abrir `stock_shortage`. No rechazar/ocultar el pedido por una inconsistencia local.

---

## 12. Pago online

### 12.1 Autoridad P1

WooCommerce es autoridad del estado de pago online. El POS **normaliza**, no crea una segunda confirmación independiente.

Datos relevantes:
- `payment_method` / external gateway ID;
- título del gateway para diagnóstico;
- `transaction_id` si existe;
- `date_paid`/`date_paid_gmt`;
- estado actual del pedido.

### 12.2 Gateway sin mapping

- conservar el external gateway ID;
- crear/actualizar el pago pendiente cuando corresponda;
- abrir `payment_mismatch`;
- bloquear automáticamente cualquier paso que dependa de una traducción fiscal no resuelta;
- no perder el pedido.

### 12.3 Confirmaciones desde el POS hacia Woo

Fuera de P1 salvo los cambios explícitamente necesarios para el flujo aprobado (p. ej., nota/estado de despacho). Confirmar manualmente un gateway Woo desde el POS como segunda autoridad queda P2.

---

## 13. Hold Stock y reservas

### 13.1 Configuración

El preflight intenta leer Settings. Si no tiene permiso:
- pedir al admin confirmar `holds_pending_stock` y minutos;
- guardar la decisión/config detectada en el canal;
- mostrarla en diagnóstico.

### 13.2 `pending`

Si Woo mantiene Hold Stock:
- importar pedido;
- crear reserva local `hold_owner='channel'` para bloquear POS;
- no restarla de nuevo del valor publicado a ese mismo canal;
- `expires_at` = momento de reconciliación, no de liberación automática.

### 13.3 `on-hold` / `processing`

La reserva pasa a `hold_owner='internal'` cuando el canal ya ha descontado su stock según la política definida. Como el POS publica valor absoluto, la siguiente publicación vuelve a alinear ambos lados.

### 13.4 Expiración

Nunca liberar reserva online solo porque el reloj venció. Primero consultar pedido:
- cancelado/fallido → liberar;
- sigue pendiente → renovar;
- on-hold → mantener + incidencia si supera plazo operativo;
- canal no accesible → mantener + backoff + alerta.

### 13.5 `checkout-draft`

No se importa P1. La reserva previa al pedido es responsabilidad de Woo. `safety_stock` reduce el riesgo, pero no existe garantía matemática de cero sobreventa durante un checkout aún no visible como pedido importable.

---

## 14. Refunds WooCommerce

### 14.1 No depender del estado global

Un refund parcial puede coexistir con un pedido cuyo status no sea `refunded`. Por ello, `order.updated` y polling llaman a `reconcile_refunds` cuando detectan cambios/IDs de refunds y, cuando sea necesario, consultan:

```text
GET /wp-json/wc/v3/orders/{order_id}/refunds
```

### 14.2 Identidad

- encabezado externo: UNIQUE `(channel_id, external_refund_id)`;
- línea de producto: UNIQUE `(return_id, sale_item_id)`;
- relecturas son idempotentes.

### 14.3 Normalización de importes

Internamente se guardan magnitudes positivas:
- `refund_amount`;
- `quantity`;
- `net_amount`;
- `tax_amount`;
- `line_amount`.

El adaptador preserva el valor real devuelto por Woo y normaliza el signo. No recalcula el refund desde el precio histórico.

Woo puede incluir:
- productos;
- shipping;
- fees;
- un `amount` de encabezado que no coincide con suma de productos.

Por eso `refund_amount` es autoridad del total monetario importado y `sale_return_items` preserva solo la porción asignada a líneas de producto.

### 14.4 Restock triestado

Refund de canal:
- `restock=NULL`: supervisor aún no decide efecto físico;
- `1`: reingresa stock;
- `0`: no reingresa/merma.

No completar mientras una línea vinculada siga `NULL`.

### 14.5 Diferencia no asignada

Si:

```text
refund_amount != SUM(line_amount)
```

mantener `refund_review` y bloquear nota de crédito automática. P1 no inventa cómo imputar shipping/fee sin revisión.

### 14.6 Autoridad

Woo es autoridad del **reembolso monetario online** en P1. El refund importado no se “cancela” como si no hubiera ocurrido. El POS decide efecto físico y FiscalEngine decide/documenta el efecto tributario.

### 14.7 Crear refunds desde POS

P2. Antes de usar capacidades modernas como `compute_totals`, el adaptador deberá detectarlas por contrato porque versiones antiguas pueden ignorar parámetros desconocidos. No asumir compatibilidad por versión sin preflight/test.

---

## 15. Outbox de canales (`channel_sync_events`)

### 15.1 Qué es

Es exclusivamente la bandeja de salida/acciones diferidas de **canales**. No se reutiliza artificialmente para DTE.

Acciones típicas:
- `push_stock`;
- `push_price`;
- `import_order`;
- `reconcile_order`;
- `reconcile_refunds`;
- `repair_webhooks`.

### 15.2 Dedupe

La deduplicación activa evita acumular múltiples `pending` equivalentes, pero un evento `done` **no bloquea** eventos futuros con la misma entidad/acción.

El job de stock/precio debe releer la verdad actual al tomar lock; por tanto, fusionar eventos no pierde el estado final.

### 15.3 Estado `processing`

Puede existir un sucesor `pending` mientras otro está `processing`. `Cache::lock` por entidad evita que dos publicaciones efectivas se pisen. El sucesor vuelve a leer el estado vigente cuando obtenga el lock.

---

## 16. DTE: contrato del adaptador fiscal

### 16.1 Límite de responsabilidad

- el contribuyente es el emisor;
- el proveedor DTE es infraestructura externa;
- SII no se trata como una API directa genérica del producto P1;
- el hecho de que un proveedor figure en información del SII **no significa certificación del software por SII**;
- no se diseña integración directa con RCV como parte del MVP.

### 16.2 Contrato mínimo conceptual

`FiscalDocumentIssuer` debe poder expresar al menos:

```text
issue(payload, idempotency_key) -> IssuanceResult
query(reference/idempotency_key) -> IssuanceStatus
fetchXml(external_document_id) -> bytes|reference
fetchPdf(external_document_id) -> optional bytes|reference
health() -> IntegrationHealth
```

Nombres concretos pueden variar, semántica no.

### 16.3 Estados

- `pending`
- `sending`
- `issued`
- `rejected`
- `error`
- `unknown`

`unknown` significa: el request pudo haber llegado pero no sabemos el resultado. **Nunca** volver a emitir a ciegas; consultar primero por referencia/idempotency key.

### 16.4 Sucursal

`branch_id` se traduce a configuración fiscal del proveedor dentro de `fiscal_provider_config`; no llenar `branches` de campos específicos de una empresa DTE.

### 16.5 Documento original y nota de crédito

Un DTE `issued` permanece emitido. Una corrección comercial/fiscal se representa por otro documento que lo referencia; no se “borra” ni muta la historia para liberar unicidad.

### 16.6 Archivos

- XML: persistir en disco fiscal según Parte 2;
- hash/metadata en DB;
- réplica incremental;
- PDF: caché regenerable, no verdad primaria;
- health advierte si XML tiene una sola copia física.

---

## 17. Backups y recuperación

### 17.1 `backup:run`

Incluye como mínimo:
- dump de base de datos;
- libro de supresiones;
- configuración recuperable definida por Parte 1/2;
- manifiesto con versión, schema y hashes/referencias de XML fiscales.

No duplica todas las carpetas XML históricas dentro de cada backup diario.

### 17.2 XML fiscales

Se replican incrementalmente al destino configurado. Por cada XML:
- persistir localmente;
- calcular/verificar hash;
- copiar al destino;
- marcar `xml_replicated_at` tras confirmar copia;
- verificar progresivamente y marcar `xml_verified_at`.

### 17.3 Secretos

Una restauración necesita también las claves con las que se cifraron datos/credenciales. Las claves maestras no se guardan desprotegidas dentro del mismo backup. El recovery package/procedimiento P1★ debe permitir reconstruir la instalación sin exponerlas públicamente.

### 17.4 Prueba de restore

Gate productivo:
- restaurar DB;
- recuperar claves;
- comprobar acceso a XML fiscales;
- ejecutar migración/health;
- re-aplicar libro de supresiones cuando corresponda;
- probar login/admin y una lectura de integración sin mutar producción.

---

## 18. Seguridad de endpoints técnicos

### 18.1 Endpoints públicos mínimos

Solo los necesarios:
- callback `wc-auth`;
- return/status de onboarding autenticado cuando corresponda;
- webhook Woo;
- callback fiscal si el proveedor lo exige;
- endpoints de licencia/control central según contrato.

No exponer una API CRUD pública general de ventas/inventario en P1.

### 18.2 Protección

- TLS;
- CSRF según naturaleza del endpoint (webhooks autenticados por firma no usan sesión CSRF);
- validación estricta de método/content-type/tamaño;
- HMAC/callback secret cuando aplique;
- rate limit diferenciado;
- no reflejar payload/secret en mensajes de error;
- logs estructurados sin PII innecesaria;
- correlation ID.

---

## 19. Observabilidad mínima sin convertirlo en SIEM

El panel de integración debe poder responder, para cada canal:
- ¿conecta?
- ¿último webhook válido?
- ¿último polling completo?
- ¿última reconciliación?
- ¿webhooks administrados están activos?
- ¿cuántos jobs pendientes/fallidos?
- ¿antigüedad del más viejo?
- ¿hay mappings incompatibles (decimal/stock parent)?
- ¿hay reservas que no se pudieron reconciliar?
- ¿último error accionable?

No intenta detectar intrusiones de red ni reemplazar herramientas de seguridad externas.

---

## 20. Contratos de error que la UI debe poder explicar

Códigos internos sugeridos (los nombres son estables aunque el texto visible sea traducible):

```text
WC_AUTH_FAILED
WC_PERMISSION_MISSING
WC_REST_UNAVAILABLE
WC_AUTH_HEADER_STRIPPED
WC_WEBHOOK_DISABLED
WC_WEBHOOK_SIGNATURE_INVALID
WC_POLL_INCOMPLETE
WC_PRODUCT_UNLINKED
WC_STOCK_PARENT_MODE_INCOMPATIBLE
WC_FRACTIONAL_STOCK_UNSUPPORTED
WC_PRICE_AUTHORITY_CONFLICT
WC_PAYMENT_MAPPING_MISSING
WC_STOCK_SHORTAGE
WC_REFUND_REVIEW_REQUIRED
WC_REMOTE_UNAVAILABLE
FISCAL_AUTHORITY_NOT_CONFIRMED
FISCAL_PROVIDER_UNAVAILABLE
FISCAL_RESULT_UNKNOWN
FISCAL_DOCUMENT_REJECTED
BACKUP_XML_SINGLE_COPY
BACKUP_RESTORE_NOT_TESTED
```

No es obligatorio crear una tabla para este catálogo; pueden ser enums/constantes de aplicación mientras no contradigan tablas de incidencias del dominio.

---

## 21. Matriz mínima de pruebas de contrato WooCommerce

### 21.1 Conexión

- `wc-auth` aceptado → claves cifradas + preflight.
- usuario cancela → no crear canal operativo.
- callback repetido → intento de un solo uso, no sobrescribir claves.
- callback expirado → rechazo seguro.
- API key manual válida/inválida.
- servidor elimina Authorization → error accionable, no query-string automático.

### 21.2 Webhooks

- firma correcta.
- firma incorrecta.
- body modificado después de firmar → rechazo.
- mismo Delivery-ID dos veces → un solo procesamiento.
- `order.updated` viejo llega después de otro nuevo → relectura evita retroceso.
- receptor persiste y responde antes del procesamiento pesado.
- simular 5 fallos y comprobar `repair_webhooks` ante webhook deshabilitado.

### 21.3 Polling

- más de 100 pedidos → todas las páginas.
- varios con mismo timestamp modificado.
- falla página intermedia → cursor no avanza.
- repetir ventana → no duplica ventas/líneas/pagos.

### 21.4 Catálogo

- producto simple.
- variable con stock por variación.
- variación con `manage_stock='parent'` → incompatibilidad.
- mapping huérfano/remoto borrado.
- producto remoto no vinculado.
- Woo sin soporte de marcas → degradación de esa capacidad solamente.

### 21.5 Stock

- publicación absoluta.
- debounce 10 cambios → estado final único correcto.
- dos workers concurrentes → lock impide valor viejo final.
- stock entero → publica.
- stock `3.500` con Woo core → **no redondea/no publica**, alerta.
- reserva pending con Hold Stock.
- reserva vencida con Woo caído → no libera.
- cancelación remota → libera.

### 21.6 Precio

- regular price sin sale price.
- Woo tenía sale_price previo → al ser POS autoridad, precio efectivo queda igual al publicado por POS.
- dos jobs de precio concurrentes → último estado interno gana.

### 21.7 Pedidos

- pending → importado/reservado.
- on-hold/processing/completed.
- cancelled/failed.
- línea no mapeada → venta se conserva + incidencia.
- gateway desconocido → pago se conserva + incidencia.
- cliente por RUT único.
- email con dos coincidencias → no asociación arbitraria.

### 21.8 Refunds

- total.
- parcial sin cambio de estado global.
- dos refunds contra misma orden.
- redelivery del mismo refund → no duplica.
- refund de producto → importes por línea preservados.
- refund con shipping/fee → diferencia de encabezado abre revisión y bloquea NC automática.
- `restock=NULL` bloquea completar.

---

## 22. Pruebas backend/MariaDB/cPanel bloqueantes antes del primer despliegue

1. Ejecutar migraciones completas desde cero en **MariaDB 10.6 mínimo**.
2. Ejecutar ARQ-13 y comprobar UNIQUE parciales con columnas generadas.
3. Instalar cache/database + locks y verificar `Cache::lock` concurrente.
4. Simular dos `schedule:run` solapados; el worker/job protegido no debe duplicarse.
5. Cola database: job exitoso, retry, dead/fail y recuperación manual.
6. 10.000+ eventos/outbox de prueba: índices y tiempos aceptables.
7. Restore real de backup.
8. Validar que el hosting permite requests salientes HTTPS hacia Woo/proveedor DTE.
9. Validar que el dominio público de la instalación recibe callbacks HTTPS desde Internet.
10. Probar ModSecurity/WAF del hosting con PUT/POST Woo; documentar remediación si bloquea métodos.
11. Test de memoria/tiempo con 100 registros por página y cargas representativas.

---

## 23. Gate de una nueva tienda WooCommerce

Una tienda se declara **lista para sincronización productiva** solo si:

- [ ] HTTPS válido.
- [ ] `wc/v3` accesible.
- [ ] pretty permalinks/REST funcional.
- [ ] autenticación `read_write` validada.
- [ ] sucursal de fulfillment seleccionada.
- [ ] productos/variaciones leíbles.
- [ ] permisos de escritura requeridos probados.
- [ ] webhooks administrados creados y verificados.
- [ ] política Hold Stock leída o confirmada manualmente.
- [ ] backorders revisados.
- [ ] mappings iniciales revisados.
- [ ] productos con stock decimal incompatibles identificados.
- [ ] variaciones con stock parent incompatibles identificadas.
- [ ] autoridad de precio confirmada; promociones Woo conflictivas desactivadas para mappings gestionados.
- [ ] gateways mapeados o conscientemente pendientes.
- [ ] autoridad DTE confirmada cuando módulo fiscal esté activo.
- [ ] prueba de pedido en ambiente controlado aprobada.
- [ ] prueba de cancelación/reserva aprobada.
- [ ] prueba de refund/revisión aprobada.
- [ ] reconciliación manual inicial sin diferencias críticas.

---

## 24. Qué queda deliberadamente fuera de P1

- plugin WordPress propio obligatorio;
- lectura/escritura directa de tablas WP/Woo;
- Shopify/MercadoLibre implementados (solo arquitectura preparada);
- stock fraccionario Woo mediante plugins desconocidos;
- backorders como flujo soportado integralmente;
- split fulfillment/múltiples sucursales para un pedido online;
- promociones administradas simultáneamente por Woo y POS;
- creación de refund monetario Woo desde POS;
- soporte genérico de todo plugin chileno de RUT/factura; se resuelve por mappings/config del adaptador;
- integración directa universal con SII/RCV;
- SIEM/GRC/compliance general;
- workers persistentes obligatorios.

---

## 25. Fuentes oficiales verificadas

**Fecha de investigación:** 04-10-2026. Estas URLs se usan para validar interfaces externas; la suite de contratos contra cada instalación concreta sigue siendo obligatoria.

### WooCommerce / WordPress

- REST API actual/recomendada: `https://developer.woocommerce.com/docs/apis/rest-api/v3/`
- REST API requisitos/autenticación general: `https://developer.woocommerce.com/docs/apis/rest-api/`
- Authentication + `wc-auth`: `https://developer.woocommerce.com/docs/apis/rest-api/authentication`
- Orders: `https://developer.woocommerce.com/docs/apis/rest-api/v3/orders/`
- Products: `https://developer.woocommerce.com/docs/apis/rest-api/v3/products/`
- Product variations: `https://developer.woocommerce.com/docs/apis/rest-api/v3/product-variations/`
- Order refunds: `https://developer.woocommerce.com/docs/apis/rest-api/v3/order-refunds/`
- Webhooks API: `https://developer.woocommerce.com/docs/apis/rest-api/v3/webhooks/`
- Webhook behavior: `https://developer.woocommerce.com/docs/best-practices/urls-and-routing/webhooks/`
- Settings API: `https://developer.woocommerce.com/docs/apis/rest-api/v3/settings/`
- HPOS: `https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/`
- WordPress REST pagination: `https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/`

### Laravel

- Scheduler: `https://laravel.com/docs/12.x/scheduling`
- Cache / atomic locks: `https://laravel.com/docs/12.x/cache`
- Queues: `https://laravel.com/docs/12.x/queues`

### Chile

- SII — proveedores de soluciones de facturación electrónica: `https://www.sii.cl/servicios_online/1039-emp_prov_fe-1184.html`
- BCN LeyChile — Ley 21.719: `https://www.bcn.cl/leychile/Navegar?idNorma=1209272`

---

## 26. Cierre de arquitectura

Con Parte 1 + Parte 2 + este Anexo se congela el siguiente contrato:

> **PUNTO 0 HISTÓRICO v2.1: preservado en `historico-v2.1`. En v2.2 las decisiones del propietario quedan incorporadas, pero los puntos marcados ABIERTOS no se implementan hasta ADR y gate correspondiente.**

Esto no significa que cada proveedor/hosting/version futura esté garantizado sin pruebas. Significa que la arquitectura define **cómo detectar una incompatibilidad, degradar una capacidad sin corromper el dominio y evolucionar el adaptador sin reescribir el POS**.

Una observación descubierta durante programación solo reabre PUNTO 0 si demuestra una contradicción estructural del dominio/modelo. Diferencias de endpoint, permiso, versión, plugin o infraestructura se resuelven primero dentro del adaptador, preflight, configuración o contrato de despliegue.
