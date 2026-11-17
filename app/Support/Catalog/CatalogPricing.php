<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * Cálculos de precio neto, margen y markup del catálogo (RN-25 / CAT-04).
 *
 * `prices_include_tax` (CFG-003) define si `product_variants.price` incluye
 * impuesto. La tasa aplicable se resuelve variante → producto → tasa
 * predeterminada según `tax_rates.is_default`. Una tasa exenta no descuenta
 * impuesto del precio. Este soporte no emite documentos fiscales ni guarda
 * márgenes: solo deriva valores para presentación y para la calculadora.
 */
final class CatalogPricing
{
    public const DEFAULT_PRICES_INCLUDE_TAX = true;

    public function pricesIncludeTax(): bool
    {
        $raw = DB::table('system_configs')
            ->where('config_key', 'prices_include_tax')
            ->value('config_value');

        if ($raw === null) {
            return self::DEFAULT_PRICES_INCLUDE_TAX;
        }

        $parsed = filter_var((string) $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? self::DEFAULT_PRICES_INCLUDE_TAX;
    }

    /**
     * Resuelve la tasa efectiva sin consultar la base: recibe el mapa ya
     * cargado para evitar N+1. La clave de retorno `id` es la tasa usada.
     *
     * @param  array<int|string, array{rate?: mixed, is_exempt?: mixed, is_default?: mixed}>  $taxRates
     * @return array{id: int|null, rate: float, is_exempt: bool, source: string}
     */
    public function resolveTax(?int $variantTaxRateId, ?int $productTaxRateId, array $taxRates): array
    {
        $candidate = $variantTaxRateId ?? $productTaxRateId;
        $source = $variantTaxRateId !== null ? 'variant' : ($productTaxRateId !== null ? 'product' : 'none');

        $tax = $candidate !== null ? ($taxRates[$candidate] ?? null) : null;

        if ($tax === null) {
            $source = 'default';

            foreach ($taxRates as $id => $row) {
                if ((bool) ($row['is_default'] ?? false)) {
                    $tax = $row;
                    $candidate = (int) $id;

                    break;
                }
            }
        }

        if ($tax === null) {
            return ['id' => null, 'rate' => 0.0, 'is_exempt' => false, 'source' => 'none'];
        }

        $isExempt = (bool) ($tax['is_exempt'] ?? false);
        $rate = (float) ($tax['rate'] ?? 0);
        $rate = is_finite($rate) ? max(0.0, min(100.0, $rate)) : 0.0;

        return [
            'id' => $candidate,
            'rate' => $isExempt ? 0.0 : $rate,
            'is_exempt' => $isExempt,
            'source' => $source,
        ];
    }

    /**
     * Convierte el precio guardado a precio neto.
     *
     * @param  array{rate: float, is_exempt: bool}  $tax
     */
    public function netPrice(string|int|float $price, array $tax, bool $pricesIncludeTax): float
    {
        $amount = $this->finiteAmount($price);

        if (! $pricesIncludeTax || (bool) ($tax['is_exempt'] ?? false)) {
            return round($amount, 4);
        }

        $rate = (float) ($tax['rate'] ?? 0);
        $factor = 1 + ($rate / 100);

        if (! is_finite($factor) || $factor <= 0) {
            return round($amount, 4);
        }

        return round($amount / $factor, 4);
    }

    /**
     * Convierte un precio neto a precio guardado. Se usa como referencia
     * matemática de la calculadora CAT-04; no se persiste automáticamente.
     *
     * @param  array{rate: float, is_exempt: bool}  $tax
     */
    public function priceFromNet(float $netPrice, array $tax, bool $pricesIncludeTax): float
    {
        $net = is_finite($netPrice) ? max(0.0, $netPrice) : 0.0;

        if (! $pricesIncludeTax || (bool) ($tax['is_exempt'] ?? false)) {
            return round($net, 2);
        }

        $rate = (float) ($tax['rate'] ?? 0);
        $factor = 1 + ($rate / 100);

        if (! is_finite($factor) || $factor <= 0) {
            return round($net, 2);
        }

        return round($net * $factor, 2);
    }

    /**
     * @param  array{rate: float, is_exempt: bool}  $tax
     * @return array{net_price: float, margin: float, margin_percent: float|null, markup_percent: float|null}
     */
    public function metrics(string|int|float $price, string|int|float $cost, array $tax, bool $pricesIncludeTax): array
    {
        $net = $this->netPrice($price, $tax, $pricesIncludeTax);
        $costValue = $this->finiteAmount($cost);

        $margin = round($net - $costValue, 4);
        $marginPercent = abs($net) > 0 ? round((($net - $costValue) / $net) * 100, 2) : null;
        $markupPercent = $costValue > 0 ? round((($net - $costValue) / $costValue) * 100, 2) : null;

        return [
            'net_price' => $net,
            'margin' => $margin,
            'margin_percent' => is_finite((float) $marginPercent) ? $marginPercent : null,
            'markup_percent' => is_finite((float) $markupPercent) ? $markupPercent : null,
        ];
    }

    private function finiteAmount(string|int|float $value): float
    {
        $amount = (float) $value;

        return is_finite($amount) ? max(0.0, $amount) : 0.0;
    }
}
