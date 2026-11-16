<?php

namespace App\Support;

use App\Models\User;
use App\Support\License\LicenseReadOnlyRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guardas transaccionales de administración de usuarios (USERS-001).
 *
 * - Límite de plan: `license_states.max_users` cuenta solo usuarios ACTIVOS y
 *   no eliminados. La fila de licencia se bloquea con `lockForUpdate()`.
 *   Sin fila de licencia se bloquean las altas/activaciones; con fila,
 *   `max_users = 0` significa ilimitado. Licencia suspendida fuera de gracia
 *   queda en solo lectura (RN-70).
 * - Último administrador: se bloquean las filas de usuarios activos antes de
 *   contar y actualizar. Se protege la desactivación, la eliminación, el cambio
 *   de rol global y el reemplazo de asignaciones que quiten `users.manage`.
 *
 * Todos los métodos deben invocarse DENTRO de la transacción de la operación.
 */
class UserAccessManager
{
    /**
     * Comprueba licencia y cupo para crear o activar un usuario.
     *
     * El usuario a activar debe estar todavía inactivo, por lo que el conteo
     * de activos no lo incluye.
     */
    public function assertCanCreateOrActivate(): void
    {
        $license = $this->lockLicenseRow();

        if ($license === null) {
            throw ValidationException::withMessages([
                'license' => 'No hay una licencia registrada. No es posible crear ni activar usuarios hasta registrar la licencia.',
            ]);
        }

        $this->assertLicenseAllowsWrites($license);

        $maxUsers = (int) $license->max_users;

        if ($maxUsers <= 0) {
            return;
        }

        $activeUsers = DB::table('users')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->count();

        if ($activeUsers >= $maxUsers) {
            throw ValidationException::withMessages([
                'max_users' => "El plan permite un máximo de {$maxUsers} usuarios activos.",
            ]);
        }
    }

    /**
     * Bloquea las filas de usuarios activos y rechaza desactivar/eliminar al
     * último administrador activo. La auto-desactivación y la auto-eliminación
     * se rechazan siempre con 403.
     */
    public function assertCanDeactivateOrDelete(User $target, User $actor): void
    {
        if ((int) $target->getKey() === (int) $actor->getKey()) {
            abort(403, 'No puede desactivar ni eliminar su propia cuenta.');
        }

        $this->lockActiveUsers();

        if (! $target->is_active) {
            return;
        }

        if ($this->hasUsersManage($target) && ! $this->otherActiveAdminExists($target)) {
            throw ValidationException::withMessages([
                'user' => 'No es posible desactivar o eliminar al último usuario activo con permiso para administrar usuarios.',
            ]);
        }
    }

    /**
     * Protege el cambio de rol global, la conversión global↔sucursal y el
     * reemplazo de asignaciones cuando el objetivo perdería `users.manage`.
     *
     * `$willHaveUsersManage` debe calcularse con el estado PROPUESTO (rol global
     * y asignaciones que se van a guardar). La auto-modificación se permite
     * solo si otro usuario activo conserva `users.manage`.
     */
    public function assertAccessChangeKeepsAdmin(
        User $target,
        User $actor,
        bool $willHaveUsersManage,
        string $errorKey = 'access',
    ): void {
        $this->lockActiveUsers();

        if (! $target->is_active || $willHaveUsersManage) {
            return;
        }

        if (! $this->hasUsersManage($target)) {
            return;
        }

        if (! $this->otherActiveAdminExists($target)) {
            throw ValidationException::withMessages([
                $errorKey => 'El cambio dejaría la instalación sin un usuario activo con permiso para administrar usuarios.',
            ]);
        }
    }

    /**
     * Calcula si el estado propuesto conserva `users.manage`.
     *
     * @param  array<int, array{branch_id: int|string, role_id: int|string}>  $assignments
     */
    public function accessProvidesUsersManage(?int $globalRoleId, array $assignments): bool
    {
        if ($globalRoleId !== null) {
            return $this->roleHasPermission($globalRoleId, 'users.manage');
        }

        foreach ($assignments as $assignment) {
            $roleId = is_array($assignment)
                ? (int) ($assignment['role_id'] ?? 0)
                : (int) $assignment;

            if ($roleId > 0 && $this->roleHasPermission($roleId, 'users.manage')) {
                return true;
            }
        }

        return false;
    }

    public function hasUsersManage(User $user): bool
    {
        if ($user->global_role_id !== null) {
            return $this->roleHasPermission((int) $user->global_role_id, 'users.manage');
        }

        return DB::table('branch_user')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'branch_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('branch_user.user_id', $user->getKey())
            ->where('permissions.code', 'users.manage')
            ->exists();
    }

    /**
     * Bloquea las filas de usuarios activos no eliminados. Serializa las
     * escrituras administrativas que podrían degradar al último administrador.
     */
    public function lockActiveUsers(): void
    {
        DB::table('users')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->get();
    }

    private function otherActiveAdminExists(User $exclude): bool
    {
        return DB::table('users as u')
            ->where('u.id', '!=', $exclude->getKey())
            ->where('u.is_active', 1)
            ->whereNull('u.deleted_at')
            ->where(function ($query): void {
                $query->whereExists(function ($sub): void {
                    $sub->select(DB::raw('1'))
                        ->from('role_permissions as rp')
                        ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                        ->whereColumn('rp.role_id', 'u.global_role_id')
                        ->where('p.code', 'users.manage');
                })->orWhereExists(function ($sub): void {
                    $sub->select(DB::raw('1'))
                        ->from('branch_user as bu')
                        ->join('role_permissions as rp', 'rp.role_id', '=', 'bu.role_id')
                        ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                        ->whereColumn('bu.user_id', 'u.id')
                        ->where('p.code', 'users.manage');
                });
            })
            ->exists();
    }

    private function roleHasPermission(int $roleId, string $permission): bool
    {
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.code', $permission)
            ->exists();
    }

    private function lockLicenseRow(): ?object
    {
        return DB::table('license_states')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function assertLicenseAllowsWrites(object $license): void
    {
        if ($license->status !== 'suspended') {
            return;
        }

        if ($license->grace_until === null) {
            throw ValidationException::withMessages([
                'license' => 'La licencia está suspendida y el sistema no permite nuevas altas ni activaciones.',
            ]);
        }

        if (LicenseReadOnlyRule::isReadOnly($license)) {
            throw ValidationException::withMessages([
                'license' => 'La licencia está suspendida fuera del período de gracia; el sistema está en modo solo lectura.',
            ]);
        }
    }
}
