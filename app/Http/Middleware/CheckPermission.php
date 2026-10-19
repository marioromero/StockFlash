<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga un permiso determinado en la
 * sucursal actual (o como global).
 *
 * Uso en rutas: Route::middleware(['auth', 'can:users.manage'])
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

        $hasPermission = $user->branchRoles()
            ->wherePivot('branch_id', $branchId)
            ->join('role_permissions', 'role_permissions.role_id', '=', 'branch_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.code', $permission)
            ->exists();

        // Usuario global: verificar contra cualquiera de sus roles globales.
        if (! $hasPermission && $user->isGlobal()) {
            $hasPermission = $user->branchRoles()
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
