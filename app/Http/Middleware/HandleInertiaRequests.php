<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => $this->sharedUser($request->user()),
                'permissions' => fn () => $this->permissionCodes($request->user()),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sharedUser(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_global' => $user->isGlobal(),
            'default_branch_id' => $user->default_branch_id,
        ];
    }

    /**
     * Códigos de permiso efectivos del usuario para la interfaz.
     * La interfaz solo oculta accesos; la autorización real es server-side.
     *
     * Debe usar el mismo ámbito que `CheckPermission::allows`: rol global para
     * usuarios globales o el rol de la sucursal por defecto para usuarios por
     * sucursal. No se agregan asignaciones de otras sucursales porque esas
     * rutas responderían 403 y el enlace no debe mostrarse.
     *
     * @return list<string>
     */
    private function permissionCodes(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($user->isGlobal()) {
            $role = $user->globalRole()->first();

            return $role === null
                ? []
                : $role->permissions()->pluck('permissions.code')->unique()->values()->all();
        }

        $branchId = $user->default_branch_id;

        if ($branchId === null) {
            return [];
        }

        $roleIds = $user->branchAssignments()
            ->where('branch_id', $branchId)
            ->pluck('role_id')
            ->unique()
            ->values()
            ->all();

        if ($roleIds === []) {
            return [];
        }

        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }
}
