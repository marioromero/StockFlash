<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Support\SensitiveKeyFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación de creación y edición de sucursales (BRANCH-001).
 *
 * - `code` se normaliza a mayúsculas y es inmutable tras la creación.
 * - Los ajustes heredables aceptan NULL (heredar de system_configs).
 * - `settings` debe ser un objeto JSON, nunca una lista ni un escalar.
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización se aplica server-side en las rutas con
        // `can.permission:settings.manage`.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (array_key_exists('code', $data)) {
            $data['code'] = is_string($data['code'])
                ? strtoupper(trim($data['code']))
                : $data['code'];
        }

        foreach ([
            'address', 'commune', 'city', 'region', 'phone',
            'sales_flow_mode', 'seller_code_policy', 'allow_negative_stock', 'settings',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $branch = $this->branch();
        $uniqueCode = Rule::unique('branches', 'code');

        if ($branch !== null) {
            // El propio registro no compite contra sí mismo; el cambio de código
            // se rechaza explícitamente en withValidator().
            $uniqueCode->ignore($branch->id);
        }

        return [
            'code' => $branch !== null
                ? ['sometimes', 'required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', $uniqueCode]
                : ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', $uniqueCode],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(['store', 'warehouse', 'online'])],
            'address' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            // El estado activo NO se modifica por edición: se cambia solo con
            // las acciones dedicadas `activate`/`deactivate`, que aplican la
            // verificación transaccional del límite y su propia auditoría.
            // En edición se prohíbe explícitamente para devolver un error claro.
            'is_active' => $branch !== null
                ? ['prohibited']
                : ['sometimes', 'boolean'],
            'requires_cash_control' => ['sometimes', 'boolean'],
            'sales_flow_mode' => ['nullable', 'string', Rule::in(['direct', 'two_step', 'both'])],
            'seller_code_policy' => ['nullable', 'string', Rule::in(['none', 'optional', 'required'])],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'settings' => ['nullable', $this->settingsObjectRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branch = $this->branch();

            if ($branch === null) {
                return;
            }

            $code = $this->input('code');

            if (is_string($code) && $code !== '' && $code !== $branch->code) {
                $validator->errors()->add(
                    'code',
                    'El código de sucursal no se puede modificar una vez creada la sucursal.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de sucursal es obligatorio.',
            'code.string' => 'El código de sucursal debe ser texto.',
            'code.max' => 'El código de sucursal no puede superar los 30 caracteres.',
            'code.regex' => 'El código solo puede contener letras mayúsculas, números y guiones.',
            'code.unique' => 'Ya existe una sucursal con ese código.',
            'name.required' => 'El nombre de la sucursal es obligatorio.',
            'name.string' => 'El nombre de la sucursal debe ser texto.',
            'name.max' => 'El nombre de la sucursal no puede superar los 150 caracteres.',
            'type.required' => 'El tipo de sucursal es obligatorio.',
            'type.in' => 'El tipo de sucursal debe ser local, bodega u online.',
            'address.max' => 'La dirección no puede superar los 255 caracteres.',
            'commune.max' => 'La comuna no puede superar los 100 caracteres.',
            'city.max' => 'La ciudad no puede superar los 100 caracteres.',
            'region.max' => 'La región no puede superar los 100 caracteres.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'is_active.prohibited' => 'El estado activo no se modifica desde la edición; use las acciones Activar o Desactivar del listado.',
            'requires_cash_control.boolean' => 'El control de caja debe ser verdadero o falso.',
            'sales_flow_mode.in' => 'El flujo de venta debe ser directo, en dos pasos o ambos.',
            'seller_code_policy.in' => 'La política de código de vendedor no es válida.',
            'allow_negative_stock.boolean' => 'Permitir stock negativo debe ser verdadero o falso.',
            'settings.string' => 'Los ajustes deben ser un objeto JSON válido.',
        ];
    }

    private function branch(): ?Branch
    {
        $routeBranch = $this->route('branch');

        if ($routeBranch instanceof Branch) {
            return $routeBranch;
        }

        if (is_numeric($routeBranch)) {
            return Branch::query()->find((int) $routeBranch);
        }

        return null;
    }

    private function settingsObjectRule(): callable
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (is_array($value)) {
                if ($value !== [] && array_is_list($value)) {
                    $fail('Los ajustes deben ser un objeto JSON, no una lista.');

                    return;
                }

                if (SensitiveKeyFilter::contains($value)) {
                    $fail('Los ajustes no pueden contener contraseñas, tokens ni secretos.');
                }

                return;
            }

            if (! is_string($value)) {
                $fail('Los ajustes deben ser un objeto JSON válido.');

                return;
            }

            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed[0] !== '{') {
                $fail('Los ajustes deben ser un objeto JSON válido, por ejemplo {"clave": "valor"}.');

                return;
            }

            $decoded = json_decode($trimmed, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                $fail('Los ajustes deben ser un objeto JSON válido.');

                return;
            }

            if (SensitiveKeyFilter::contains($decoded)) {
                $fail('Los ajustes no pueden contener contraseñas, tokens ni secretos.');
            }
        };
    }
}
