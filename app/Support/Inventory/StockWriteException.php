<?php

namespace App\Support\Inventory;

use RuntimeException;

/**
 * Error de validación de escritura manual de inventario (INVWRITE-001).
 *
 * `field` permite mapear el rechazo a un error es-CL del formulario sin
 * exponer detalles internos, costos ni el valor del umbral de autorización.
 */
final class StockWriteException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
