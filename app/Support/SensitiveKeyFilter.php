<?php

namespace App\Support;

/**
 * Detección y filtrado de claves sensibles en estructuras asociativas.
 *
 * Se usa en la validación de `settings` (BranchRequest) y en el registro de
 * auditoría (AuditLogger) para impedir que credenciales o secretos lleguen a
 * `branches.settings` o a `audit_logs`.
 *
 * La comparación es por token completo, nunca por subcadena, para no rechazar
 * claves legítimas como `shipping_cost` o `mapping` (que contienen `pin`).
 * Los límites camelCase se separan antes de comparar, de modo que `authToken`,
 * `clientSecret`, `apiKey` o `creditCard` también se detectan.
 */
class SensitiveKeyFilter
{
    /** Tokens individuales que, por sí solos, indican una clave sensible. */
    private const SENSITIVE_TOKENS = [
        'password',
        'passwd',
        'pin',
        'secret',
        'token',
        'authorization',
        'cookie',
    ];

    /**
     * Términos compuestos que solo son sensibles al formar una palabra
     * completa: dos tokens adyacentes (`api` + `key`) o un único token
     * (`apikey`, `privatekey`, `creditcard`).
     */
    private const SENSITIVE_COMPOUNDS = [
        'apikey',
        'privatekey',
        'creditcard',
    ];

    /**
     * Indica si algún valor de la estructura (incluidos los anidados) tiene
     * una clave sensible.
     *
     * @param  array<string|int, mixed>  $values
     */
    public static function contains(array $values): bool
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                return true;
            }

            if (is_array($value) && self::contains($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve una copia de la estructura sin las claves sensibles,
     * aplicándose recursivamente a los valores anidados.
     *
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    public static function filter(array $values): array
    {
        $filtered = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                continue;
            }

            $filtered[$key] = is_array($value)
                ? self::filter($value)
                : $value;
        }

        return $filtered;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $tokens = self::tokens($key);

        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (in_array($token, self::SENSITIVE_TOKENS, true)) {
                return true;
            }
        }

        // `apiKey` / `api_key` → `api` + `key`; `privateKey`, `creditCard`.
        for ($index = 0; $index < count($tokens) - 1; $index++) {
            $pair = $tokens[$index].$tokens[$index + 1];

            if (in_array($pair, self::SENSITIVE_COMPOUNDS, true)) {
                return true;
            }
        }

        // Claves ya concatenadas sin separadores: `apikey`, `privatekey`, etc.
        $joined = implode('', $tokens);

        return in_array($joined, self::SENSITIVE_COMPOUNDS, true);
    }

    /**
     * Separa camelCase y cualquier carácter no alfanumérico en tokens
     * minúsculos. `secretToken` → [secret, token]; `api-key` → [api, key].
     *
     * @return list<string>
     */
    private static function tokens(string $key): array
    {
        $withBoundaries = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key) ?? $key;
        $withBoundaries = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $withBoundaries) ?? $withBoundaries;

        $normalized = strtolower($withBoundaries);

        return preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
