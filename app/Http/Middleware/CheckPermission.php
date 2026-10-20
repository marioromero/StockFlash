<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga un permiso determinado.
 *
 * Reglas (DB-001 / AUTH-001):
 * - Usuario global (global_role_id no nulo): sus permisos vienen del rol global
 *   en `role_permissions`, aplican a todas las sucursales.
 * - Usuario por sucursal: sus permisos vienen del rol asignado en
 *   `branch_user` para la sucursal indicada.
 * - Sin asignación de rol en la sucursal: sin permiso (403).
 *
 * Uso en rutas: Route::middleware(['auth', 'can.permission:users.manage'])
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $branchId = $request->route('branch') ?? $request->input('branch_id') ?? $user->default_branch_id;

        $hasPermission = false;

        if ($user->isGlobal()) {
            // Usuario global: permisos del rol global, aplican en toda la instalación.
            $hasPermission = $user->globalRole()
                ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.code', $permission)
                ->exists();
        } elseif ($branchId !== null) {
            // Usuario por sucursal: permisos del rol asignado en esa sucursal.
            $hasPermission = $user->branchAssignments()
                ->where('branch_id', $branchId)
                ->join('role_permissions', 'role_permissions.role_id', '=', 'branch_user.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.code', $permission)
                ->exists();
        }

        if (! $hasPermission) {
            abort(403, 'No tiene permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
