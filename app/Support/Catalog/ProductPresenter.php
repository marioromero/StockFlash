<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\TaxRate;

/**
 * Serialización explícita de productos para Inertia.
 *
 * `cost_price`, `last_cost`, precio neto, margen y markup solo se incluyen
 * cuando el actor tiene `catalog.view_cost`. Sin ese permiso las claves ni
 * siquiera existen en las props entregadas al cliente.
 */
final class ProductPresenter
{
    public function __construct(private readonly CatalogPricing $pricing) {}

    /**
     * @return array<string, mixed>
     */
    public function listRow(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'internal_code' => $product->internal_code,
            'type' => $product->type,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
            'is_active' => (bool) $product->is_active,
            'is_sellable' => (bool) $product->is_sellable,
            'tracks_stock' => (bool) $product->tracks_stock,
            'origin' => $product->origin,
            'variant_count' => (int) ($product->variants_count ?? $product->variants->count()),
            'skus' => $product->variants->pluck('sku')->values()->all(),
            'tags' => $product->tags->map(fn ($tag): array => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Product $product, bool $canViewCost): array
    {
        $taxRates = $canViewCost ? $this->activeTaxRates() : [];
        $pricesIncludeTax = $canViewCost && $this->pricing->pricesIncludeTax();

        return [
            'id' => (int) $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'type' => $product->type,
            'name' => $product->name,
            'description' => $product->description,
            'internal_code' => $product->internal_code,
            'image_url' => $product->image_url,
            'tax_rate_id' => $product->tax_rate_id,
            'tracks_stock' => (bool) $product->tracks_stock,
            'is_sellable' => (bool) $product->is_sellable,
            'is_active' => (bool) $product->is_active,
            'origin' => $product->origin,
            'tag_ids' => $product->tags->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'tags' => $product->tags->map(fn ($tag): array => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
            ])->values()->all(),
            'variants' => $product->variants
                ->sortBy('id')
                ->map(fn (ProductVariant $variant): array => $this->variant(
                    $variant,
                    $canViewCost,
                    $product,
                    $taxRates,
                    $pricesIncludeTax,
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int|string, array{rate?: mixed, is_exempt?: mixed, is_default?: mixed}>  $taxRates
     * @return array<string, mixed>
     */
    public function variant(
        ProductVariant $variant,
        bool $canViewCost,
        ?Product $product = null,
        array $taxRates = [],
        bool $pricesIncludeTax = false,
    ): array {
        $base = [
            'id' => (int) $variant->id,
            'sku' => $variant->sku,
            'price' => (string) $variant->price,
            'unit' => $variant->unit,
            'sold_by_weight' => (bool) $variant->sold_by_weight,
            'max_discount_percent' => $variant->max_discount_percent === null
                ? null
                : (string) $variant->max_discount_percent,
            'min_price' => $variant->min_price === null ? null : (string) $variant->min_price,
            'allow_price_override' => (bool) $variant->allow_price_override,
            'tax_rate_id' => $variant->tax_rate_id,
            'combo_deducts_components' => (bool) $variant->combo_deducts_components,
            'is_active' => (bool) $variant->is_active,
            'attribute_value_ids' => $variant->attributeValues
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'attribute_values' => $variant->attributeValues
                ->map(fn ($value): array => [
                    'id' => (int) $value->id,
                    'attribute_id' => (int) $value->attribute_id,
                    'attribute_name' => $value->attribute?->name,
                    'value' => $value->value,
                ])
                ->values()
                ->all(),
            'barcodes' => $variant->barcodes
                ->sortBy([
                    ['is_primary', 'desc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->map(fn (ProductBarcode $barcode): array => $this->barcode($barcode))
                ->all(),
        ];

        if (! $canViewCost) {
            return $base;
        }

        $productTaxRateId = $product?->tax_rate_id;
        $productTaxRateId = $productTaxRateId === null ? null : (int) $productTaxRateId;
        $tax = $this->pricing->resolveTax($variant->tax_rate_id, $productTaxRateId, $taxRates);
        $metrics = $this->pricing->metrics(
            $variant->price,
            $variant->cost_price,
            $tax,
            $pricesIncludeTax,
        );

        return $base + [
            'cost_price' => (string) $variant->cost_price,
            'last_cost' => $variant->last_cost === null ? null : (string) $variant->last_cost,
            'net_price' => number_format($metrics['net_price'], 4, '.', ''),
            'margin' => number_format($metrics['margin'], 2, '.', ''),
            'margin_percent' => $metrics['margin_percent'] === null
                ? null
                : number_format($metrics['margin_percent'], 2, '.', ''),
            'markup_percent' => $metrics['markup_percent'] === null
                ? null
                : number_format($metrics['markup_percent'], 2, '.', ''),
            'effective_tax_rate' => number_format($tax['rate'], 2, '.', ''),
            'tax_is_exempt' => $tax['is_exempt'],
            'tax_source' => $tax['source'],
        ];
    }

    /**
     * @return array<int, array{rate: string, is_exempt: bool, is_default: bool}>
     */
    private function activeTaxRates(): array
    {
        return TaxRate::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'rate', 'is_exempt', 'is_default'])
            ->mapWithKeys(fn (TaxRate $taxRate): array => [
                (int) $taxRate->id => [
                    'rate' => (string) $taxRate->rate,
                    'is_exempt' => (bool) $taxRate->is_exempt,
                    'is_default' => (bool) $taxRate->is_default,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function barcode(ProductBarcode $barcode): array
    {
        return [
            'id' => (int) $barcode->id,
            'barcode' => $barcode->barcode,
            'type' => $barcode->type,
            'is_primary' => (bool) $barcode->is_primary,
        ];
    }
}
