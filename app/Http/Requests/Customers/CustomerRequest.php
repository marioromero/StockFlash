<?php

namespace App\Http\Requests\Customers;

use App\Support\Customers\DiscountPercent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allowlist de campos de `customers`.
 *
 * Solo valida la forma; la autorización real ocurre en la ruta
 * (`customers.view` / `customers.manage` / `customers.view_sensitive`).
 *
 * Campos como `is_active`, `origin`, `marketing_opt_in`, `blocked_at`,
 * `anonymized_at`, `fiscal_id_encrypted`, `fiscal_id_hash`, `last_activity_at`
 * o `user_id` no se validan y, por lo tanto, se ignoran aunque el cliente los
 * envíe.
 */
class CustomerRequest extends FormRequest
{
    private const DISCOUNT_PATTERN = '/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach ([
            'type', 'name', 'trade_name', 'rut', 'email', 'phone',
            'business_activity', 'fiscal_address', 'fiscal_commune', 'fiscal_city',
        ] as $field) {
            if (! array_key_exists($field, $data) || ! is_string($data[$field])) {
                continue;
            }

            $data[$field] = trim($data[$field]);

            if ($data[$field] === '' && ! in_array($field, ['type', 'name'], true)) {
                $data[$field] = null;
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

        if (array_key_exists('customer_group_id', $data) && $data['customer_group_id'] === '') {
            $data['customer_group_id'] = null;
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $customerId = $this->route('customer');
        $isUpdate = $customerId !== null;

        return [
            'type' => $isUpdate
                ? ['sometimes', 'required', 'string', Rule::in(['person', 'company'])]
                : ['required', 'string', Rule::in(['person', 'company'])],
            'name' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:150']
                : ['required', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'rut' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'business_activity' => ['nullable', 'string', 'max:150'],
            'fiscal_address' => ['nullable', 'string', 'max:255'],
            'fiscal_commune' => ['nullable', 'string', 'max:100'],
            'fiscal_city' => ['nullable', 'string', 'max:100'],
            'customer_group_id' => ['nullable', 'integer', 'min:1', 'exists:customer_groups,id'],
            'default_discount_percent' => ['nullable', 'string', 'regex:'.self::DISCOUNT_PATTERN],
            'max_discount_percent' => ['nullable', 'string', 'regex:'.self::DISCOUNT_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Seleccione el tipo de cliente (persona o empresa).',
            'type.in' => 'El tipo de cliente debe ser persona o empresa.',
            'name.required' => 'El nombre o razón social es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'rut.max' => 'El RUT no puede superar los 30 caracteres.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo no puede superar los 150 caracteres.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'business_activity.max' => 'El giro no puede superar los 150 caracteres.',
            'fiscal_address.max' => 'El domicilio tributario no puede superar los 255 caracteres.',
            'fiscal_commune.max' => 'La comuna tributaria no puede superar los 100 caracteres.',
            'fiscal_city.max' => 'La ciudad tributaria no puede superar los 100 caracteres.',
            'customer_group_id.integer' => 'Seleccione un grupo de clientes válido.',
            'customer_group_id.min' => 'Seleccione un grupo de clientes válido.',
            'customer_group_id.exists' => 'El grupo de clientes seleccionado no existe.',
            'default_discount_percent.regex' => 'El descuento propio debe estar entre 0 y 100, con hasta dos decimales.',
            'max_discount_percent.regex' => 'El tope propio debe estar entre 0 y 100, con hasta dos decimales.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        // Normaliza los descuentos validados a la forma canónica exacta.
        foreach (['default_discount_percent', 'max_discount_percent'] as $field) {
            if (! array_key_exists($field, $validated) || $validated[$field] === null) {
                continue;
            }

            $validated[$field] = DiscountPercent::canonical($validated[$field]);
        }

        return $key === null ? $validated : ($validated[$key] ?? $default);
    }
}
