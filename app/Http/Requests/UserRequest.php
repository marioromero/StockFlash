<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación de creación y edición de usuarios (USERS-001 · CORE-03).
 *
 * - Email obligatorio, máximo 150, normalizado a minúsculas y único sin
 *   distinguir mayúsculas/minúsculas, incluidos los usuarios eliminados.
 * - Contraseña: obligatoria al crear (15–64), opcional al editar; sin reglas
 *   artificiales de composición (SEC-01).
 * - PIN: opcional, exactamente `system_configs.pin_length` dígitos (default 4).
 * - Acceso global XOR por sucursal; `central_admin` solo global.
 * - `is_active` no se modifica desde aquí: solo por las acciones dedicadas.
 */
class UserRequest extends UserBranchAssignmentRequest
{
    public function prepareForValidation(): void
    {
        $data = $this->all();

        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        foreach ([
            'global_role_id', 'default_branch_id',
            'max_discount_percent', 'max_return_amount',
            'pin', 'password',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        $user = $this->userModel();

        return array_merge(parent::rules(), [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                $this->emailUniqueRule($user),
            ],
            'password' => $user === null
                ? ['required', 'string', 'min:15', 'max:64']
                : ['nullable', 'string', 'min:15', 'max:64'],
            'pin' => ['nullable', 'string', $this->pinLengthRule()],
            'pin_clear' => ['sometimes', 'boolean'],
            'global_role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where('is_system', 1),
            ],
            'default_branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('is_active', 1)->whereNull('deleted_at')
                ),
            ],
            'max_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_return_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (array_key_exists('is_active', $this->all())) {
                $validator->errors()->add(
                    'is_active',
                    'El estado activo no se modifica desde la edición; use las acciones Activar o Desactivar del listado.'
                );
            }

            $user = $this->userModel();
            $input = $this->all();

            $globalRoleProvided = array_key_exists('global_role_id', $input);
            $globalRoleId = $globalRoleProvided
                ? $this->input('global_role_id')
                : $user?->global_role_id;

            $assignmentsProvided = array_key_exists('assignments', $input);

            if ($assignmentsProvided) {
                $assignments = $this->input('assignments', []);
            } elseif ($user !== null) {
                $assignments = DB::table('branch_user')
                    ->where('user_id', $user->getKey())
                    ->get(['branch_id', 'role_id'])
                    ->map(fn (object $row): array => [
                        'branch_id' => (int) $row->branch_id,
                        'role_id' => (int) $row->role_id,
                    ])
                    ->all();
            } else {
                $assignments = [];
            }

            if (! is_array($assignments)) {
                return;
            }

            if (
                $assignmentsProvided
                && $globalRoleId !== null
                && $globalRoleId !== ''
                && $assignments !== []
            ) {
                $validator->errors()->add(
                    'assignments',
                    'Un usuario global no puede tener asignaciones por sucursal. Al convertirlo en global se eliminan sus asignaciones.'
                );
            }

            if ($this->input('pin_clear') && $this->input('pin') !== null) {
                $validator->errors()->add(
                    'pin',
                    'No puede informar un PIN nuevo y solicitar su eliminación a la vez.'
                );
            }

            $defaultBranchProvided = array_key_exists('default_branch_id', $input);
            // Si la clave no se envía, la sucursal inicial efectiva es la ya
            // persistida. Debe revalidarse contra las asignaciones propuestas
            // para no conservar un valor inconsistente (MENOR-1).
            $defaultBranchId = $defaultBranchProvided
                ? $this->input('default_branch_id')
                : $user?->default_branch_id;

            if (
                $defaultBranchId !== null
                && $defaultBranchId !== ''
                && ($globalRoleId === null || $globalRoleId === '')
            ) {
                $assignedBranchIds = collect($assignments)
                    ->pluck('branch_id')
                    ->filter(fn ($id): bool => $id !== null && $id !== '')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if (! in_array((int) $defaultBranchId, $assignedBranchIds, true)) {
                    $validator->errors()->add(
                        'default_branch_id',
                        'La sucursal inicial debe estar entre las sucursales asignadas al usuario por sucursal.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.required' => 'El nombre del usuario es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.max' => 'El correo electrónico no puede superar los 150 caracteres.',
            'password.required' => 'La contraseña es obligatoria al crear el usuario.',
            'password.min' => 'La contraseña debe tener al menos 15 caracteres.',
            'password.max' => 'La contraseña no puede superar los 64 caracteres.',
            'pin.string' => 'El PIN debe ser una cadena de dígitos.',
            'pin.regex' => 'El PIN debe tener exactamente la cantidad de dígitos configurada por el sistema.',
            'global_role_id.exists' => 'El rol global seleccionado no existe.',
            'default_branch_id.exists' => 'La sucursal inicial no existe o está inactiva.',
            'max_discount_percent.numeric' => 'El tope de descuento debe ser numérico.',
            'max_discount_percent.min' => 'El tope de descuento no puede ser negativo.',
            'max_discount_percent.max' => 'El tope de descuento no puede superar 100.',
            'max_return_amount.numeric' => 'El monto máximo de devolución debe ser numérico.',
            'max_return_amount.min' => 'El monto máximo de devolución no puede ser negativo.',
        ]);
    }

    private function userModel(): ?User
    {
        $routeUser = $this->route('user');

        if ($routeUser instanceof User) {
            return $routeUser;
        }

        if (is_numeric($routeUser)) {
            return User::query()->withTrashed()->find((int) $routeUser);
        }

        return null;
    }

    private function emailUniqueRule(?User $user): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $query = User::query()
                ->withTrashed()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($value)]);

            if ($user !== null) {
                $query->whereKeyNot($user->getKey());
            }

            if ($query->exists()) {
                $fail('Ya existe un usuario con ese correo electrónico.');
            }
        };
    }

    private function pinLengthRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }

            $length = (int) (DB::table('system_configs')
                ->where('config_key', 'pin_length')
                ->value('config_value') ?: 4);

            if (! is_string($value) || ! preg_match('/^\d{'.$length.'}$/', $value)) {
                $fail("El PIN debe tener exactamente {$length} dígitos.");
            }
        };
    }
}
