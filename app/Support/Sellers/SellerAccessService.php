<?php

namespace App\Support\Sellers;

use App\Models\Branch;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolución de alcance de vendedores (SEL-01).
 *
 * Las rutas son globales (no existe `{branch}`), por lo que el permiso no se
 * puede evaluar contra la sucursal por defecto. Reglas:
 * - Usuario global: administra todos los vendedores y sucursales.
 * - Usuario por sucursal: solo las sucursales donde alguno de sus roles tiene
 *   `sellers.manage`; nunca basta con tener acceso a la sucursal.
 * - `sellers.view_reports` no concede administración ni visibilidad de PII.
 */
final class SellerAccessService
{
    private const USER_OPTION_LIMIT = 500;

    public function canManageAnyBranch(User $user): bool
    {
        if ($user->isGlobal()) {
            return $this->roleHasPermission((int) $user->global_role_id, 'sellers.manage');
        }

        return DB::table('branch_user as bu')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'bu.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('bu.user_id', $user->getKey())
            ->where('p.code', 'sellers.manage')
            ->exists();
    }

    /**
     * IDs de sucursales activas donde el usuario puede administrar vendedores.
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
            ->where('p.code', 'sellers.manage')
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

    /**
     * Usuarios seleccionables como vínculo. Solo id/name, nunca correo, RUT,
     * contraseña ni otros datos personales.
     *
     * @return list<array{id: int, name: string}>
     */
    public function userOptions(User $user): array
    {
        $query = User::query()
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->select(['id', 'name']);

        $allowed = $this->allowedBranchIds($user);

        if ($allowed !== null) {
            $query->where(function (Builder $builder) use ($allowed): void {
                $builder->whereNotNull('global_role_id');

                if ($allowed !== []) {
                    $builder->orWhereExists(function ($sub) use ($allowed): void {
                        $sub->select(DB::raw('1'))
                            ->from('branch_user')
                            ->whereColumn('branch_user.user_id', 'users.id')
                            ->whereIn('branch_user.branch_id', $allowed);
                    });
                }
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::USER_OPTION_LIMIT)
            ->get()
            ->map(fn (User $option): array => [
                'id' => (int) $option->id,
                'name' => (string) $option->name,
            ])
            ->values()
            ->all();
    }

    public function canAccess(Seller $seller, User $user): bool
    {
        $allowed = $this->allowedBranchIds($user);

        if ($allowed === null) {
            return true;
        }

        if ($allowed === []) {
            return false;
        }

        return DB::table('branch_seller')
            ->where('seller_id', $seller->getKey())
            ->whereIn('branch_id', $allowed)
            ->exists();
    }

    /**
     * Restringe el listado a vendedores con al menos una sucursal permitida.
     *
     * @param  Builder<Seller>  $query
     * @return Builder<Seller>
     */
    public function scopeVisibleSellers(Builder $query, User $user): Builder
    {
        $allowed = $this->allowedBranchIds($user);

        if ($allowed === null) {
            return $query;
        }

        if ($allowed === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('branches', function (Builder $branches) use ($allowed): void {
            $branches->whereIn('branches.id', $allowed);
        });
    }

    /**
     * @return list<array{id: int, code: string, name: string, is_active: bool}>
     */
    public function visibleBranches(Seller $seller, User $user): array
    {
        $query = $seller->branches()
            ->orderBy('branches.name')
            ->orderBy('branches.id');

        $allowed = $this->allowedBranchIds($user);

        if ($allowed !== null) {
            if ($allowed === []) {
                return [];
            }

            $query->whereIn('branches.id', $allowed);
        }

        return $query
            ->get(['branches.id', 'branches.code', 'branches.name', 'branches.is_active'])
            ->map(fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
                'is_active' => (bool) $branch->is_active,
            ])
            ->values()
            ->all();
    }

    public function hasHiddenAssignments(Seller $seller, User $user): bool
    {
        $allowed = $this->allowedBranchIds($user);

        if ($allowed === null) {
            return false;
        }

        $total = $seller->branches()->count();

        if ($allowed === []) {
            return $total > 0;
        }

        $visible = $seller->branches()->whereIn('branches.id', $allowed)->count();

        return $total > $visible;
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
