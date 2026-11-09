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
 * - La sucursal solicitada debe existir (no estar eliminada lógicamente) y
 *   estar activa (is_active=1) para cualquier usuario, incluido el global.
 * - Usuario global (global_role_id no nulo): acceso a todas las sucursales
 *   existentes y activas.
 * - Usuario por sucursal: solo a las sucursales donde tiene fila en branch_user.
 * - Sin asignación: sin acceso a ninguna sucursal (excepto dashboard global).
 *
 * El parámetro de ruta puede ser el código de sucursal (ej. 'SUC-A') o su ID numérico;
 * se resuelve al ID antes de consultar branch_user.
 */
class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next, ?string $branchId = null): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Resolver identificador de sucursal: ruta, parámetro del middleware,
        // request o default del usuario. Sin identificador no hay acceso.
        $identifier = $request->route('branch')
            ?? $branchId
            ?? $request->input('branch_id')
            ?? $user->default_branch_id;

        if ($identifier === null || $identifier === '') {
            abort(403, 'Sucursal no especificada.');
        }

        // Un identificador numérico se resuelve primero por ID y, si no existe,
        // por código: los códigos de sucursal pueden ser puramente numéricos.
        $sucursal = null;

        if (is_numeric($identifier)) {
            $sucursal = \App\Models\Branch::whereKey($identifier)->first();
        }

        if ($sucursal === null) {
            $sucursal = \App\Models\Branch::where('code', $identifier)->first();
        }

        if ($sucursal === null) {
            abort(403, 'Sucursal no encontrada.');
        }

        // La sucursal debe estar activa incluso para usuarios globales.
        if (! $sucursal->is_active) {
            abort(403, 'Sucursal desactivada.');
        }

        // La sucursal debe ser válida incluso para usuarios globales.
        if ($user->isGlobal()) {
            return $next($request);
        }

        $branchId = $sucursal->id;

        $hasAccess = $user->branchAssignments()->where('branch_id', $branchId)->exists();

        if (! $hasAccess) {
            abort(403, 'No tiene acceso a esta sucursal.');
        }

        return $next($request);
    }
}
