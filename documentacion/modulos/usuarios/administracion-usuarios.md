# Administración de usuarios (StockFlash)

> **Estado de la funcionalidad:** implementada en el Feature Packet `USERS-001` (estado `APPROVED`, aprobado por el propietario el 2026-11-13). La funcionalidad está disponible en el entorno local de desarrollo; su publicación y verificación externa se registran por separado.

Este manual explica cómo listar, crear, editar, activar, desactivar y eliminar lógicamente usuarios, además de asignar roles por sucursal, configurar un PIN de autorización y definir topes propios de descuento y devolución.

## 1. Nombre de la funcionalidad

Administración de usuarios, roles y asignación a sucursales (CRUD de usuarios).

## 2. ¿Para qué sirve?

Permite mantener las cuentas que usan StockFlash y definir qué puede hacer cada una. Un usuario puede tener un **rol global** (aplica en todas las sucursales) o uno o más **roles por sucursal**. También permite marcar una **sucursal inicial**, restablecer la contraseña temporal, configurar un PIN de autorización y fijar topes propios de descuento y devolución.

## 3. ¿Quién puede utilizarla?

Solo usuarios con el permiso **`users.manage`**, que en la configuración vigente corresponde al rol **Admin Central**. La autorización se valida en el servidor en cada operación: aunque un enlace no se muestre en pantalla, el servidor responde **403** a quien no tenga el permiso. La administración de usuarios es global y no utiliza el control de acceso por sucursal.

## 4. ¿Cómo acceder?

1. Inicie sesión en StockFlash. Si necesita ayuda, consulte [`general/acceso.md`](../../general/acceso.md).
2. En el **dashboard**, busque el enlace **Usuarios**. El enlace solo aparece si su usuario tiene `users.manage`.
3. También puede acceder directamente a la ruta `/users` desde el navegador, siempre que su sesión y sus permisos lo autoricen.
4. Para volver al dashboard use el botón **Volver al dashboard**.

## 5. ¿Cómo utilizarla?

### Listar y buscar usuarios

- La pantalla principal muestra **nombre**, **correo**, **tipo de acceso** (Global o Por sucursal), **rol principal**, **sucursales asignadas**, **estado** (Activo/Inactivo) y si el **PIN está configurado**.
- La búsqueda por nombre o correo se ejecuta en el servidor y conserva la paginación.
- Los usuarios eliminados lógicamente no aparecen en el listado.
- Un usuario por sucursal sin asignaciones se muestra con la advertencia **Sin asignaciones**: puede existir, pero no tendrá acceso operativo.

### Crear un usuario

1. Haga clic en **Nuevo usuario**.
2. Complete **Nombre** y **Correo electrónico**. El correo se guarda en minúsculas y debe ser único.
3. Informe una **contraseña temporal** de 15 a 64 caracteres. El usuario deberá cambiarla en su primer ingreso.
4. Opcionalmente configure un **PIN** con la cantidad exacta de dígitos definida por el sistema.
5. Elija el **tipo de acceso**:
   - **Rol global:** seleccione un rol global. El usuario no lleva asignaciones por sucursal.
   - **Usuario por sucursal:** deje el rol global vacío y agregue una o más filas de **Sucursal + Rol**.
6. Opcionalmente seleccione una **sucursal inicial** activa. Para un usuario por sucursal debe estar entre las sucursales asignadas.
7. Opcionalmente defina los **topes propios** de descuento (%) y devolución.
8. Haga clic en **Crear usuario**. Si todo es válido, el sistema vuelve al listado y muestra un mensaje de éxito.

### Editar un usuario

1. Haga clic en **Editar** en la fila del usuario.
2. Modifique los datos permitidos, el tipo de acceso, las asignaciones o los topes.
3. Deje la contraseña vacía para conservarla. Si informa una nueva, el usuario deberá cambiarla y sus sesiones vigentes se invalidarán.
4. El **estado activo** se muestra solo como referencia: se cambia con las acciones **Activar** o **Desactivar** del listado.
5. Haga clic en **Guardar cambios**.

### Desactivar y activar

1. Use **Desactivar** para impedir el acceso. El usuario sigue existiendo y conserva su historia.
2. Use **Activar** para habilitarlo nuevamente. El sistema verifica el límite de usuarios activos de la licencia.
3. No es posible desactivar ni activar la propia cuenta desde esta pantalla cuando la acción está bloqueada por seguridad.

### Eliminar lógicamente

1. Haga clic en **Eliminar** y confirme la advertencia.
2. La cuenta queda **inactiva**, se aplica un borrado lógico, desaparece del listado y su correo queda reservado para siempre.
3. No existe restauración de usuarios eliminados en esta versión.

### Configurar o quitar el PIN

1. En el formulario, escriba el nuevo PIN si desea configurarlo.
2. Para quitarlo en un usuario que ya lo tiene, marque **Quitar el PIN configurado**.
3. El PIN se guarda únicamente como hash y nunca se muestra. La pantalla solo indica **PIN configurado** o **PIN no configurado**.

## 6. Opciones y configuraciones disponibles

| Campo | Obligatorio | Descripción |
|-------|-------------|-------------|
| Nombre | Sí | Nombre visible del usuario, máximo 150 caracteres. |
| Correo electrónico | Sí | Correo válido, máximo 150 caracteres, normalizado a minúsculas y único sin distinguir mayúsculas/minúsculas, incluidos los usuarios eliminados. |
| Contraseña | Sí al crear | 15 a 64 caracteres, sin reglas artificiales de composición. En edición es opcional. |
| PIN | No | Exactamente la cantidad de dígitos configurada por el sistema (por defecto 4). Solo se guarda el hash. |
| Rol global | No | Rol de sistema que aplica en todas las sucursales. Si se informa, el usuario no lleva asignaciones. `central_admin` solo puede ser global. |
| Sucursal + Rol por sucursal | No | Filas únicas por sucursal. Los roles por sucursal son Vendedor, Cajero, Bodeguero y Supervisor. |
| Sucursal inicial | No | Debe existir y estar activa. Para un usuario por sucursal, debe estar entre sus asignaciones. |
| Descuento máximo (%) | No | Nulo o un valor entre 0 y 100. Nulo hereda el tope del rol (RN-02). |
| Monto máximo de devolución | No | Nulo o un valor mayor o igual a 0. Nulo hereda el tope del rol (RN-02). |
| Estado activo | No editable aquí | Solo cambia con las acciones **Activar** o **Desactivar**. |

## 7. Reglas y limitaciones

- **Acceso global XOR por sucursal:** un usuario no puede tener rol global y asignaciones de sucursal a la vez. Al convertirlo en global se eliminan sus asignaciones; al pasarlo a por sucursal se limpia el rol global.
- **`central_admin` solo global:** no puede asignarse como rol por sucursal.
- **Cero asignaciones:** un usuario por sucursal puede guardarse sin asignaciones, pero se advierte que no tendrá acceso operativo.
- **Sucursal inicial:** debe estar activa y, para un usuario por sucursal, estar asignada.
- **Contraseña temporal:** el administrador la define y la comunica por un canal seguro fuera del sistema. No se envía correo en esta versión. Al informar una contraseña, el usuario debe cambiarla y sus sesiones vigentes se invalidan en la siguiente petición.
- **PIN:** la longitud se lee desde la configuración del sistema (`system_configs.pin_length`, por defecto 4). No se muestra ni se recupera; el flujo de autorización con PIN pertenece a otra funcionalidad.
- **Estado y baja:** `is_active` solo cambia por las acciones dedicadas. No se puede desactivar ni eliminar la propia cuenta. No se puede desactivar, eliminar, cambiar de rol global ni reemplazar las asignaciones del último usuario activo con `users.manage`. El auto-cambio de rol solo se permite si otro usuario activo conserva `users.manage`.
- **Límite de la licencia:** al crear o activar se cuenta solo a los usuarios activos no eliminados y se bloquea la fila de licencia. Sin licencia registrada no se pueden crear ni activar usuarios. Con licencia registrada, `max_users = 0` significa ilimitado. Una licencia suspendida fuera de gracia deja el sistema en solo lectura para altas y activaciones.
- **Eliminación lógica:** la cuenta se conserva, se deja inactiva, deja de aparecer en las consultas y su correo queda reservado. No se restaura.
- **Auditoría:** alta, baja, cambios de rol/acceso, activación/desactivación y reseteos de credenciales se registran como eventos de seguridad. Los cambios de datos personales y las lecturas del listado o de la ficha se marcan como acceso a datos personales. Nunca se auditan contraseñas, PIN ni hashes. La auditoría forma parte de la misma transacción: si falla, la operación se revierte.
- **Lectura de datos personales:** cada consulta exitosa del listado o de la ficha registra un evento antes de entregar los datos, sin copiar nombres, correos ni listas de personas al registro.
- **Autorización:** todas las rutas exigen `users.manage` en el servidor. Una URL de usuario manipulada por alguien sin permiso responde **403**.

## 8. Resultados esperados

- **Crear:** el usuario aparece en el listado, queda activo, con cambio de contraseña obligatorio y evento `user.created`.
- **Editar:** los datos actualizados se reflejan en el listado. Si se cambió contraseña, rol, asignaciones, sucursal inicial o estado, las sesiones del afectado se invalidan en su siguiente petición.
- **Desactivar:** el usuario queda **Inactivo** y no puede iniciar sesión ni continuar su sesión vigente.
- **Activar:** el usuario vuelve a estar **Activo** si el plan lo permite.
- **Eliminar:** el usuario desaparece del listado, la fila se conserva con fecha de eliminación lógica y el correo sigue reservado.
- **PIN configurado:** la fila muestra **Configurado** sin revelar el valor.
- **Sin permiso:** el servidor responde **403** y no ejecuta la operación.

## 9. Errores frecuentes y soluciones

| Error | Qué significa | Qué hacer |
|-------|---------------|-----------|
| «El nombre del usuario es obligatorio.» | No se ingresó el nombre. | Complete el campo Nombre. |
| «El correo electrónico es obligatorio.» / «no es válido» | El correo está vacío o tiene formato incorrecto. | Ingrese un correo válido, por ejemplo `usuario@empresa.cl`. |
| «Ya existe un usuario con ese correo electrónico.» | El correo pertenece a otra cuenta, incluso eliminada lógicamente. | Use otro correo. El correo de una cuenta eliminada no se reutiliza. |
| «La contraseña es obligatoria al crear el usuario.» | Se intentó crear sin contraseña temporal. | Informe una contraseña de 15 a 64 caracteres. |
| «La contraseña debe tener al menos 15 caracteres.» | La contraseña es demasiado corta. | Use al menos 15 caracteres. |
| «El PIN debe tener exactamente N dígitos.» | La longitud del PIN no coincide con la configuración del sistema. | Ingrese exactamente la cantidad de dígitos indicada por el administrador del sistema. |
| «Un usuario global no puede tener asignaciones por sucursal...» | Se eligió rol global y además se agregaron sucursales. | Quite las asignaciones o deje el rol global vacío. |
| «La sucursal no existe o está inactiva.» | La sucursal elegida fue desactivada o eliminada. | Elija una sucursal activa. |
| «No se puede asignar la misma sucursal más de una vez.» | Hay dos filas con la misma sucursal. | Deje una sola fila por sucursal. |
| «La sucursal inicial debe estar entre las sucursales asignadas...» | La sucursal inicial no está asignada al usuario por sucursal. | Asígnela o elija otra sucursal inicial. |
| «El estado activo no se modifica desde la edición...» | Se intentó enviar `is_active` desde el formulario. | Use **Activar** o **Desactivar** en el listado. |
| «No es posible desactivar o eliminar al último usuario activo con permiso...» | La operación dejaría la instalación sin administrador activo. | Cree o active otro usuario con `users.manage` antes de continuar. |
| «No puede desactivar ni eliminar su propia cuenta.» | Se intentó una acción sobre la propia cuenta. | Otro administrador debe realizarla. |
| «El plan permite un máximo de N usuarios activos.» | Se alcanzó el límite de usuarios activos de la licencia. | Desactive otro usuario o amplíe la licencia. |
| «No hay una licencia registrada...» | La instalación no tiene licencia registrada. | Registre la licencia antes de crear o activar usuarios. |
| «La licencia está suspendida...» / «modo solo lectura» | La licencia está suspendida fuera de gracia. | Regularice la licencia; no se permiten altas ni activaciones. |
| Error 403 | Su usuario no tiene `users.manage`. | Solicite el permiso o el rol Admin Central. |

## 10. Ejemplos prácticos

- **Crear un administrador global:** el Admin Central crea `ana@empresa.cl`, define una contraseña temporal, selecciona el rol global **Admin Central**, deja las asignaciones vacías y guarda. Ana deberá cambiar la contraseña en su primer ingreso.
- **Crear un cajero de sucursal:** crea `carlos@empresa.cl`, deja el rol global vacío, agrega la sucursal `SUC-A` con rol **Cajero**, marca `SUC-A` como sucursal inicial y guarda. Carlos solo podrá operar en esa sucursal.
- **Cambiar un cajero a supervisor:** edita a Carlos, cambia el rol de `SUC-A` de **Cajero** a **Supervisor** y guarda. Sus sesiones vigentes se invalidan y deberá iniciar sesión nuevamente.
- **Usuario sin acceso operativo:** crea `bodega@empresa.cl` como usuario por sucursal sin asignaciones. El sistema lo guarda con una advertencia y el listado muestra **Sin asignaciones**.
- **Restablecer contraseña:** edita un usuario, informa una nueva contraseña temporal y guarda. El usuario deberá cambiarla en su próximo ingreso; su sesión anterior deja de ser válida.
- **Configurar PIN:** edita un usuario, escribe un PIN de exactamente la cantidad de dígitos configurada y guarda. La pantalla mostrará **PIN configurado** sin revelar el valor.
- **Eliminar una cuenta:** elimina lógicamente a un usuario que ya no trabaja en la empresa. La cuenta queda inactiva, no aparece en el listado y su correo no puede reutilizarse.

## Enlaces relacionados

- [Inicio y cierre de sesión](../../general/acceso.md)
- [Cambio de contraseña](../../general/cambio-contrasena.md)
- [Administración de sucursales](../sucursales/administracion-sucursales.md)
- [Índice del manual](../../README.md)
