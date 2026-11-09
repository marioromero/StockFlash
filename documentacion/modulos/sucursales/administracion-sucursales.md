# Administración de sucursales (StockFlash)

> **Estado de la funcionalidad:** implementada en el Feature Packet `BRANCH-001` (estado `APPROVED`, aprobado por el propietario el 2026-11-09). La funcionalidad está disponible en el entorno local de desarrollo; su publicación y verificación externa se registran por separado.

Este manual explica cómo listar, crear, editar, activar, desactivar y eliminar lógicamente las sucursales de una instalación de StockFlash.

## 1. Nombre de la funcionalidad

Administración de sucursales (CRUD de sucursales).

## 2. ¿Para qué sirve?

Permite mantener las sucursales que usan el sistema: un local de venta, una bodega o una sucursal online. Cada sucursal tiene un código único, un nombre, un tipo, datos de contacto y ajustes operativos. Las sucursales activas son el contexto de operación de los módulos de venta, inventario, caja y reportes.

## 3. ¿Quién puede utilizarla?

Solo usuarios con el permiso **`settings.manage`** (habitualmente el rol **Admin Central**). La autorización se valida en el servidor en cada operación; aunque un enlace no se muestre en pantalla, el servidor rechaza con **403** a quien no tenga el permiso.

La administración de sucursales es global: no requiere seleccionar una sucursal y no utiliza el control de acceso por sucursal. Un Admin Central puede listar y editar una sucursal inactiva desde esta pantalla.

## 4. ¿Cómo acceder?

1. Inicie sesión en StockFlash. Si necesita ayuda, consulte [`general/acceso.md`](../../general/acceso.md).
2. En el **dashboard**, busque el enlace **Sucursales**. El enlace solo aparece si su usuario tiene `settings.manage`.
3. También puede acceder directamente a la ruta `/branches` desde el navegador, siempre que su sesión y sus permisos lo autoricen.
4. Para volver al dashboard use el botón **Volver al dashboard**.

## 5. ¿Cómo utilizarla?

### Listar sucursales

- La pantalla principal muestra el **código**, **nombre**, **tipo**, **comuna/ciudad**, **estado** (Activa/Inactiva) y si **requiere control de caja**.
- Las sucursales se muestran ordenadas por código.
- Si no existe ninguna sucursal, verá un mensaje de bienvenida con el botón **Crear primera sucursal**.
- Las sucursales eliminadas lógicamente no aparecen en el listado.

### Crear una sucursal

1. Haga clic en **Nueva sucursal**.
2. Complete los campos obligatorios: **Código**, **Nombre** y **Tipo**.
3. Complete los datos opcionales de dirección, comuna, ciudad, región y teléfono.
4. Defina si la sucursal estará **activa** y si **requiere control de caja**.
5. Elija los ajustes heredables o deje **Heredar** para usar la configuración general del sistema.
6. Si necesita ajustes avanzados, escriba un objeto JSON en el campo **Configuración adicional**.
7. Haga clic en **Crear sucursal**. Si todo es válido, el sistema vuelve al listado y muestra un mensaje de éxito.

### Editar una sucursal

1. Haga clic en **Editar** en la fila de la sucursal.
2. Modifique los campos permitidos.
3. El **código** se muestra de solo lectura: no puede cambiarse después de crear la sucursal.
4. El **estado** (Activa/Inactiva) se muestra como indicador no editable. Para cambiarlo use **Activar** o **Desactivar** en el listado; la edición no modifica el estado.
5. Haga clic en **Guardar cambios**.

### Desactivar una sucursal

1. Haga clic en **Desactivar**.
2. Confirme la acción en el cuadro de diálogo.
3. La sucursal conserva todos sus datos, pero deja de aceptar nuevas operaciones.
4. Si es la **última sucursal activa**, el sistema muestra una advertencia explícita antes de continuar. La desactivación está permitida; las operaciones que requieren sucursal responderán **403** hasta que se active otra.

### Activar una sucursal

1. Haga clic en **Activar** en una sucursal inactiva.
2. Confirme la acción.
3. El sistema verifica el límite de sucursales activas del plan antes de activarla.

### Eliminar lógicamente una sucursal

1. Haga clic en **Eliminar**.
2. Lea y confirme la advertencia: se aplica un borrado lógico, se conserva la historia y el código queda reservado.
3. La sucursal deja de aparecer en el listado y no puede utilizarse en nuevas operaciones.

## 6. Opciones y configuraciones disponibles

| Campo | Obligatorio | Descripción |
|-------|-------------|-------------|
| Código | Sí | Identificador único de la sucursal. Se normaliza a mayúsculas, admite letras, números y guiones, y tiene un máximo de 30 caracteres. Es inmutable después de crear la sucursal. |
| Nombre | Sí | Nombre visible de la sucursal, máximo 150 caracteres. |
| Tipo | Sí | **Local** (`store`), **Bodega** (`warehouse`) u **Online** (`online`). |
| Dirección | No | Dirección física, máximo 255 caracteres. |
| Comuna | No | Comuna, máximo 100 caracteres. |
| Ciudad | No | Ciudad, máximo 100 caracteres. |
| Región | No | Región, máximo 100 caracteres. |
| Teléfono | No | Teléfono de contacto, máximo 30 caracteres. |
| Sucursal activa | No | Solo al crear: si está marcada, la sucursal nace activa. En edición el estado es informativo y se cambia con **Activar**/**Desactivar** del listado. |
| Requiere control de caja | No | Si está marcada, la sucursal exige control de caja según la configuración del módulo. |
| Flujo de venta | No | **Heredar**, **Directo**, **En dos pasos** o **Ambos**. |
| Código de vendedor | No | **Heredar**, **No solicitarlo**, **Opcional** u **Obligatorio**. |
| Stock negativo | No | **Heredar**, **Permitir** o **No permitir**. |
| Configuración adicional | No | Objeto JSON opcional para ajustes avanzados. No se aceptan listas, valores sueltos, contraseñas, tokens ni secretos. |

Los ajustes heredables usan **Heredar** para tomar la configuración general del sistema. La aplicación no inventa un valor distinto del contrato cuando se elige heredar.

## 7. Reglas y limitaciones

- El **código de sucursal es único e inmutable**. Se normaliza a mayúsculas y se usa como prefijo de numeración visible (regla RN-74). Un intento de cambiarlo se rechaza con un error de validación.
- Una sucursal **eliminada lógicamente conserva su código reservado**. No es posible crear otra sucursal con el mismo código; el camino previsto es elegir otro código o esperar a una funcionalidad futura de restauración.
- La eliminación es siempre **lógica** (`soft delete`). No existe borrado físico ni restauración en esta versión.
- El **estado activo solo se cambia con las acciones dedicadas Activar o Desactivar**, que aplican la verificación del límite de plan y su propia auditoría. La edición de la sucursal no modifica el estado; un intento de enviarlo en la edición se rechaza con un error de validación.
- Se permite **desactivar la última sucursal activa**, con confirmación y advertencia. El dashboard global y la administración de sucursales siguen disponibles, pero las rutas operativas que requieren sucursal responden **403**.
- Las rutas operativas solo aceptan sucursales **existentes y activas**, incluso para usuarios globales.
- Si existe una licencia con **`max_branches` mayor que cero**, la creación o reactivación de sucursales activas no puede superar ese límite. El valor **`0`** o la ausencia de fila de licencia se interpretan como **ilimitado** en esta versión.
- Cada creación, edición, desactivación, activación y eliminación registra un evento de auditoría con el usuario, la fecha, la IP y los valores anteriores/nuevos filtrados. La auditoría forma parte de la misma transacción de la operación: si falla, la operación se revierte por completo.
- Los ajustes JSON no deben contener contraseñas, PIN, tokens, claves ni datos personales innecesarios.
- Esta pantalla es de administración global y no utiliza el contexto de sucursal.

## 8. Resultados esperados

- **Crear:** la sucursal aparece en el listado con el código en mayúsculas y el mensaje «Sucursal creada correctamente».
- **Editar:** los datos actualizados se reflejan en el listado, se conserva el código original y el estado no cambia.
- **Desactivar:** la sucursal queda como **Inactiva**, conserva sus datos y deja de aceptar operaciones.
- **Activar:** la sucursal vuelve a estar **Activa** si el plan lo permite.
- **Eliminar:** la sucursal desaparece del listado, la fila se conserva con fecha de eliminación lógica y el código sigue reservado.
- **Sin permiso:** el servidor responde **403** y no ejecuta la operación.

## 9. Errores frecuentes y soluciones

| Error | Qué significa | Qué hacer |
|-------|---------------|-----------|
| «El código de sucursal es obligatorio.» | No se ingresó el código. | Complete el campo Código. |
| «El código solo puede contener letras mayúsculas, números y guiones.» | El código tiene espacios, minúsculas no normalizables o símbolos no permitidos. | Use un código como `SUC-A` o `BODEGA-2`. |
| «Ya existe una sucursal con ese código.» | El código pertenece a una sucursal existente, incluida una eliminada lógicamente. | Elija otro código. El código de una sucursal eliminada no se reutiliza. |
| «El código de sucursal no se puede modificar una vez creada la sucursal.» | Intentó cambiar el código en edición. | Deje el código original; el campo es de solo lectura. |
| «El tipo de sucursal debe ser local, bodega u online.» | Se envió un tipo no permitido. | Seleccione Local, Bodega u Online. |
| «Los ajustes deben ser un objeto JSON válido...» | La configuración adicional no es un objeto JSON, es una lista o es un valor suelto. | Escriba un objeto como `{"clave": "valor"}` o deje el campo vacío. |
| «Los ajustes no pueden contener contraseñas, tokens ni secretos.» | Se intentó guardar una credencial en la configuración. | Elimine esas claves; use la configuración del sistema para credenciales. |
| «El plan permite un máximo de N sucursales activas.» | Se alcanzó el límite de sucursales activas del plan. | Desactive otra sucursal o consulte al administrador de la licencia. |
| «No tiene permiso para realizar esta acción.» o error 403 | Su usuario no tiene `settings.manage`. | Solicite al administrador el permiso o el rol Admin Central. |
| La sucursal no responde en operaciones | La sucursal está desactivada. | Actívela desde esta pantalla si corresponde. |

## 10. Ejemplos prácticos

- **Crear un local:** el Admin Central crea la sucursal `SUC-A` con nombre «Local Centro», tipo **Local**, comuna «Santiago» y control de caja activado. Al guardar, aparece en el listado y queda disponible para las operaciones del local.
- **Crear una bodega:** crea `BODEGA-1`, tipo **Bodega**, con dirección ficticia «Calle Ficticia 123». Deja los ajustes heredables en **Heredar** para usar la configuración general.
- **Desactivar temporalmente:** la sucursal `SUC-B` cierra por mantenimiento. Se usa **Desactivar**; la sucursal queda inactiva, conserva sus datos y las operaciones que requieren sucursal responden 403. Al reabrir, se usa **Activar**.
- **Límite del plan:** si el plan permite dos sucursales activas y ya existen dos, el intento de crear una tercera muestra el error «El plan permite un máximo de 2 sucursales activas». Se debe desactivar una sucursal o ampliar la licencia.
- **Código reservado:** se elimina lógicamente `SUC-C`. Un intento posterior de crear otra sucursal con el código `SUC-C` falla con el error de código duplicado; se debe elegir otro código.

## Enlaces relacionados

- [Inicio y cierre de sesión](../../general/acceso.md)
- [Cambio de contraseña](../../general/cambio-contrasena.md)
- [Índice del manual](../../README.md)
