<?php

namespace App\Support\License;

/**
 * Fotografía sanitizada del estado de licencia local.
 *
 * Solo contiene campos aptos para UI. No transporta `installation_key`,
 * `signed_payload`, `signature`, límites confidenciales ni datos personales.
 */
final class LicenseSnapshot
{
    public function __construct(
        public readonly bool $configured,
        public readonly ?string $status,
        public readonly bool $readOnly,
        public readonly bool $inGrace,
        public readonly ?string $expiresAt,
        public readonly ?string $graceUntil,
    ) {}
}
