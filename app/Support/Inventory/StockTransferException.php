<?php

namespace App\Support\Inventory;

use RuntimeException;

/**
 * Error de dominio del ciclo de traspasos entre sucursales (INVTRANSFER-001).
 *
 * `field` permite devolver un error es-CL asociado al formulario o a la acción
 * sin exponer detalles internos, costos ni datos de otras sucursales.
 */
final class StockTransferException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
