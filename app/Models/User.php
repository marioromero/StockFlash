<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario de StockFlash (DB-001 Parte 2 §4.9).
 *
 * Esquema aprobado: tablas `users`, `roles`, `permissions`, `role_permissions`,
 * `branch_user`, `branches`. Sin paquete externo de auth; RBAC propio.
 *
 * - `global_role_id` no nulo = usuario global (rol aplica en todas las sucursales).
 *   Nulo = usuario por sucursal; acceso solo a través de filas en `branch_user`.
 * - `is_active = 0` impide iniciar sesión e invalida la sesión vigente.
 * - `locked_until` con fecha futura bloquea el login (tras 5 intentos fallidos).
 * - `must_change_password = 1` obliga a cambiar contraseña en el próximo ingreso.
 *
 * PII: nombre, correo, última IP de acceso. Hash de contraseña y PIN nunca se
 * exponen ni se registran.
 */
#[Fillable([
    'name', 'email', 'password', 'remember_token',
    'global_role_id', 'default_branch_id', 'pin_hash',
    'pin_failed_attempts', 'pin_locked_until', 'mfa_enabled',
    'mfa_secret_encrypted', 'mfa_confirmed_at',
    'max_discount_percent', 'max_return_amount',
    'password_changed_at',
    'is_active', 'failed_login_attempts', 'locked_until',
    'must_change_password', 'last_login_at', 'last_login_ip',
])]
#[Hidden(['password', 'remember_token', 'pin_hash', 'mfa_secret_encrypted'])]
class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    protected $hidden = [
        'password', 'remember_token', 'pin_hash', 'mfa_secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pin_failed_attempts' => 'integer',
            'mfa_enabled' => 'boolean',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Rol global del usuario (solo aplica si no es nulo).
     */
    public function globalRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'global_role_id');
    }

    /**
     * Sucursal por defecto del usuario.
     */
    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    /**
     * Asignaciones de usuario a sucursales (rol por sucursal).
     */
    public function branchAssignments(): HasMany
    {
        return $this->hasMany(BranchUser::class, 'user_id');
    }

    /**
     * Roles del usuario por cada sucursal (a través de branch_user).
     */
    public function branchRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'branch_user',
            'user_id',
            'role_id'
        )->withPivot('branch_id');
    }

    /**
     * Indica si el usuario es global (ve todas las sucursales).
     */
    public function isGlobal(): bool
    {
        return (bool) $this->global_role_id;
    }

    /**
     * Indica si la cuenta está activa y puede iniciar sesión.
     */
    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active && !$this->isLocked();
    }

    /**
     * Indica si la cuenta está bloqueada temporalmente por intentos fallidos.
     */
    public function isLocked(): bool
    {
        return $this->locked_until instanceof \DateTimeInterface
            && $this->locked_until > now();
    }

    /**
     * Indica si debe cambiar la contraseña en el próximo ingreso.
     */
    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    /**
     * Registra un intento fallido de login y bloquea tras el umbral configurado.
     */
    public function incrementFailedLogin(): void
    {
        $this->increment('failed_login_attempts');
        $maxAttempts = (int) config('system_configs.login_max_attempts', 5);
        if ($this->failed_login_attempts >= $maxAttempts) {
            $this->locked_until = now()->addMinutes(30);
        }
        $this->save();
    }

    /**
     * Reinicia los intentos fallidos tras un login exitoso.
     */
    public function clearFailedLogin(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    /**
     * Registra el último acceso exitoso.
     */
    public function recordLogin(string $ip): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->clearFailedLogin();
        $this->save();
    }
}
