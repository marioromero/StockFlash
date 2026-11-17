<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CheckPermission;
use App\Http\Requests\Catalog\AttributeRequest;
use App\Http\Requests\Catalog\AttributeValueRequest;
use App\Http\Requests\Catalog\BrandRequest;
use App\Http\Requests\Catalog\CategoryRequest;
use App\Http\Requests\Catalog\TagRequest;
use App\Http\Requests\Catalog\TaxRateRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Support\AuditLogger;
use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maestros de catálogo P0 (CATMAST-001 · CAT-02/CAT-05).
 *
 * Una única pantalla administrativa para impuestos, categorías, marcas,
 * etiquetas y atributos/valores. Todo el catálogo es común a la instalación:
 * no usa `branch.access` y la auditoría se registra con `branch_id = null`.
 *
 * Las escrituras ocurren dentro de una transacción junto con su auditoría.
 * Si la auditoría falla, la operación completa se revierte. La licencia
 * suspendida fuera de gracia bloquea escrituras (RN-70).
 */
class CatalogMastersController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        $taxRates = TaxRate::query()
            ->orderBy('code')
            ->get()
            ->map(fn (TaxRate $taxRate): array => $this->serializeTaxRate($taxRate))
            ->values();

        $categories = Category::query()
            ->with('defaultTaxRate:id,code,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => $this->serializeCategory($category))
            ->values();

        $brands = Brand::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand): array => $this->serializeBrand($brand))
            ->values();

        $tags = Tag::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => $this->serializeTag($tag))
            ->values();

        $attributes = Attribute::query()
            ->with(['values' => fn ($query) => $query->orderBy('value')])
            ->orderBy('name')
            ->get()
            ->map(fn (Attribute $attribute): array => $this->serializeAttribute($attribute))
            ->values();

        $user = $request->user();

        return Inertia::render('Catalog/Masters', [
            'tax_rates' => $taxRates,
            'categories' => $categories,
            'brands' => $brands,
            'tags' => $tags,
            'attributes' => $attributes,
            'can_manage' => $user !== null && CheckPermission::allows($user, 'catalog.manage'),
            'read_only' => $this->licenseIsReadOnly(),
        ]);
    }

    public function storeTaxRate(TaxRateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data): void {
            $this->assertLicenseAllowsWrites();

            if ($this->boolValue($data, 'is_default', false)) {
                $this->clearOtherDefaultTaxRates(null);
            }

            $taxRate = TaxRate::create($this->taxRateAttributes($data, true));

            $this->auditLogger->record(
                'catalog.tax_rates.created',
                'tax_rates',
                (int) $taxRate->id,
                null,
                null,
                $this->taxRateAuditValues($taxRate),
                'catalog',
            );
        }, 'Tasa de impuesto creada correctamente.');
    }

    public function updateTaxRate(TaxRateRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data, $taxRate): void {
            $this->assertLicenseAllowsWrites();

            $oldValues = $this->taxRateAuditValues($taxRate);

            if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
                $this->clearOtherDefaultTaxRates((int) $taxRate->id);
            }

            $taxRate->fill($this->taxRateAttributes($data, false));
            $taxRate->save();

            $this->auditLogger->record(
                'catalog.tax_rates.updated',
                'tax_rates',
                (int) $taxRate->id,
                null,
                $oldValues,
                $this->taxRateAuditValues($taxRate),
                'catalog',
            );
        }, 'Tasa de impuesto actualizada correctamente.');
    }

    public function destroyTaxRate(TaxRate $taxRate): RedirectResponse
    {
        return $this->mutate(function () use ($taxRate): void {
            $this->assertLicenseAllowsWrites();

            $id = (int) $taxRate->id;

            $referenced = DB::table('categories')->where('default_tax_rate_id', $id)->exists()
                || DB::table('products')->where('tax_rate_id', $id)->exists()
                || DB::table('product_variants')->where('tax_rate_id', $id)->exists();

            if ($referenced) {
                throw ValidationException::withMessages([
                    'tax_rate' => 'No se puede eliminar la tasa: está referenciada por categorías, productos o variantes.',
                ]);
            }

            $oldValues = $this->taxRateAuditValues($taxRate);

            $taxRate->delete();

            $this->auditLogger->record(
                'catalog.tax_rates.deleted',
                'tax_rates',
                $id,
                null,
                $oldValues,
                null,
                'catalog',
            );
        }, 'Tasa de impuesto eliminada correctamente.');
    }

    public function storeCategory(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data): void {
            $this->assertLicenseAllowsWrites();

            $category = Category::create($this->categoryAttributes($data, true));

            $this->auditLogger->record(
                'catalog.categories.created',
                'categories',
                (int) $category->id,
                null,
                null,
                $this->categoryAuditValues($category),
                'catalog',
            );
        }, 'Categoría creada correctamente.');
    }

    public function updateCategory(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data, $category): void {
            $this->assertLicenseAllowsWrites();

            $oldValues = $this->categoryAuditValues($category);

            $category->fill($this->categoryAttributes($data, false));
            $category->save();

            $this->auditLogger->record(
                'catalog.categories.updated',
                'categories',
                (int) $category->id,
                null,
                $oldValues,
                $this->categoryAuditValues($category),
                'catalog',
            );
        }, 'Categoría actualizada correctamente.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        return $this->mutate(function () use ($category): void {
            $this->assertLicenseAllowsWrites();

            $id = (int) $category->id;

            if (DB::table('categories')->where('parent_id', $id)->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'No se puede eliminar la categoría: tiene subcategorías (incluidas las eliminadas).',
                ]);
            }

            if (DB::table('products')->where('category_id', $id)->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'No se puede eliminar la categoría: hay productos que la referencian.',
                ]);
            }

            $oldValues = $this->categoryAuditValues($category);

            $category->delete();

            $this->auditLogger->record(
                'catalog.categories.deleted',
                'categories',
                $id,
                null,
                $oldValues,
                ['deleted_at' => optional($category->deleted_at)->toDateTimeString()],
                'catalog',
            );
        }, 'Categoría eliminada lógicamente.');
    }

    public function storeBrand(BrandRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data): void {
            $this->assertLicenseAllowsWrites();

            $brand = Brand::create($this->brandAttributes($data, true));

            $this->auditLogger->record(
                'catalog.brands.created',
                'brands',
                (int) $brand->id,
                null,
                null,
                $this->brandAuditValues($brand),
                'catalog',
            );
        }, 'Marca creada correctamente.');
    }

    public function updateBrand(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data, $brand): void {
            $this->assertLicenseAllowsWrites();

            $oldValues = $this->brandAuditValues($brand);

            $brand->fill($this->brandAttributes($data, false));
            $brand->save();

            $this->auditLogger->record(
                'catalog.brands.updated',
                'brands',
                (int) $brand->id,
                null,
                $oldValues,
                $this->brandAuditValues($brand),
                'catalog',
            );
        }, 'Marca actualizada correctamente.');
    }

    public function destroyBrand(Brand $brand): RedirectResponse
    {
        return $this->mutate(function () use ($brand): void {
            $this->assertLicenseAllowsWrites();

            $id = (int) $brand->id;

            if (DB::table('products')->where('brand_id', $id)->exists()) {
                throw ValidationException::withMessages([
                    'brand' => 'No se puede eliminar la marca: hay productos que la referencian.',
                ]);
            }

            $oldValues = $this->brandAuditValues($brand);

            $brand->delete();

            $this->auditLogger->record(
                'catalog.brands.deleted',
                'brands',
                $id,
                null,
                $oldValues,
                ['deleted_at' => optional($brand->deleted_at)->toDateTimeString()],
                'catalog',
            );
        }, 'Marca eliminada lógicamente.');
    }

    public function storeTag(TagRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data): void {
            $this->assertLicenseAllowsWrites();

            $tag = Tag::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
            ]);

            $this->auditLogger->record(
                'catalog.tags.created',
                'tags',
                (int) $tag->id,
                null,
                null,
                $this->tagAuditValues($tag),
                'catalog',
            );
        }, 'Etiqueta creada correctamente.');
    }

    public function updateTag(TagRequest $request, Tag $tag): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data, $tag): void {
            $this->assertLicenseAllowsWrites();

            $oldValues = $this->tagAuditValues($tag);

            $tag->name = $data['name'];

            if (array_key_exists('slug', $data) && $data['slug'] !== null && $data['slug'] !== '') {
                $tag->slug = $data['slug'];
            }

            $tag->save();

            $this->auditLogger->record(
                'catalog.tags.updated',
                'tags',
                (int) $tag->id,
                null,
                $oldValues,
                $this->tagAuditValues($tag),
                'catalog',
            );
        }, 'Etiqueta actualizada correctamente.');
    }

    public function destroyTag(Tag $tag): RedirectResponse
    {
        return $this->mutate(function () use ($tag): void {
            $this->assertLicenseAllowsWrites();

            $id = (int) $tag->id;

            if (DB::table('product_tag')->where('tag_id', $id)->exists()) {
                throw ValidationException::withMessages([
                    'tag' => 'No se puede eliminar la etiqueta: hay productos que la referencian.',
                ]);
            }

            $oldValues = $this->tagAuditValues($tag);

            $tag->delete();

            $this->auditLogger->record(
                'catalog.tags.deleted',
                'tags',
                $id,
                null,
                $oldValues,
                null,
                'catalog',
            );
        }, 'Etiqueta eliminada correctamente.');
    }

    public function storeAttribute(AttributeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data): void {
            $this->assertLicenseAllowsWrites();

            $attribute = Attribute::create(['name' => $data['name']]);

            $this->auditLogger->record(
                'catalog.attributes.created',
                'attributes',
                (int) $attribute->id,
                null,
                null,
                $this->attributeAuditValues($attribute),
                'catalog',
            );
        }, 'Atributo creado correctamente.');
    }

    public function updateAttribute(AttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data, $attribute): void {
            $this->assertLicenseAllowsWrites();

            $oldValues = $this->attributeAuditValues($attribute);

            $attribute->name = $data['name'];
            $attribute->save();

            $this->auditLogger->record(
                'catalog.attributes.updated',
                'attributes',
                (int) $attribute->id,
                null,
                $oldValues,
                $this->attributeAuditValues($attribute),
                'catalog',
            );
        }, 'Atributo actualizado correctamente.');
    }

    public function destroyAttribute(Attribute $attribute): RedirectResponse
    {
        return $this->mutate(function () use ($attribute): void {
            $this->assertLicenseAllowsWrites();

            $id = (int) $attribute->id;

            if (DB::table('attribute_values')->where('attribute_id', $id)->exists()) {
                throw ValidationException::withMessages([
                    'attribute' => 'No se puede eliminar el atributo: primero elimine sus valores.',
                ]);
            }

            $hasVariantReferences = DB::table('product_variant_attribute_values')
                ->whereIn(
                    'attribute_value_id',
                    DB::table('attribute_values')->select('id')->where('attribute_id', $id)
                )
                ->exists();

            if ($hasVariantReferences) {
                throw ValidationException::withMessages([
                    'attribute' => 'No se puede eliminar el atributo: sus valores están referenciados por variantes.',
                ]);
            }

            $oldValues = $this->attributeAuditValues($attribute);

            $attribute->delete();

            $this->auditLogger->record(
                'catalog.attributes.deleted',
                'attributes',
                $id,
                null,
                $oldValues,
                null,
                'catalog',
            );
        }, 'Atributo eliminado correctamente.');
    }

    public function storeAttributeValue(AttributeValueRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutate(function () use ($data): void {
            $this->assertLicenseAllowsWrites();

            $attributeValue = AttributeValue::create([
                'attribute_id' => (int) $data['attribute_id'],
                'value' => $data['value'],
            ]);

            $this->auditLogger->record(
                'catalog.attribute_values.created',
                'attribute_values',
                (int) $attributeValue->id,
                null,
                null,
                $this->attributeValueAuditValues($attributeValue),
                'catalog',
            );
        }, 'Valor de atributo creado correctamente.');
    }

    public function updateAttributeValue(
        AttributeValueRequest $request,
        AttributeValue $attributeValue,
    ): RedirectResponse {
        $data = $request->validated();

        return $this->mutate(function () use ($data, $attributeValue): void {
            $this->assertLicenseAllowsWrites();

            $oldValues = $this->attributeValueAuditValues($attributeValue);

            $attributeValue->attribute_id = (int) $data['attribute_id'];
            $attributeValue->value = $data['value'];
            $attributeValue->save();

            $this->auditLogger->record(
                'catalog.attribute_values.updated',
                'attribute_values',
                (int) $attributeValue->id,
                null,
                $oldValues,
                $this->attributeValueAuditValues($attributeValue),
                'catalog',
            );
        }, 'Valor de atributo actualizado correctamente.');
    }

    public function destroyAttributeValue(AttributeValue $attributeValue): RedirectResponse
    {
        return $this->mutate(function () use ($attributeValue): void {
            $this->assertLicenseAllowsWrites();

            $id = (int) $attributeValue->id;

            if (DB::table('product_variant_attribute_values')->where('attribute_value_id', $id)->exists()) {
                throw ValidationException::withMessages([
                    'attribute_value' => 'No se puede eliminar el valor: está referenciado por variantes.',
                ]);
            }

            $oldValues = $this->attributeValueAuditValues($attributeValue);

            $attributeValue->delete();

            $this->auditLogger->record(
                'catalog.attribute_values.deleted',
                'attribute_values',
                $id,
                null,
                $oldValues,
                null,
                'catalog',
            );
        }, 'Valor de atributo eliminado correctamente.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function taxRateAttributes(array $data, bool $creating): array
    {
        $attributes = [
            'code' => $data['code'],
            'name' => $data['name'],
            'rate' => $data['rate'],
        ];

        foreach (['fiscal_code', 'is_exempt', 'is_default', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = in_array($field, ['is_exempt', 'is_default', 'is_active'], true)
                    ? (bool) $data[$field]
                    : $data[$field];
            }
        }

        if ($creating) {
            $attributes += [
                'fiscal_code' => null,
                'is_exempt' => false,
                'is_default' => false,
                'is_active' => true,
            ];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function categoryAttributes(array $data, bool $creating): array
    {
        $attributes = [];

        foreach (['parent_id', 'name', 'max_discount_percent', 'default_tax_rate_id', 'sort_order', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $field === 'is_active' ? (bool) $data[$field] : $data[$field];
            }
        }

        if ($creating) {
            $attributes['slug'] = $data['slug'] ?? Str::slug((string) $data['name']);
            $attributes += [
                'parent_id' => null,
                'max_discount_percent' => null,
                'default_tax_rate_id' => null,
                'sort_order' => 0,
                'is_active' => true,
            ];
        } elseif (array_key_exists('slug', $data) && $data['slug'] !== null && $data['slug'] !== '') {
            $attributes['slug'] = $data['slug'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function brandAttributes(array $data, bool $creating): array
    {
        $attributes = ['name' => $data['name']];

        foreach (['description', 'logo_url', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $field === 'is_active' ? (bool) $data[$field] : $data[$field];
            }
        }

        if ($creating) {
            $attributes['slug'] = $data['slug'] ?? Str::slug((string) $data['name']);
            $attributes += ['description' => null, 'logo_url' => null, 'is_active' => true];
        } elseif (array_key_exists('slug', $data) && $data['slug'] !== null && $data['slug'] !== '') {
            $attributes['slug'] = $data['slug'];
        }

        return $attributes;
    }

    private function clearOtherDefaultTaxRates(?int $exceptId): void
    {
        $query = TaxRate::query()->where('is_default', 1);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * Ejecuta una mutación y su auditoría en la misma transacción.
     */
    private function mutate(callable $operation, string $message): RedirectResponse
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->rethrowUniqueViolation($exception);
        }

        return redirect()
            ->route('catalog.masters.index')
            ->with('success', $message);
    }

    private function rethrowUniqueViolation(QueryException $exception): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        if (! in_array($sqlState, ['23000', '23505'], true)) {
            throw $exception;
        }

        $message = strtolower($exception->getMessage());

        $map = [
            'tax_rates.code' => ['code', 'Ya existe una tasa de impuesto con ese código.'],
            'categories.slug' => ['slug', 'Ya existe una categoría con ese slug (incluidas las eliminadas).'],
            'brands.slug' => ['slug', 'Ya existe una marca con ese slug (incluidas las eliminadas).'],
            'tags.slug' => ['slug', 'Ya existe una etiqueta con ese slug.'],
            'attributes.name' => ['name', 'Ya existe un atributo con ese nombre.'],
            'attribute_values.attribute_id' => ['value', 'Ese valor ya existe para el atributo seleccionado.'],
        ];

        foreach ($map as $needle => [$field, $text]) {
            if (str_contains($message, $needle)) {
                throw ValidationException::withMessages([$field => $text]);
            }
        }

        throw $exception;
    }

    /**
     * RN-70: licencia suspendida fuera de gracia = solo lectura. Sin fila de
     * licencia no se bloquea (a diferencia de altas de sucursales).
     */
    private function assertLicenseAllowsWrites(): void
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (LicenseReadOnlyRule::isReadOnly($license)) {
            throw ValidationException::withMessages([
                'read_only' => 'La licencia está suspendida fuera del período de gracia; el sistema está en modo solo lectura.',
            ]);
        }
    }

    private function licenseIsReadOnly(): bool
    {
        $license = DB::table('license_states')
            ->orderBy('id')
            ->first();

        return LicenseReadOnlyRule::isReadOnly($license);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function boolValue(array $data, string $key, bool $default): bool
    {
        return array_key_exists($key, $data) ? (bool) $data[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTaxRate(TaxRate $taxRate): array
    {
        return [
            'id' => $taxRate->id,
            'code' => $taxRate->code,
            'name' => $taxRate->name,
            'rate' => (string) $taxRate->rate,
            'is_exempt' => (bool) $taxRate->is_exempt,
            'is_default' => (bool) $taxRate->is_default,
            'fiscal_code' => $taxRate->fiscal_code,
            'is_active' => (bool) $taxRate->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'max_discount_percent' => $category->max_discount_percent === null
                ? null
                : (string) $category->max_discount_percent,
            'default_tax_rate_id' => $category->default_tax_rate_id,
            'default_tax_rate' => $category->defaultTaxRate === null
                ? null
                : [
                    'id' => $category->defaultTaxRate->id,
                    'code' => $category->defaultTaxRate->code,
                    'name' => $category->defaultTaxRate->name,
                ],
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBrand(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'description' => $brand->description,
            'logo_url' => $brand->logo_url,
            'is_active' => (bool) $brand->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTag(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttribute(Attribute $attribute): array
    {
        return [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'values' => $attribute->values
                ->map(fn (AttributeValue $value): array => [
                    'id' => $value->id,
                    'value' => $value->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxRateAuditValues(TaxRate $taxRate): array
    {
        return $taxRate->only([
            'code', 'name', 'rate', 'is_exempt', 'is_default', 'fiscal_code', 'is_active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryAuditValues(Category $category): array
    {
        return $category->only([
            'parent_id', 'name', 'slug', 'max_discount_percent', 'default_tax_rate_id',
            'sort_order', 'is_active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function brandAuditValues(Brand $brand): array
    {
        return $brand->only(['name', 'slug', 'description', 'logo_url', 'is_active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function tagAuditValues(Tag $tag): array
    {
        return $tag->only(['name', 'slug']);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeAuditValues(Attribute $attribute): array
    {
        return $attribute->only(['name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeValueAuditValues(AttributeValue $attributeValue): array
    {
        return $attributeValue->only(['attribute_id', 'value']);
    }
}
