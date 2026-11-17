<?php

namespace App\Http\Requests\Catalog;

use App\Models\TaxRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist exacta de `tax_rates`. La autorización real ocurre en la ruta
 * (`can.permission:catalog.manage`); aquí solo se valida la forma.
 */
class TaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['code', 'name'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('fiscal_code', $data)) {
            if (is_string($data['fiscal_code'])) {
                $data['fiscal_code'] = trim($data['fiscal_code']);
            }

            if ($data['fiscal_code'] === '') {
                $data['fiscal_code'] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $taxRate = $this->route('taxRate');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tax_rates', 'code')->ignore($taxRate instanceof TaxRate ? $taxRate->id : null),
            ],
            'name' => ['required', 'string', 'max:80'],
            'rate' => ['required', 'numeric', 'between:0,100'],
            'is_exempt' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'fiscal_code' => ['nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            // Claves sensibles/cercanas que no pertenecen al contrato.
            'percentage' => ['prohibited'],
            'tax_percentage' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la tasa es obligatorio.',
            'code.max' => 'El código de la tasa no puede superar los 20 caracteres.',
            'code.unique' => 'Ya existe una tasa de impuesto con ese código.',
            'name.required' => 'El nombre de la tasa es obligatorio.',
            'name.max' => 'El nombre de la tasa no puede superar los 80 caracteres.',
            'rate.required' => 'El porcentaje es obligatorio.',
            'rate.numeric' => 'El porcentaje debe ser numérico.',
            'rate.between' => 'El porcentaje debe estar entre 0 y 100.',
            'fiscal_code.max' => 'El código fiscal no puede superar los 10 caracteres.',
            'is_exempt.boolean' => 'El indicador de exento no es válido.',
            'is_default.boolean' => 'El indicador de tasa predeterminada no es válido.',
            'is_active.boolean' => 'El indicador de activo no es válido.',
            'percentage.prohibited' => 'La columna de porcentaje contractual es «rate»; no se acepta «percentage».',
            'tax_percentage.prohibited' => 'La columna de porcentaje contractual es «rate»; no se acepta «tax_percentage».',
        ];
    }
}
