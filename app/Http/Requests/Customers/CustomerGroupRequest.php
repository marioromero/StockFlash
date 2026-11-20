<?php

namespace App\Http\Requests\Customers;

use App\Models\CustomerGroup;
use App\Support\Customers\DiscountPercent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist de `customer_groups` (Parte 2 §6.1 · CUS-02).
 *
 * El estado `is_active` no se edita en el formulario: se cambia con los
 * endpoints de activación/desactivación. El código es único y se recorta.
 */
class CustomerGroupRequest extends FormRequest
{
    private const DISCOUNT_PATTERN = '/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['code', 'name'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        foreach (['default_discount_percent', 'max_discount_percent'] as $field) {
            if (! array_key_exists($field, $data) || ! is_string($data[$field])) {
                continue;
            }

            $data[$field] = trim($data[$field]);

            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $group = $this->route('group');
        $isUpdate = $group !== null;
        $ignoreId = $group instanceof CustomerGroup ? $group->id : $group;

        return [
            'code' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                    Rule::unique('customer_groups', 'code')->ignore($ignoreId)]
                : ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                    Rule::unique('customer_groups', 'code')],
            'name' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:100']
                : ['required', 'string', 'max:100'],
            'default_discount_percent' => ['nullable', 'string', 'regex:'.self::DISCOUNT_PATTERN],
            'max_discount_percent' => ['nullable', 'string', 'regex:'.self::DISCOUNT_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código del grupo es obligatorio.',
            'code.max' => 'El código del grupo no puede superar los 30 caracteres.',
            'code.regex' => 'El código del grupo solo admite letras, números, punto, guion y guion bajo.',
            'code.unique' => 'Ya existe un grupo de clientes con ese código.',
            'name.required' => 'El nombre del grupo es obligatorio.',
            'name.max' => 'El nombre del grupo no puede superar los 100 caracteres.',
            'default_discount_percent.regex' => 'El descuento por defecto debe estar entre 0 y 100, con hasta dos decimales.',
            'max_discount_percent.regex' => 'El tope del grupo debe estar entre 0 y 100, con hasta dos decimales.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        foreach (['default_discount_percent', 'max_discount_percent'] as $field) {
            if (! array_key_exists($field, $validated) || $validated[$field] === null) {
                continue;
            }

            $validated[$field] = DiscountPercent::canonical($validated[$field]);
        }

        return $key === null ? $validated : ($validated[$key] ?? $default);
    }
}
