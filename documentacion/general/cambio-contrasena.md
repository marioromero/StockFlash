# Cambio de contraseña (StockFlash)

Este manual explica cómo cambiar la contraseña de su propia cuenta.

## Nombre de la funcionalidad

Cambio de contraseña del usuario autenticado.

## ¿Para qué sirve?

Permite que cualquier usuario autenticado reemplace su contraseña actual por una nueva, por ejemplo por seguridad periódica o porque sospecha que alguien más la conoce.

## ¿Quién puede utilizarla?

Cualquier usuario con una sesión iniciada en StockFlash.

## ¿Cómo acceder?

1. Inicie sesión en el sistema (consulte `general/acceso.md`).
2. Abra el menú de usuario (su nombre en la parte superior de la pantalla).
3. Seleccione **Cambiar contraseña** (o equivalente).
4. Se muestra un formulario con tres campos: contraseña actual, contraseña nueva y confirmación de contraseña nueva.

## ¿Cómo utilizarla?

1. Ingrese su **contraseña actual**.
2. Ingrese la **contraseña nueva**. Debe tener **al menos 15 caracteres** y no estar en la lista de contraseñas comunes o comprometidas.
3. Repita la contraseña nueva en el campo de confirmación.
4. Haga clic en **Guardar cambios** (o equivalente).
5. Si todo es correcto, el sistema guarda la nueva contraseña, cierra la sesión actual por seguridad y le pide iniciar sesión nuevamente con la nueva contraseña.

## Opciones y configuraciones disponibles

El administrador del sistema puede restablecer manualmente la contraseña de cualquier usuario si este olvida la suya. Ese restablecimiento no revela la contraseña anterior.

## Reglas y limitaciones

- La contraseña nueva debe tener **mínimo 15 caracteres**.
- El sistema admite hasta **64 caracteres** como máximo.
- No se permiten reglas artificiales de composición (por ejemplo, "debe tener una mayúscula y un número") como requisito obligatorio; la longitud y la lista de contraseñas bloqueadas son los controles principales.
- Las contraseñas comunes o comprometidas están bloqueadas.
- Después de cambiar la contraseña, la sesión actual se invalida y debe iniciar sesión de nuevo.
- El sistema no almacena ni muestra la contraseña en texto plano, ni la escribe en registros (logs).

## Resultados esperados

- Éxito: contraseña guardada, sesión cerrada, redirección al formulario de ingreso para iniciar sesión con la nueva contraseña.
- Error: si la contraseña actual es incorrecta o la nueva no cumple las reglas, el sistema muestra un mensaje indicando el problema y no guarda cambios.

## Errores frecuentes y soluciones

| Error | Qué significa | Qué hacer |
|-------|---------------|-----------|
| "La contraseña actual es incorrecta" | No coincidió con la registrada. | Revise la escritura o solicite al administrador un restablecimiento manual. |
| "La contraseña es demasiado corta" | Tiene menos de 15 caracteres. | Use una contraseña más larga. |
| "Contraseña bloqueada (común o comprometida)" | Esa contraseña aparece en listas de uso frecuente o filtraciones conocidas. | Elija otra contraseña distinta y no obvia. |
| "Las contraseñas nuevas no coinciden" | Los campos de contraseña nueva y confirmación difieren. | Vuelva a escribirlas iguales en ambos campos. |

## Ejemplos prácticos

- Ejemplo: Ana quiere cambiar su contraseña por seguridad. Accede al menú de usuario, elige Cambiar contraseña, escribe la actual, luego una nueva de 16 caracteres y la confirma. El sistema guarda el cambio, cierra su sesión y ella vuelve a entrar con la nueva contraseña.
