<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio transaccional del agregado producto + variantes + pivotes + códigos.
 *
 * Cada operación de múltiples filas se ejecuta en una transacción junto con su
 * auditoría; si la auditoría o una validación falla, todo se revierte. No crea
 * stock, inventario, ventas ni composición de combos.
 */
final class ProductCatalogService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CatalogWriteGuard $guard,
        private readonly CatalogUniqueViolation $uniqueViolation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data, bool $canViewCost): Product
    {
        try {
            return DB::transaction(function () use ($data, $canViewCost): Product {
                $this->guard->assertWritable();

                $variants = array_values($data['variants'] ?? []);
                $this->assertVariantCount((string) $data['type'], count($variants));

                $product = Product::create($this->productCreateAttributes($data));
                $this->syncTags($product, $data['tag_ids'] ?? []);

                $this->auditLogger->record(
                    'catalog.products.created',
                    'products',
                    (int) $product->id,
                    null,
                    null,
                    $this->productAuditValues($product),
                    'catalog',
                );

                foreach ($variants as $variantData) {
                    $variant = $this->createVariantRecord($product, $variantData, $canViewCost);

                    $this->auditLogger->record(
                        'catalog.product_variants.created',
                        'product_variants',
                        (int) $variant->id,
                        null,
                        null,
                        $this->variantAuditValues($variant, $canViewCost),
                        'catalog',
                    );
                }

                return $product->load(['category:id,name', 'brand:id,name', 'taxRate:id,code,name', 'tags:id,name', 'variants']);
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data, bool $canViewCost): Product
    {
        try {
            return DB::transaction(function () use ($product, $data, $canViewCost): Product {
                $this->guard->assertWritable();

                $locked = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->load('variants');
                $this->assertVariantCount((string) $data['type'], $locked->variants->count());

                $oldValues = $this->productAuditValues($locked);

                $locked->fill($this->productUpdateAttributes($data));
                $locked->save();

                if (array_key_exists('tag_ids', $data)) {
                    $this->syncTags($locked, $data['tag_ids'] ?? []);
                }

                $this->auditLogger->record(
                    'catalog.products.updated',
                    'products',
                    (int) $locked->id,
                    null,
                    $oldValues,
                    $this->productAuditValues($locked),
                    'catalog',
                );

                return $locked->load(['category:id,name', 'brand:id,name', 'taxRate:id,code,name', 'tags:id,name', 'variants']);
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    public function deleteProduct(Product $product): void
    {
        try {
            DB::transaction(function () use ($product): void {
                $this->guard->assertWritable();

                $locked = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $variants = ProductVariant::query()
                    ->where('product_id', $locked->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $oldValues = $this->productAuditValues($locked);
                $oldValues['variant_ids'] = $variants->pluck('id')->values()->all();

                foreach ($variants as $variant) {
                    $variant->delete();
                }

                $locked->delete();

                $this->auditLogger->record(
                    'catalog.products.deleted',
                    'products',
                    (int) $locked->id,
                    null,
                    $oldValues,
                    ['id' => (int) $locked->id, 'deleted_at' => optional($locked->deleted_at)->toDateTimeString()],
                    'catalog',
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVariant(Product $product, array $data, bool $canViewCost): ProductVariant
    {
        try {
            return DB::transaction(function () use ($product, $data, $canViewCost): ProductVariant {
                $this->guard->assertWritable();

                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $active = ProductVariant::withTrashed()
                    ->where('product_id', $lockedProduct->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->whereNull('deleted_at');

                $this->assertVariantAdditionAllowed((string) $lockedProduct->type, $active->count());

                $variant = $this->createVariantRecord($lockedProduct, $data, $canViewCost);

                $this->auditLogger->record(
                    'catalog.product_variants.created',
                    'product_variants',
                    (int) $variant->id,
                    null,
                    null,
                    $this->variantAuditValues($variant, $canViewCost),
                    'catalog',
                );

                return $variant;
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVariant(
        Product $product,
        ProductVariant $variant,
        array $data,
        bool $canViewCost,
    ): ProductVariant {
        try {
            return DB::transaction(function () use ($product, $variant, $data, $canViewCost): ProductVariant {
                $this->guard->assertWritable();

                $lockedVariant = $this->lockVariant($product, $variant);

                $oldValues = $this->variantAuditValues($lockedVariant, $canViewCost);

                $lockedVariant->fill($this->variantUpdateAttributes($data, $canViewCost));
                $lockedVariant->save();

                if (array_key_exists('attribute_value_ids', $data)) {
                    $this->syncAttributeValues($lockedVariant, $data['attribute_value_ids'] ?? []);
                }

                $this->auditLogger->record(
                    'catalog.product_variants.updated',
                    'product_variants',
                    (int) $lockedVariant->id,
                    null,
                    $oldValues,
                    $this->variantAuditValues($lockedVariant, $canViewCost),
                    'catalog',
                );

                return $lockedVariant;
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    public function deleteVariant(Product $product, ProductVariant $variant, bool $canViewCost): void
    {
        try {
            DB::transaction(function () use ($product, $variant, $canViewCost): void {
                $this->guard->assertWritable();

                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $active = ProductVariant::withTrashed()
                    ->where('product_id', $lockedProduct->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->whereNull('deleted_at');

                $target = $active->firstWhere('id', $variant->id);

                if ($target === null) {
                    abort(404);
                }

                $this->assertVariantCount((string) $lockedProduct->type, $active->count() - 1);

                $oldValues = $this->variantAuditValues($target, $canViewCost);

                $target->delete();

                $this->auditLogger->record(
                    'catalog.product_variants.deleted',
                    'product_variants',
                    (int) $target->id,
                    null,
                    $oldValues,
                    ['id' => (int) $target->id, 'deleted_at' => optional($target->deleted_at)->toDateTimeString()],
                    'catalog',
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBarcode(ProductVariant $variant, array $data): ProductBarcode
    {
        try {
            return DB::transaction(function () use ($variant, $data): ProductBarcode {
                $this->guard->assertWritable();

                $lockedVariant = ProductVariant::query()
                    ->whereKey($variant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $isPrimary = (bool) ($data['is_primary'] ?? false);

                if ($isPrimary) {
                    $this->displacePrimaryBarcodes((int) $lockedVariant->id);
                }

                $barcode = ProductBarcode::create([
                    'variant_id' => (int) $lockedVariant->id,
                    'barcode' => $data['barcode'],
                    'type' => $data['type'],
                    'is_primary' => $isPrimary,
                ]);

                $this->auditLogger->record(
                    'catalog.product_barcodes.created',
                    'product_barcodes',
                    (int) $barcode->id,
                    null,
                    null,
                    $this->barcodeAuditValues($barcode),
                    'catalog',
                );

                return $barcode;
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateBarcode(
        ProductVariant $variant,
        ProductBarcode $barcode,
        array $data,
    ): ProductBarcode {
        try {
            return DB::transaction(function () use ($variant, $barcode, $data): ProductBarcode {
                $this->guard->assertWritable();

                $lockedVariant = ProductVariant::query()
                    ->whereKey($variant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedBarcode = ProductBarcode::query()
                    ->whereKey($barcode->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedBarcode->variant_id !== (int) $lockedVariant->id) {
                    abort(404);
                }

                $oldValues = $this->barcodeAuditValues($lockedBarcode);
                $isPrimary = array_key_exists('is_primary', $data)
                    ? (bool) $data['is_primary']
                    : (bool) $lockedBarcode->is_primary;

                if ($isPrimary) {
                    $this->displacePrimaryBarcodes((int) $lockedVariant->id, (int) $lockedBarcode->id);
                }

                $lockedBarcode->fill([
                    'barcode' => $data['barcode'],
                    'type' => $data['type'],
                    'is_primary' => $isPrimary,
                ]);
                $lockedBarcode->save();

                $this->auditLogger->record(
                    'catalog.product_barcodes.updated',
                    'product_barcodes',
                    (int) $lockedBarcode->id,
                    null,
                    $oldValues,
                    $this->barcodeAuditValues($lockedBarcode),
                    'catalog',
                );

                return $lockedBarcode;
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    public function deleteBarcode(ProductVariant $variant, ProductBarcode $barcode): void
    {
        try {
            DB::transaction(function () use ($variant, $barcode): void {
                $this->guard->assertWritable();

                $lockedVariant = ProductVariant::query()
                    ->whereKey($variant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedBarcode = ProductBarcode::query()
                    ->whereKey($barcode->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lockedBarcode->variant_id !== (int) $lockedVariant->id) {
                    abort(404);
                }

                $oldValues = $this->barcodeAuditValues($lockedBarcode);

                $lockedBarcode->delete();

                $this->auditLogger->record(
                    'catalog.product_barcodes.deleted',
                    'product_barcodes',
                    (int) $oldValues['id'],
                    null,
                    $oldValues,
                    null,
                    'catalog',
                );
            }, 3);
        } catch (QueryException $exception) {
            $this->uniqueViolation->rethrow($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productCreateAttributes(array $data): array
    {
        return [
            'category_id' => $data['category_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'internal_code' => $data['internal_code'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'tracks_stock' => (bool) ($data['tracks_stock'] ?? true),
            'is_sellable' => (bool) ($data['is_sellable'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            // El CRUD manual siempre nace como POS. El payload no puede
            // atribuir un origen de canal o importación ficticio.
            'origin' => 'pos',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productUpdateAttributes(array $data): array
    {
        // `origin` no se actualiza desde el CRUD manual: se preserva la
        // procedencia real ya guardada (incluida la de integraciones futuras).
        $attributes = [
            'type' => $data['type'],
            'name' => $data['name'],
        ];

        foreach ([
            'category_id', 'brand_id', 'description', 'internal_code', 'image_url', 'tax_rate_id',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        foreach (['tracks_stock', 'is_sellable', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = (bool) $data[$field];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createVariantRecord(Product $product, array $data, bool $canViewCost): ProductVariant
    {
        $attributeValueIds = $data['attribute_value_ids'] ?? [];

        $variant = ProductVariant::create([
            'product_id' => (int) $product->id,
            'sku' => $data['sku'],
            'price' => $data['price'],
            // `cost_price` es NOT NULL: si llega null/omitido se usa el default
            // seguro 0; la validación rechaza null/"" enviados explícitamente.
            'cost_price' => $canViewCost && isset($data['cost_price'])
                ? $data['cost_price']
                : 0,
            'unit' => $data['unit'],
            'sold_by_weight' => (bool) ($data['sold_by_weight'] ?? false),
            'max_discount_percent' => $data['max_discount_percent'] ?? null,
            'min_price' => $data['min_price'] ?? null,
            'allow_price_override' => (bool) ($data['allow_price_override'] ?? true),
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'combo_deducts_components' => (bool) ($data['combo_deducts_components'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->syncAttributeValues($variant, $attributeValueIds);

        return $variant;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function variantUpdateAttributes(array $data, bool $canViewCost): array
    {
        $attributes = [
            'sku' => $data['sku'],
            'price' => $data['price'],
            'unit' => $data['unit'],
        ];

        foreach (['max_discount_percent', 'min_price', 'tax_rate_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        foreach ([
            'sold_by_weight', 'allow_price_override', 'combo_deducts_components', 'is_active',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = (bool) $data[$field];
            }
        }

        // Un null/"" no debe reemplazar el costo existente en una columna NOT NULL.
        if ($canViewCost && isset($data['cost_price']) && $data['cost_price'] !== '') {
            $attributes['cost_price'] = $data['cost_price'];
        }

        return $attributes;
    }

    /**
     * @param  list<int|string>  $tagIds
     */
    private function syncTags(Product $product, array $tagIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $tagIds)));
        $product->tags()->sync($ids);
    }

    /**
     * @param  list<int|string>  $attributeValueIds
     */
    private function syncAttributeValues(ProductVariant $variant, array $attributeValueIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $attributeValueIds)));
        $this->assertAttributeValuesAreValid($ids);

        DB::table('product_variant_attribute_values')
            ->where('variant_id', $variant->id)
            ->delete();

        if ($ids === []) {
            return;
        }

        DB::table('product_variant_attribute_values')->insert(
            array_map(
                fn (int $id): array => [
                    'variant_id' => (int) $variant->id,
                    'attribute_value_id' => $id,
                ],
                $ids,
            ),
        );
    }

    /**
     * @param  list<int|string>  $attributeValueIds
     */
    private function assertAttributeValuesAreValid(array $attributeValueIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $attributeValueIds)));

        if ($ids === []) {
            return;
        }

        $rows = DB::table('attribute_values')
            ->whereIn('id', $ids)
            ->get(['id', 'attribute_id']);

        if ($rows->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => 'Uno o más valores de atributo no existen.',
            ]);
        }

        if ($rows->pluck('attribute_id')->unique()->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => 'Cada variante admite como máximo un valor por atributo.',
            ]);
        }
    }

    private function assertVariantCount(string $type, int $count): void
    {
        $valid = match ($type) {
            'simple' => $count === 1,
            'variable' => $count >= 2,
            'combo' => $count >= 1,
            default => false,
        };

        if (! $valid) {
            $message = match ($type) {
                'simple' => 'Un producto simple debe tener exactamente una variante.',
                'variable' => 'Un producto variable debe tener al menos dos variantes.',
                'combo' => 'Un producto combo debe tener al menos una variante contractual.',
                default => 'El tipo de producto no es válido.',
            };

            throw ValidationException::withMessages(['type' => $message]);
        }
    }

    private function assertVariantAdditionAllowed(string $type, int $existing): void
    {
        $this->assertVariantCount($type, $existing + 1);
    }

    private function lockVariant(Product $product, ProductVariant $variant): ProductVariant
    {
        $lockedProduct = Product::query()
            ->whereKey($product->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $lockedVariant = ProductVariant::query()
            ->whereKey($variant->getKey())
            ->where('product_id', $lockedProduct->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedVariant;
    }

    private function displacePrimaryBarcodes(int $variantId, ?int $exceptId = null): void
    {
        $barcodes = ProductBarcode::query()
            ->where('variant_id', $variantId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($barcodes as $barcode) {
            if ($exceptId !== null && (int) $barcode->id === $exceptId) {
                continue;
            }

            if (! (bool) $barcode->is_primary) {
                continue;
            }

            $oldValues = $this->barcodeAuditValues($barcode);
            $barcode->is_primary = false;
            $barcode->save();

            $this->auditLogger->record(
                'catalog.product_barcodes.primary_displaced',
                'product_barcodes',
                (int) $barcode->id,
                null,
                $oldValues,
                $this->barcodeAuditValues($barcode),
                'catalog',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productAuditValues(Product $product): array
    {
        $values = $product->only([
            'id', 'category_id', 'brand_id', 'type', 'name', 'description', 'internal_code',
            'image_url', 'tax_rate_id', 'tracks_stock', 'is_sellable', 'is_active', 'origin',
        ]);

        $values['tag_ids'] = DB::table('product_tag')
            ->where('product_id', $product->id)
            ->orderBy('tag_id')
            ->pluck('tag_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function variantAuditValues(ProductVariant $variant, bool $canViewCost): array
    {
        $values = $variant->only([
            'id', 'product_id', 'sku', 'price', 'unit', 'sold_by_weight',
            'max_discount_percent', 'min_price', 'allow_price_override', 'tax_rate_id',
            'combo_deducts_components', 'is_active',
        ]);

        if ($canViewCost) {
            $values['cost_price'] = $variant->cost_price;
        }

        $values['attribute_value_ids'] = DB::table('product_variant_attribute_values')
            ->where('variant_id', $variant->id)
            ->orderBy('attribute_value_id')
            ->pluck('attribute_value_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function barcodeAuditValues(ProductBarcode $barcode): array
    {
        return $barcode->only(['id', 'variant_id', 'barcode', 'type', 'is_primary']);
    }
}
