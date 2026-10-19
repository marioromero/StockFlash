# Inicio y cierre de sesión (StockFlash)

> **Estado de la funcionalidad:** planificada en el Feature Packet `AUTH-001` (estado `DRAFT`, pendiente de aprobación del propietario). Esta guía describe el comportamiento previsto una vez implementada; no refleja una función ya desplegada ni disponible en producción.

Este manual explica cómo acceder y salir del sistema StockFlash.

## Nombre de la funcionalidad

Acceso al sistema (login / logout).

## ¿Para qué sirve?

Permite que un usuario autorizado entre al sistema con su correo y contraseña, y salga de forma segura cuando termina su jornada. El acceso controlado protege los datos del comercio y asegura que cada acción quede asociada a quien la realiza.

## ¿Quién puede utilizarla?

Cualquier usuario con una cuenta activa en StockFlash. Para crear la primera cuenta de administrador en un entorno nuevo se requiere un paso inicial controlado (sin contraseñas predefinidas); consulte con el administrador de la instalación.

## ¿Cómo acceder?

1. Abra el navegador y diríjase a la dirección del sistema indicada por el administrador (por ejemplo, `http://127.0.0.1:8000/` en entorno local).
2. La página de bienvenida muestra el formulario de ingreso.
3. Ingrese su **correo electrónico** y su **contraseña**.
4. Haga clic en el botón **Ingresar** (o equivalente).
5. Si las credenciales son correctas, el sistema lo lleva al **dashboard** (página principal protegida).
6. Para salir, haga clic en su nombre de usuario o en el menú superior y seleccione **Cerrar sesión**.

## ¿Cómo utilizarla?

### Iniciar sesión

- El correo y la contraseña deben coincidir con los registrados en su cuenta.
- La contraseña no se muestra al escribirla.
- Si olvida la contraseña, pida al administrador que la restablezca manualmente; el sistema no envía correos de recuperación en esta versión.

### Cerrar sesión

- Siempre cierre sesión al terminar su jornada, especialmente si usa una estación compartida.
- Al cerrar, la sesión queda invalidada y nadie puede seguir operando con su cuenta sin volver a autenticarse.

## Opciones y configuraciones disponibles

No hay opciones de usuario en el formulario de acceso. El administrador del sistema gestiona los roles, permisos y sucursales desde la administración.

## Reglas y limitaciones

- Tras **5 intentos fallidos** de ingreso, la cuenta se bloquea temporalmente por seguridad. Debe esperar o contactar al administrador.
- La sesión expira por inactividad (30 minutos en punto de venta, 60 minutos en administración) y tiene una duración máxima de 12 horas.
- Al cambiar su contraseña, la sesión actual se invalida y debe volver a iniciar sesión.
- Un usuario sin asignación de sucursal no tiene acceso a operar en sucursal alguna (403). El administrador global (`global_role_id` = `central_admin`) puede autenticarse y entrar al dashboard incluso si no existe sucursal creada, pero no puede operar en sucursal inexistente.

## Resultados esperados

- Ingreso exitoso: el sistema muestra el dashboard y el nombre del usuario autenticado.
- Credenciales incorrectas: el sistema muestra un mensaje de error y no permite el ingreso.
- Cierre de sesión: el sistema regresa al formulario de ingreso y la sesión queda invalidada.

## Errores frecuentes y soluciones

| Error | Qué significa | Qué hacer |
|-------|---------------|-----------|
| "Credenciales incorrectas" | El correo o la contraseña no coinciden. | Revise la escritura (mayúsculas/minúsculas). Si no recuerda la contraseña, pida al administrador que la restablezca. |
| "Cuenta bloqueada temporalmente" | Superó los 5 intentos fallidos. | Espere unos minutos e intente de nuevo, o contacte al administrador. |
| "No tiene acceso a ninguna sucursal" | Su usuario no está asignado a una sucursal. | Contacte al administrador para asignarle una sucursal o rol global. |

## Ejemplos prácticos

- Ejemplo: María es cajera. Ingresa su correo `maria@comercio.cl` y su contraseña, hace clic en Ingresar y llega al dashboard de ventas. Al terminar, abre el menú superior y cierra sesión para que nadie más use su caja.
- Ejemplo: Juan intenta ingresar 5 veces con una contraseña equivocada. En el sexto intento el sistema le indica que la cuenta está bloqueada temporalmente. Espera el tiempo indicado y vuelve a intentarlo con la contraseña correcta.
