<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de asignaciones de usuario por sucursal (USERS-001).
 *
 * Cada fila es única por sucursal; el rol debe ser uno de los cuatro roles
 * operativos por sucursal. `central_admin` solo puede ser rol global.
 *
 * `UserRequest` hereda estas reglas y añade los datos del usuario.
 */
class UserBranchAssignmentRequest extends FormRequest
{
    /** Roles que pueden asignarse dentro de una sucursal. */
    public const BRANCH_ROLE_CODES = [
        'seller',
        'cashier',
        'warehouse_keeper',
        'supervisor',
    ];

    public function authorize(): bool
    {
        // La autorización real se aplica en las rutas con `users.manage`.
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => ['sometimes', 'array'],
            'assignments.*.branch_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('is_active', 1)->whereNull('deleted_at')
                ),
            ],
            'assignments.*.role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->whereIn('code', self::BRANCH_ROLE_CODES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'assignments.array' => 'Las asignaciones por sucursal deben ser una lista.',
            'assignments.*.branch_id.required' => 'Cada asignación debe indicar una sucursal.',
            'assignments.*.branch_id.distinct' => 'No se puede asignar la misma sucursal más de una vez.',
            'assignments.*.branch_id.exists' => 'La sucursal no existe o está inactiva.',
            'assignments.*.role_id.required' => 'Cada asignación debe indicar un rol.',
            'assignments.*.role_id.exists' => 'El rol por sucursal no es válido o no puede asignarse en una sucursal.',
        ];
    }
}
