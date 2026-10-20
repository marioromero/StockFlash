<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que el usuario autenticado tenga acceso a la sucursal indicada.
 *
 * Regla de negocio (DB-001 / AUTH-001):
 * - Usuario global (global_role_id no nulo): acceso a todas las sucursales.
 * - Usuario por sucursal: solo a las sucursales donde tiene fila en branch_user.
 * - Sin asignación: sin acceso a ninguna sucursal (excepto dashboard global).
 *
 * El parámetro de ruta puede ser el código de sucursal (ej. 'SUC-A') o su ID numérico;
 * se resuelve al ID antes de consultar branch_user.
 */
class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next, ?int $branchId = null): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Usuario global: acceso a todas las sucursales.
        if ($user->isGlobal()) {
            return $next($request);
        }

        // Resolver identificador de sucursal: ruta, request o default del usuario.
        $branchId = $request->route('branch') ?? $request->input('branch_id') ?? $user->default_branch_id;

        if ($branchId === null) {
            return $next($request);
        }

        // Si el parámetro no es numérico, asumir que es el código de sucursal.
        if (! is_numeric($branchId)) {
            $sucursal = \App\Models\Branch::where('code', $branchId)->first();
            if ($sucursal === null) {
                abort(403, 'Sucursal no encontrada.');
            }
            $branchId = $sucursal->id;
        }

        $hasAccess = $user->branchAssignments()->where('branch_id', $branchId)->exists();

        if (! $hasAccess) {
            abort(403, 'No tiene acceso a esta sucursal.');
        }

        return $next($request);
    }
}
