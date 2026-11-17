<?php

namespace App\Http\Requests\Catalog;

use App\Http\Middleware\CheckPermission;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Allowlist del producto y de las variantes iniciales.
 *
 * El costo solo entra a `validated()` cuando el actor tiene
 * `catalog.view_cost`; sin ese permiso un payload con `cost_price` es
 * ignorado y los costos existentes se preservan. `last_cost` nunca tiene
 * regla: no es editable desde esta pantalla.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['name', 'internal_code'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['category_id', 'brand_id', 'description', 'internal_code', 'image_url', 'tax_rate_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (isset($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                if (isset($variant['sku']) && is_string($variant['sku'])) {
                    $variant['sku'] = trim($variant['sku']);
                }

                foreach (['cost_price', 'max_discount_percent', 'min_price', 'tax_rate_id'] as $field) {
                    if (array_key_exists($field, $variant) && $variant[$field] === '') {
                        $variant[$field] = null;
                    }
                }

                $data['variants'][$index] = $variant;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $creating = ! $product instanceof Product;
        $productId = $product instanceof Product ? (int) $product->id : null;
        $canViewCost = $this->actorCanViewCost();

        $rules = [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at')->where('is_active', 1),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->whereNull('deleted_at')->where('is_active', 1),
            ],
            'type' => ['required', 'string', Rule::in(['simple', 'variable', 'combo'])],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'internal_code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('products', 'internal_code')->ignore($productId),
            ],
            'image_url' => ['nullable', 'string', 'max:500'],
            'tax_rate_id' => [
                'nullable',
                'integer',
                Rule::exists('tax_rates', 'id')->where('is_active', 1),
            ],
            'tracks_stock' => ['sometimes', 'boolean'],
            'is_sellable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'origin' => ['required', 'string', Rule::in(['pos', 'channel', 'import'])],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];

        if ($creating) {
            $rules += [
                'variants' => ['required', 'array', 'min:1'],
                'variants.*.sku' => [
                    'required',
                    'string',
                    'max:100',
                    'distinct',
                    Rule::unique('product_variants', 'sku'),
                ],
                'variants.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
                'variants.*.unit' => [
                    'required',
                    'string',
                    Rule::in(['unit', 'kg', 'g', 'lt', 'ml', 'm', 'box', 'other']),
                ],
                'variants.*.sold_by_weight' => ['sometimes', 'boolean'],
                'variants.*.max_discount_percent' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
                'variants.*.min_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
                'variants.*.allow_price_override' => ['sometimes', 'boolean'],
                'variants.*.tax_rate_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('tax_rates', 'id')->where('is_active', 1),
                ],
                'variants.*.combo_deducts_components' => ['sometimes', 'boolean'],
                'variants.*.is_active' => ['sometimes', 'boolean'],
                'variants.*.attribute_value_ids' => ['sometimes', 'array'],
                'variants.*.attribute_value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
            ];

            if ($canViewCost) {
                // Si el costo se envía debe ser numérico: null o "" no pueden
                // llegar a la columna NOT NULL. Omitirlo conserva el default 0.
                $rules['variants.*.cost_price'] = [
                    'sometimes',
                    'required',
                    'numeric',
                    'min:0',
                    'decimal:0,4',
                    'max:9999999999.9999',
                ];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $product = $this->route('product');
            $creating = ! $product instanceof Product;

            if (in_array($type, ['simple', 'variable', 'combo'], true)) {
                if ($creating) {
                    $variants = $this->input('variants');
                    if (is_array($variants)) {
                        $this->assertVariantCount($validator, (string) $type, count($variants), 'variants');

                        foreach ($variants as $index => $variant) {
                            if (is_array($variant) && isset($variant['attribute_value_ids']) && is_array($variant['attribute_value_ids'])) {
                                $this->assertOneValuePerAttribute(
                                    $validator,
                                    $variant['attribute_value_ids'],
                                    "variants.{$index}.attribute_value_ids",
                                );
                            }
                        }
                    }
                } elseif ($product instanceof Product) {
                    $this->assertVariantCount(
                        $validator,
                        (string) $type,
                        $product->variants()->count(),
                        'type',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'La categoría seleccionada no existe, está eliminada o está inactiva.',
            'brand_id.exists' => 'La marca seleccionada no existe, está eliminada o está inactiva.',
            'type.required' => 'El tipo de producto es obligatorio.',
            'type.in' => 'El tipo de producto debe ser simple, variable o combo.',
            'name.required' => 'El nombre del producto es obligatorio.',
            'name.max' => 'El nombre del producto no puede superar los 255 caracteres.',
            'internal_code.max' => 'El código interno no puede superar los 60 caracteres.',
            'internal_code.unique' => 'Ya existe un producto con ese código interno (incluidos los eliminados).',
            'image_url.max' => 'La URL de imagen no puede superar los 500 caracteres.',
            'tax_rate_id.exists' => 'La tasa de impuesto seleccionada no existe o está inactiva.',
            'origin.in' => 'El origen debe ser pos, channel o import.',
            'tag_ids.*.exists' => 'Una de las etiquetas seleccionadas no existe.',
            'variants.required' => 'Debe registrar al menos una variante.',
            'variants.min' => 'Debe registrar al menos una variante.',
            'variants.*.sku.required' => 'El SKU de cada variante es obligatorio.',
            'variants.*.sku.max' => 'El SKU no puede superar los 100 caracteres.',
            'variants.*.sku.distinct' => 'Los SKU de las variantes deben ser distintos.',
            'variants.*.sku.unique' => 'Ya existe una variante con ese SKU (incluidas las eliminadas).',
            'variants.*.price.required' => 'El precio base de cada variante es obligatorio.',
            'variants.*.price.decimal' => 'El precio base admite como máximo dos decimales.',
            'variants.*.price.min' => 'El precio base no puede ser negativo.',
            'variants.*.price.max' => 'El precio base excede el máximo permitido (999.999.999.999,99).',
            'variants.*.cost_price.required' => 'El costo neto enviado no es válido; indique un número o deje el campo vacío.',
            'variants.*.cost_price.decimal' => 'El costo admite como máximo cuatro decimales.',
            'variants.*.cost_price.min' => 'El costo no puede ser negativo.',
            'variants.*.cost_price.max' => 'El costo excede el máximo permitido (9.999.999.999,9999).',
            'variants.*.unit.in' => 'La unidad de la variante no es válida.',
            'variants.*.max_discount_percent.between' => 'El descuento máximo debe estar entre 0 y 100.',
            'variants.*.min_price.min' => 'El precio mínimo no puede ser negativo.',
            'variants.*.min_price.max' => 'El precio mínimo excede el máximo permitido (999.999.999.999,99).',
            'variants.*.tax_rate_id.exists' => 'La tasa de impuesto de la variante no existe o está inactiva.',
            'variants.*.attribute_value_ids.*.exists' => 'Uno de los valores de atributo no existe.',
        ];
    }

    private function actorCanViewCost(): bool
    {
        $user = $this->user();

        return $user !== null && CheckPermission::allows($user, 'catalog.view_cost');
    }

    private function assertVariantCount(
        Validator $validator,
        string $type,
        int $count,
        string $field,
    ): void {
        if ($type === 'simple' && $count !== 1) {
            $validator->errors()->add($field, 'Un producto simple debe tener exactamente una variante.');

            return;
        }

        if ($type === 'variable' && $count < 2) {
            $validator->errors()->add($field, 'Un producto variable debe tener al menos dos variantes.');

            return;
        }

        if ($type === 'combo' && $count < 1) {
            $validator->errors()->add($field, 'Un producto combo debe tener al menos una variante contractual.');
        }
    }

    /**
     * @param  array<int, mixed>  $attributeValueIds
     */
    private function assertOneValuePerAttribute(
        Validator $validator,
        array $attributeValueIds,
        string $field,
    ): void {
        $ids = array_values(array_unique(array_map('intval', $attributeValueIds)));

        if ($ids === []) {
            return;
        }

        $attributeIds = DB::table('attribute_values')
            ->whereIn('id', $ids)
            ->pluck('attribute_id');

        if ($attributeIds->count() !== count($ids) || $attributeIds->unique()->count() !== count($ids)) {
            $validator->errors()->add(
                $field,
                'Cada variante admite como máximo un valor por atributo y todos deben existir.',
            );
        }
    }
}
