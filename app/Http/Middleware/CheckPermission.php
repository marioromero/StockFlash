<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
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

        if (! self::allows($user, $permission, $request->route('branch'))) {
            abort(403, 'No tiene permiso para realizar esta acción.');
        }

        return $next($request);
    }

    /**
     * Permiso efectivo del usuario en el ámbito solicitado.
     *
     * El ámbito SOLO puede provenir de la ruta (`{branch}`) o de la sucursal
     * por defecto del usuario. Nunca se lee `branch_id` desde query/body:
     * un parámetro de request no puede elevar ni cambiar el ámbito de
     * autorización.
     */
    public static function allows(User $user, string $permission, mixed $routeBranch = null): bool
    {
        // `branch` puede llegar como modelo por Route Model Binding; se
        // normaliza a su clave para consultar asignaciones por sucursal.
        if ($routeBranch instanceof Model) {
            $routeBranch = $routeBranch->getKey();
        }

        if ($user->isGlobal()) {
            // Usuario global: permisos del rol global, aplican en toda la instalación.
            return $user->globalRole()
                ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.code', $permission)
                ->exists();
        }

        $branchId = $routeBranch ?? $user->default_branch_id;

        if ($branchId === null) {
            return false;
        }

        // Usuario por sucursal: permisos del rol asignado en esa sucursal.
        return $user->branchAssignments()
            ->where('branch_id', $branchId)
            ->join('role_permissions', 'role_permissions.role_id', '=', 'branch_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.code', $permission)
            ->exists();
    }
}
