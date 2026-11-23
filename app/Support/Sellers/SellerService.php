<?php

namespace App\Support\Sellers;

use App\Models\Branch;
use App\Models\Seller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso transaccionales de vendedores (SEL-01 · RN-13/RN-14).
 *
 * Cada escritura (fila `sellers`, pivote `branch_seller` y auditoría) ocurre
 * en una sola transacción con `SellerWriteGuard`. La auditoría registra solo
 * ID, estado, IDs de sucursal y nombres de campos modificados: nunca nombre,
 * código, correo ni otros datos personales.
 */
final class SellerService
{
    private const MODULE = 'sellers';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SellerWriteGuard $guard,
        private readonly SellerAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Seller
    {
        $this->assertManager($actor);

        $code = SellerCode::normalize((string) ($data['code'] ?? ''));

        if ($code === Seller::RESERVED_CODE) {
            throw ValidationException::withMessages([
                'code' => 'El código NONE está reservado para el vendedor «Sin asignar».',
            ]);
        }

        if (! SellerCode::isValid($code)) {
            throw ValidationException::withMessages([
                'code' => 'El código debe tener entre 1 y 20 caracteres y usar solo letras, números, punto, guion o guion bajo.',
            ]);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $branchIds = $this->branchIds($data['branches'] ?? []);
        $userId = $this->nullableId($data['user_id'] ?? null);
        $commission = SellerCommission::canonical($this->commissionInput($data));

        try {
            return DB::transaction(function () use ($code, $name, $branchIds, $userId, $commission, $actor): Seller {
                $this->guard->assertWritable();
                $this->assertBranchSet($actor, $branchIds, requireAtLeastOne: true);
                $this->assertUserAssignable($userId, $branchIds);
                $this->assertCodeAvailable($code);

                $seller = Seller::create([
                    'code' => $code,
                    'name' => $name,
                    'user_id' => $userId,
                    'commission_percent' => $commission,
                    'is_active' => true,
                    'anonymized_at' => null,
                ]);

                $this->replaceBranches($seller, $branchIds);

                $this->auditLogger->record(
                    'sellers.created',
                    'sellers',
                    (int) $seller->id,
                    null,
                    null,
                    [
                        'id' => (int) $seller->id,
                        'is_active' => true,
                        'branch_ids' => $branchIds,
                        'fields' => ['name', 'user_id', 'commission_percent', 'branches'],
                    ],
                    self::MODULE,
                );

                return $seller;
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe un vendedor con ese código.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Seller $seller, array $data): Seller
    {
        $this->assertManager($actor);
        $this->assertMutable($seller);

        $existing = $this->existingBranchIds($seller);

        try {
            return DB::transaction(function () use ($actor, $seller, $data, $existing): Seller {
                $this->guard->assertWritable();
                $this->assertMutable($seller);

                $submitted = array_key_exists('branches', $data)
                    ? $this->branchIds($data['branches'])
                    : null;

                if ($submitted !== null) {
                    $this->assertBranchSet($actor, $submitted, requireAtLeastOne: false);
                }

                $allowed = $this->access->allowedBranchIds($actor);

                if ($submitted === null) {
                    $final = $existing;
                } elseif ($allowed === null) {
                    $final = $submitted;
                } else {
                    // Conserva asignaciones fuera del alcance del administrador;
                    // solo se pueden agregar o quitar sucursales permitidas.
                    $unauthorizedExisting = array_values(array_diff($existing, $allowed));
                    $final = $this->sortIds([...$unauthorizedExisting, ...$submitted]);
                }

                if ($final === []) {
                    throw ValidationException::withMessages([
                        'branches' => 'El vendedor debe mantener al menos una sucursal asignada.',
                    ]);
                }

                $userId = array_key_exists('user_id', $data)
                    ? $this->nullableId($data['user_id'])
                    : ($seller->user_id === null ? null : (int) $seller->user_id);

                $this->assertUserAssignable($userId, $final);

                $name = array_key_exists('name', $data)
                    ? trim((string) $data['name'])
                    : (string) $seller->name;

                $commission = array_key_exists('commission_percent', $data)
                    ? SellerCommission::canonical($this->commissionInput($data))
                    : SellerCommission::canonical((string) $seller->commission_percent);

                $currentCommission = SellerCommission::canonical((string) $seller->commission_percent);
                $currentUserId = $seller->user_id === null ? null : (int) $seller->user_id;
                $existingSorted = $this->sortIds($existing);

                $fields = [];

                if ($name !== (string) $seller->name) {
                    $fields[] = 'name';
                }

                if ($userId !== $currentUserId) {
                    $fields[] = 'user_id';
                }

                if ($commission !== $currentCommission) {
                    $fields[] = 'commission_percent';
                }

                if ($final !== $existingSorted) {
                    $fields[] = 'branches';
                }

                if ($fields === []) {
                    return $seller;
                }

                $seller->name = $name;
                $seller->user_id = $userId;
                $seller->commission_percent = $commission;
                $seller->save();

                if ($final !== $existingSorted) {
                    $this->replaceBranches($seller, $final);
                }

                $this->auditLogger->record(
                    'sellers.updated',
                    'sellers',
                    (int) $seller->id,
                    null,
                    null,
                    [
                        'id' => (int) $seller->id,
                        'is_active' => (bool) $seller->is_active,
                        'branch_ids' => $final,
                        'fields' => $fields,
                    ],
                    self::MODULE,
                );

                if ($final !== $existingSorted) {
                    $this->auditLogger->record(
                        'sellers.branches_updated',
                        'sellers',
                        (int) $seller->id,
                        null,
                        null,
                        [
                            'id' => (int) $seller->id,
                            'is_active' => (bool) $seller->is_active,
                            'branch_ids' => $final,
                            'fields' => ['branches'],
                        ],
                        self::MODULE,
                    );
                }

                return $seller;
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'seller' => 'No fue posible actualizar el vendedor.',
            ]);
        }
    }

    public function changeStatus(User $actor, Seller $seller, bool $active): Seller
    {
        $this->assertManager($actor);
        $this->assertMutable($seller);

        return DB::transaction(function () use ($seller, $active): Seller {
            $this->guard->assertWritable();
            $this->assertMutable($seller);

            if ((bool) $seller->is_active === $active) {
                return $seller;
            }

            $seller->is_active = $active;
            $seller->save();

            $this->auditLogger->record(
                $active ? 'sellers.activated' : 'sellers.deactivated',
                'sellers',
                (int) $seller->id,
                null,
                null,
                [
                    'id' => (int) $seller->id,
                    'is_active' => $active,
                ],
                self::MODULE,
            );

            return $seller;
        });
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function assertBranchSet(User $actor, array $branchIds, bool $requireAtLeastOne): void
    {
        if ($branchIds === []) {
            if ($requireAtLeastOne) {
                throw ValidationException::withMessages([
                    'branches' => 'Seleccione al menos una sucursal activa.',
                ]);
            }

            return;
        }

        $allowed = $this->access->allowedBranchIds($actor);

        if ($allowed !== null && array_diff($branchIds, $allowed) !== []) {
            throw ValidationException::withMessages([
                'branches' => 'Solo puede asignar sucursales donde tiene permiso para administrar vendedores.',
            ]);
        }

        $count = Branch::query()
            ->whereIn('id', $branchIds)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->count();

        if ($count !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branches' => 'Todas las sucursales asignadas deben existir y estar activas.',
            ]);
        }
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function assertUserAssignable(?int $userId, array $branchIds): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()
            ->whereKey($userId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'user_id' => 'El usuario vinculado debe existir y estar activo.',
            ]);
        }

        if ($user->isGlobal()) {
            return;
        }

        $accessCount = $user->branchAssignments()
            ->whereIn('branch_id', $branchIds)
            ->distinct()
            ->count('branch_id');

        if ($accessCount !== count($branchIds)) {
            throw ValidationException::withMessages([
                'user_id' => 'El usuario vinculado debe tener acceso a todas las sucursales asignadas al vendedor.',
            ]);
        }
    }

    private function assertCodeAvailable(string $code): void
    {
        if (Seller::withTrashed()->whereRaw('UPPER(code) = ?', [$code])->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe un vendedor con ese código.',
            ]);
        }
    }

    private function assertManager(User $actor): void
    {
        if (! $this->access->canManageAnyBranch($actor)) {
            abort(403, 'No tiene permiso para administrar vendedores.');
        }
    }

    private function assertMutable(Seller $seller): void
    {
        if ($seller->isReserved()) {
            throw ValidationException::withMessages([
                'seller' => 'El vendedor reservado «Sin asignar» no se puede modificar.',
            ]);
        }

        if ($seller->anonymized_at !== null) {
            throw ValidationException::withMessages([
                'seller' => 'El vendedor está anonimizado y no admite cambios ni cambios de estado.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function existingBranchIds(Seller $seller): array
    {
        return $this->sortIds(
            $seller->branches()
                ->pluck('branches.id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function replaceBranches(Seller $seller, array $branchIds): void
    {
        DB::table('branch_seller')
            ->where('seller_id', $seller->getKey())
            ->whereNotIn('branch_id', $branchIds)
            ->delete();

        foreach ($branchIds as $branchId) {
            DB::table('branch_seller')->insertOrIgnore([
                'seller_id' => (int) $seller->getKey(),
                'branch_id' => $branchId,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function branchIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $branchId) {
            if (is_int($branchId) || (is_string($branchId) && ctype_digit($branchId))) {
                $ids[] = (int) $branchId;
            }
        }

        return $this->sortIds($ids);
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function commissionInput(array $data): ?string
    {
        return array_key_exists('commission_percent', $data)
            ? (string) ($data['commission_percent'] ?? '')
            : null;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sortIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
