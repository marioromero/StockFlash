<?php

namespace App\Support\Inventory;

use RuntimeException;

/**
 * Error de dominio de configuración de reposición (INV-07).
 *
 * El campo identifica la entrada afectada para que el controlador responda
 * 422 es-CL sin filtrar detalles internos ni datos de otras sucursales.
 */
final class ReorderSettingsException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
