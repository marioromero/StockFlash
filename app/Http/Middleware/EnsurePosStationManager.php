<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PosStations\PosStationAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza la administración de estaciones POS en rutas globales sin `{branch}`.
 *
 * `CheckPermission` evalúa solo el rol global o la sucursal por defecto. Las
 * estaciones POS usan `settings.manage` en cualquier sucursal asignada, por lo
 * que este guard fail-closed exige:
 * - usuario global: `settings.manage` en su rol global;
 * - usuario por sucursal: al menos un rol de `branch_user` con `settings.manage`.
 *
 * El alcance fino se resuelve después en `PosStationAccessService`.
 */
class EnsurePosStationManager
{
    public function __construct(private readonly PosStationAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $this->access->canManageAnyBranch($user)) {
            abort(403, 'No tiene permiso para administrar estaciones POS.');
        }

        return $next($request);
    }
}
