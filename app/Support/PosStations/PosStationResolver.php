<?php

namespace App\Support\PosStations;

use App\Models\PosStation;
use Illuminate\Http\Request;

/**
 * Resolución de solo lectura de la estación por cookie de dispositivo (POS-02).
 *
 * Lee el token en claro únicamente desde la cookie HttpOnly, calcula su
 * SHA-256 y busca una estación no eliminada, activa y con sucursal activa.
 * NO autentica ni autoriza usuarios, NO concede permisos, NO emite tokens y
 * NO actualiza `last_seen_at`. La ausencia o invalidez de la cookie devuelve
 * `null` de forma segura.
 */
final class PosStationResolver
{
    /**
     * Nombre interno de la cookie de dispositivo. No es un secreto por sí
     * mismo; solo el valor es sensible.
     */
    public const COOKIE_NAME = 'stockflash_pos_station';

    public function resolveFromRequest(Request $request, ?int $expectedBranchId = null): ?PosStation
    {
        $raw = $request->cookie(self::COOKIE_NAME);

        if (! is_string($raw) || ! PosStationToken::isValidRawToken($raw)) {
            return null;
        }

        return $this->resolveFromRawToken($raw, $expectedBranchId);
    }

    /**
     * Identifica la estación del token. Si el llamador conoce la sucursal del
     * contexto POS, puede exigirla: un token de otra sucursal devuelve `null`.
     */
    public function resolveFromRawToken(string $rawToken, ?int $expectedBranchId = null): ?PosStation
    {
        if (! PosStationToken::isValidRawToken($rawToken)) {
            return null;
        }

        $hash = PosStationToken::hash($rawToken);

        $query = PosStation::query()
            ->where('device_token_hash', $hash)
            ->where('is_active', 1)
            ->whereHas('branch', function ($branchQuery): void {
                $branchQuery->where('is_active', 1)->whereNull('deleted_at');
            });

        if ($expectedBranchId !== null) {
            $query->where('branch_id', $expectedBranchId);
        }

        return $query->first();
    }
}
