<?php

namespace App\Support\Customers;

use Illuminate\Support\Facades\Crypt;

/**
 * RUT chileno: normalización, módulo 11, cifrado y índice ciego (RN-58).
 *
 * - Normaliza a `cuerpo-DV` sin puntos, espacios, guiones ni ceros iniciales.
 * - Valida el dígito verificador con módulo 11 (incluye K).
 * - Cifra el RUT canónico con Laravel Crypt.
 * - Calcula `HMAC-SHA256(rut_normalizado, BLIND_INDEX_KEY)`. No usa hashes
 *   simples ni derivados de APP_KEY.
 * - Las máscaras son constantes y deterministas: no dependen del valor, por lo
 *   que no filtran longitud ni dígitos.
 *
 * Nunca registra ni expone el RUT, su cifrado, su hash o la clave.
 */
final class CustomerRut
{
    public const MASK_RUT = '••••••••-•';

    public const MASK_EMAIL = '•••@•••';

    public const MASK_PHONE = '••••••••';

    private const MIN_KEY_LENGTH = 32;

    /**
     * @throws RutException
     */
    public function normalize(string $raw): string
    {
        $value = trim($raw);
        $value = preg_replace('/[\.\s\-]+/u', '', $value) ?? '';
        $value = mb_strtoupper($value, 'UTF-8');

        if ($value === '' || strlen($value) < 2) {
            throw new RutException('rut', 'Ingrese un RUT válido.');
        }

        $dv = substr($value, -1);
        $body = substr($value, 0, -1);

        if (! preg_match('/^\d+$/', $body) || ! preg_match('/^[0-9K]$/', $dv)) {
            throw new RutException('rut', 'Ingrese un RUT válido.');
        }

        $body = ltrim($body, '0');

        if ($body === '') {
            throw new RutException('rut', 'Ingrese un RUT válido.');
        }

        if (! hash_equals($this->checkDigit($body), $dv)) {
            throw new RutException('rut', 'El RUT no es válido (dígito verificador incorrecto).');
        }

        return $body.'-'.$dv;
    }

    /**
     * @throws PrivacyKeyException
     */
    public function blindIndex(string $canonical): string
    {
        $key = config('privacy.blind_index_key');

        if (! is_string($key) || strlen($key) < self::MIN_KEY_LENGTH) {
            throw new PrivacyKeyException(
                'rut',
                'No se puede operar con RUT: falta configurar una BLIND_INDEX_KEY válida (mínimo 32 caracteres).',
            );
        }

        return hash_hmac('sha256', $canonical, $key);
    }

    public function encrypt(string $canonical): string
    {
        return Crypt::encryptString($canonical);
    }

    public function decrypt(?string $encrypted): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        return Crypt::decryptString($encrypted);
    }

    public function maskRut(bool $hasRut): ?string
    {
        return $hasRut ? self::MASK_RUT : null;
    }

    public function maskEmail(?string $email): ?string
    {
        return ($email === null || $email === '') ? null : self::MASK_EMAIL;
    }

    public function maskPhone(?string $phone): ?string
    {
        return ($phone === null || $phone === '') ? null : self::MASK_PHONE;
    }

    /**
     * Dígito verificador módulo 11 para un cuerpo numérico sin ceros iniciales.
     */
    public function checkDigit(string $body): string
    {
        $sum = 0;
        $multiplier = 2;

        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);

        if ($remainder === 11) {
            return '0';
        }

        if ($remainder === 10) {
            return 'K';
        }

        return (string) $remainder;
    }
}
