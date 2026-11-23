<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Sellers\SellerAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza `sellers.manage` en rutas globales sin `{branch}`.
 *
 * `CheckPermission` evalúa solo el rol global o la sucursal por defecto. Para
 * vendedores el permiso puede estar en cualquier sucursal asignada, por lo que
 * este guard fail-closed:
 * - usuario global: exige `sellers.manage` en su rol global;
 * - usuario por sucursal: exige al menos un rol de `branch_user` con
 *   `sellers.manage`.
 *
 * `sellers.view_reports` no concede acceso. El alcance fino de sucursales se
 * resuelve después en `SellerAccessService`.
 */
class EnsureSellerManager
{
    public function __construct(private readonly SellerAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $this->access->canManageAnyBranch($user)) {
            abort(403, 'No tiene permiso para administrar vendedores.');
        }

        return $next($request);
    }
}
