<?php

namespace App\Http\Requests\Catalog;

use App\Http\Middleware\CheckPermission;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Allowlist de variante. El costo solo se valida si el actor tiene
 * `catalog.view_cost`; `last_cost` nunca se acepta desde el cliente.
 */
class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['sku']) && is_string($data['sku'])) {
            $data['sku'] = trim($data['sku']);
        }

        foreach (['cost_price', 'max_discount_percent', 'min_price', 'tax_rate_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = $variant instanceof ProductVariant ? (int) $variant->id : null;
        $canViewCost = $this->actorCanViewCost();

        $rules = [
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'unit' => ['required', 'string', Rule::in(['unit', 'kg', 'g', 'lt', 'ml', 'm', 'box', 'other'])],
            'sold_by_weight' => ['sometimes', 'boolean'],
            'max_discount_percent' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'allow_price_override' => ['sometimes', 'boolean'],
            'tax_rate_id' => [
                'nullable',
                'integer',
                Rule::exists('tax_rates', 'id')->where('is_active', 1),
            ],
            'combo_deducts_components' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
        ];

        if ($canViewCost) {
            // Si el costo se envía debe ser numérico: null o "" no pueden
            // llegar a la columna NOT NULL. Omitirlo preserva el costo actual.
            $rules['cost_price'] = [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'decimal:0,4',
                'max:9999999999.9999',
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('attribute_value_ids') && is_array($this->input('attribute_value_ids'))) {
                $this->assertOneValuePerAttribute(
                    $validator,
                    $this->input('attribute_value_ids'),
                    'attribute_value_ids',
                );
            }

            $variant = $this->route('variant');
            $product = $this->route('product');

            if ($variant instanceof ProductVariant || ! $product instanceof Product) {
                return;
            }

            $existing = $product->variants()->count();
            $type = (string) $product->type;

            if ($type === 'simple' && $existing >= 1) {
                $validator->errors()->add('sku', 'Un producto simple solo admite una variante.');
            } elseif ($type === 'variable' && $existing + 1 < 2) {
                $validator->errors()->add('sku', 'Un producto variable debe tener al menos dos variantes.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'El SKU de la variante es obligatorio.',
            'sku.max' => 'El SKU no puede superar los 100 caracteres.',
            'sku.unique' => 'Ya existe una variante con ese SKU (incluidas las eliminadas).',
            'price.required' => 'El precio base es obligatorio.',
            'price.numeric' => 'El precio base debe ser numérico.',
            'price.decimal' => 'El precio base admite como máximo dos decimales.',
            'price.min' => 'El precio base no puede ser negativo.',
            'price.max' => 'El precio base excede el máximo permitido (999.999.999.999,99).',
            'unit.in' => 'La unidad de la variante no es válida.',
            'max_discount_percent.between' => 'El descuento máximo debe estar entre 0 y 100.',
            'max_discount_percent.decimal' => 'El descuento máximo admite como máximo dos decimales.',
            'min_price.min' => 'El precio mínimo no puede ser negativo.',
            'min_price.decimal' => 'El precio mínimo admite como máximo dos decimales.',
            'min_price.max' => 'El precio mínimo excede el máximo permitido (999.999.999.999,99).',
            'tax_rate_id.exists' => 'La tasa de impuesto seleccionada no existe o está inactiva.',
            'cost_price.required' => 'El costo neto enviado no es válido; indique un número o deje el campo vacío.',
            'cost_price.decimal' => 'El costo admite como máximo cuatro decimales.',
            'cost_price.min' => 'El costo no puede ser negativo.',
            'cost_price.max' => 'El costo excede el máximo permitido (9.999.999.999,9999).',
            'attribute_value_ids.*.exists' => 'Uno de los valores de atributo no existe.',
        ];
    }

    private function actorCanViewCost(): bool
    {
        $user = $this->user();

        return $user !== null && CheckPermission::allows($user, 'catalog.view_cost');
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
