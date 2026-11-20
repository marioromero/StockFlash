<?php

namespace App\Support\Customers;

use RuntimeException;

/**
 * RUT inválido o no normalizable (RN-58).
 *
 * El mensaje es apto para usuarios y nunca contiene el valor ingresado.
 */
final class RutException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
