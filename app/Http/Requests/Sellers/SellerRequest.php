<?php

namespace App\Http\Requests\Sellers;

use App\Models\Seller;
use App\Support\Sellers\SellerCode;
use App\Support\Sellers\SellerCommission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Allowlist de campos de `sellers` (SEL-01).
 *
 * Solo valida la forma. La autorización real ocurre en la ruta
 * (`sellers.manage`, módulo y alcance por sucursal) y las reglas de negocio
 * (sucursales permitidas, usuario con acceso, pivote y auditoría) en el
 * servicio transaccional.
 *
 * `code` se valida y normaliza solo al crear: es inmutable después. Campos
 * como `is_active`, `anonymized_at`, `deleted_at`, `id` o `user` no se
 * consideran aunque el cliente los envíe.
 */
class SellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['code', 'name', 'commission_percent'] as $field) {
            if (! array_key_exists($field, $data) || ! is_string($data[$field])) {
                continue;
            }

            $data[$field] = trim($data[$field]);
        }

        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = SellerCode::normalize($data['code']);
        }

        if (array_key_exists('commission_percent', $data) && $data['commission_percent'] === '') {
            $data['commission_percent'] = null;
        }

        if (array_key_exists('user_id', $data) && $data['user_id'] === '') {
            $data['user_id'] = null;
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $isUpdate = $this->route('seller') !== null;

        $rules = [
            'name' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:150']
                : ['required', 'string', 'max:150'],
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', 1)->whereNull('deleted_at')
                ),
            ],
            'commission_percent' => [
                'sometimes',
                'nullable',
                'string',
                'regex:'.SellerCommission::PATTERN,
            ],
            'branches' => $isUpdate
                ? ['sometimes', 'array']
                : ['required', 'array', 'min:1'],
            'branches.*' => [
                'integer',
                'min:1',
                'distinct',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('is_active', 1)->whereNull('deleted_at')
                ),
            ],
        ];

        if (! $isUpdate) {
            $rules['code'] = [
                'required',
                'string',
                'max:20',
                'regex:'.SellerCode::PATTERN,
                Rule::notIn([Seller::RESERVED_CODE]),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // La interfaz muestra un único mensaje de sucursales; se copia el
            // primer error anidado (`branches.0`) a la clave `branches`.
            if ($validator->errors()->has('branches')) {
                return;
            }

            foreach ($validator->errors()->keys() as $key) {
                if (! str_starts_with($key, 'branches.')) {
                    continue;
                }

                $messages = $validator->errors()->get($key);

                if ($messages !== []) {
                    $validator->errors()->add('branches', $messages[0]);

                    return;
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código del vendedor es obligatorio.',
            'code.max' => 'El código no puede superar los 20 caracteres.',
            'code.regex' => 'El código debe usar solo letras, números, punto, guion o guion bajo.',
            'code.not_in' => 'El código NONE está reservado para el vendedor «Sin asignar».',
            'name.required' => 'El nombre del vendedor es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'user_id.integer' => 'Seleccione un usuario válido.',
            'user_id.min' => 'Seleccione un usuario válido.',
            'user_id.exists' => 'El usuario seleccionado no existe o no está activo.',
            'commission_percent.string' => 'La comisión debe ser una cadena decimal entre 0 y 100.',
            'commission_percent.regex' => 'La comisión debe estar entre 0 y 100, con hasta dos decimales.',
            'branches.required' => 'Seleccione al menos una sucursal activa.',
            'branches.array' => 'Las sucursales seleccionadas no son válidas.',
            'branches.min' => 'Seleccione al menos una sucursal activa.',
            'branches.*.integer' => 'Las sucursales seleccionadas no son válidas.',
            'branches.*.min' => 'Las sucursales seleccionadas no son válidas.',
            'branches.*.distinct' => 'No puede repetir la misma sucursal.',
            'branches.*.exists' => 'Todas las sucursales asignadas deben existir y estar activas.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        if (array_key_exists('code', $validated)) {
            $validated['code'] = SellerCode::normalize((string) $validated['code']);
        }

        if (array_key_exists('commission_percent', $validated)) {
            $validated['commission_percent'] = SellerCommission::canonical(
                $validated['commission_percent'] === null ? null : (string) $validated['commission_percent']
            );
        }

        if (array_key_exists('user_id', $validated) && $validated['user_id'] !== null) {
            $validated['user_id'] = (int) $validated['user_id'];
        }

        if (array_key_exists('branches', $validated)) {
            $validated['branches'] = array_values(array_map(
                fn ($branchId): int => (int) $branchId,
                $validated['branches']
            ));
        }

        return $key === null ? $validated : ($validated[$key] ?? $default);
    }
}
