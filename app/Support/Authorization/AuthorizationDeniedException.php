<?php

namespace App\Support\Authorization;

use RuntimeException;

/**
 * Rechazo de una autorización mediante PIN (CORE-11 / SEC-12).
 *
 * Convención única del servicio: toda negativa (política, formato de PIN,
 * PIN incorrecto, bloqueo o contexto inválido) se informa con esta excepción.
 * `reason` es un código estable y no sensible; el mensaje está en es-CL y
 * NUNCA incluye el PIN, su hash ni la cantidad de intentos restantes.
 */
final class AuthorizationDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
