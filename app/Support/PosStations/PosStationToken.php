<?php

namespace App\Support\PosStations;

/**
 * Identidad de dispositivo POS (POS-02).
 *
 * Genera 32 bytes criptográficamente seguros (>= 256 bits) expresados como
 * 64 caracteres hexadecimales. Solo se persiste `hash('sha256', $token)`.
 * El token en claro nunca se guarda, registra ni serializa.
 */
final class PosStationToken
{
    public const RAW_LENGTH = 64;

    public const HASH_LENGTH = 64;

    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function isValidRawToken(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }
}
