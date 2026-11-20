<?php

namespace App\Support\Customers;

use RuntimeException;

/**
 * La clave del índice ciego no está configurada o es demasiado corta.
 *
 * Se lanza solo cuando una operación realmente requiere RUT; un cliente sin
 * RUT puede crearse aunque BLIND_INDEX_KEY no exista.
 */
final class PrivacyKeyException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
