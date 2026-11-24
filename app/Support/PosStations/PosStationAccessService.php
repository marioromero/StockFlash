<?php

namespace App\Support\PosStations;

use App\Models\Branch;
use App\Models\PosStation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Alcance de administración de estaciones POS (CSH-02).
 *
 * Las rutas son globales (no existe `{branch}`), por lo que el permiso no se
 * evalúa contra la sucursal por defecto. Reglas:
 * - Usuario global: administra estaciones de todas las sucursales.
 * - Usuario por sucursal: solo sucursales activas donde alguno de sus roles
 *   tiene `settings.manage`; nunca basta con pertenecer a la sucursal.
 * - No existe un permiso independiente de estaciones POS.
 */
final class PosStationAccessService
{
    public function canManageAnyBranch(User $user): bool
    {
        if ($user->isGlobal()) {
            return $this->roleHasPermission((int) $user->global_role_id, 'settings.manage');
        }

        // Un permiso en una sucursal inactiva no habilita la administración:
        // el alcance se compone solo de sucursales activas y no eliminadas.
        return $this->allowedBranchIds($user) !== [];
    }

    /**
     * IDs de sucursales activas donde el usuario puede administrar estaciones.
     * `null` significa alcance global.
     *
     * @return list<int>|null
     */
    public function allowedBranchIds(User $user): ?array
    {
        if ($user->isGlobal()) {
            return null;
        }

        return DB::table('branch_user as bu')
            ->join('branches as b', 'b.id', '=', 'bu.branch_id')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'bu.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('bu.user_id', $user->getKey())
            ->where('p.code', 'settings.manage')
            ->where('b.is_active', 1)
            ->whereNull('b.deleted_at')
            ->select('bu.branch_id')
            ->distinct()
            ->orderBy('bu.branch_id')
            ->pluck('bu.branch_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function branchOptions(User $user): array
    {
        $query = Branch::query()
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->orderBy('id');

        $allowed = $this->allowedBranchIds($user);

        if ($allowed !== null) {
            if ($allowed === []) {
                return [];
            }

            $query->whereIn('id', $allowed);
        }

        return $query
            ->get(['id', 'code', 'name'])
            ->map(fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
            ])
            ->values()
            ->all();
    }

    public function canAccess(PosStation $station, User $user): bool
    {
        $allowed = $this->allowedBranchIds($user);

        if ($allowed === null) {
            return true;
        }

        return in_array((int) $station->branch_id, $allowed, true);
    }

    /**
     * Restringe el listado a estaciones de sucursales permitidas.
     *
     * @param  Builder<PosStation>  $query
     * @return Builder<PosStation>
     */
    public function scopeVisibleStations(Builder $query, User $user): Builder
    {
        $allowed = $this->allowedBranchIds($user);

        if ($allowed === null) {
            return $query;
        }

        if ($allowed === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $allowed);
    }

    private function roleHasPermission(int $roleId, string $permission): bool
    {
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.code', $permission)
            ->exists();
    }
}
