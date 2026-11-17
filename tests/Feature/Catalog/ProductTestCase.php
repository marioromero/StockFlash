<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base de pruebas CATPROD-001. Toda la suite usa SQLite :memory:.
 */
abstract class ProductTestCase extends CatalogMastersTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        $suffix = Str::upper(Str::random(6));

        return array_merge([
            'category_id' => null,
            'brand_id' => null,
            'type' => 'simple',
            'name' => 'Producto '.$suffix,
            'description' => 'Descripción del producto.',
            'internal_code' => 'P-'.$suffix,
            'image_url' => null,
            'tax_rate_id' => null,
            'tracks_stock' => true,
            'is_sellable' => true,
            'is_active' => true,
            'origin' => 'pos',
            'tag_ids' => [],
            'variants' => [$this->variantPayload()],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function variantPayload(array $overrides = []): array
    {
        $suffix = Str::upper(Str::random(6));

        return array_merge([
            'sku' => 'SKU-'.$suffix,
            'price' => '1000.00',
            'cost_price' => '500.0000',
            'unit' => 'unit',
            'sold_by_weight' => false,
            'max_discount_percent' => null,
            'min_price' => null,
            'allow_price_override' => true,
            'tax_rate_id' => null,
            'combo_deducts_components' => true,
            'is_active' => true,
            'attribute_value_ids' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function barcodePayload(array $overrides = []): array
    {
        return array_merge([
            'barcode' => '780'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'type' => 'ean13',
            'is_primary' => false,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createSimpleProduct(array $overrides = [], array $variantOverrides = []): Product
    {
        $payload = $this->productPayload($overrides);
        $payload['variants'] = [$this->variantPayload($variantOverrides)];

        $this->actingAs($this->admin)
            ->post('/catalog/products', $payload)
            ->assertRedirect();

        return Product::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createVariableProduct(array $overrides = [], int $variants = 2): Product
    {
        $payload = $this->productPayload(array_merge(['type' => 'variable'], $overrides));
        $payload['variants'] = [];

        for ($i = 0; $i < $variants; $i++) {
            $payload['variants'][] = $this->variantPayload();
        }

        $this->actingAs($this->admin)
            ->post('/catalog/products', $payload)
            ->assertRedirect();

        return Product::query()->latest('id')->firstOrFail();
    }

    protected function addVariant(Product $product, array $overrides = []): ProductVariant
    {
        $this->actingAs($this->admin)
            ->post("/catalog/products/{$product->id}/variants", $this->variantPayload($overrides))
            ->assertRedirect();

        return ProductVariant::query()->latest('id')->firstOrFail();
    }

    protected function addBarcode(Product $product, ProductVariant $variant, array $overrides = []): int
    {
        $this->actingAs($this->admin)
            ->post(
                "/catalog/products/{$product->id}/variants/{$variant->id}/barcodes",
                $this->barcodePayload($overrides),
            )
            ->assertRedirect();

        return (int) DB::table('product_barcodes')->latest('id')->value('id');
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function makeRoleWithPermissions(array $permissions): Role
    {
        $role = Role::create([
            'code' => 'catprod_'.Str::lower(Str::random(8)),
            'name' => 'Rol CATPROD '.Str::upper(Str::random(4)),
            'authority_level' => 30,
            'is_system' => 0,
        ]);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('code', $permission)->value('id');

            if ($permissionId !== null) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        return $role;
    }

    protected function makeManagerWithoutCostPermission(): User
    {
        $branch = $this->makeBranch();
        $role = $this->makeRoleWithPermissions(['catalog.view', 'catalog.manage']);

        return $this->makeBranchUser($branch, $role);
    }

    /**
     * @return array{0: Attribute, 1: AttributeValue}
     */
    protected function makeAttributeValue(string $attributeName, string $value): array
    {
        $attribute = Attribute::create(['name' => $attributeName]);
        $attributeValue = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => $value,
        ]);

        return [$attribute, $attributeValue];
    }

    protected function makeTag(string $name): Tag
    {
        return Tag::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        ]);
    }
}
