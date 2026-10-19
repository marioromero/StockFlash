<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rol de sistema (DB-001 Parte 2 §4.7).
 * 5 roles de sistema: seller, cashier, warehouse_keeper, supervisor, central_admin.
 */
#[Fillable([
    'code', 'name', 'authority_level', 'max_discount_percent',
    'max_return_amount', 'description', 'is_system',
])]
class Role extends Model
{
    protected $table = 'roles';

    protected $casts = [
        'authority_level' => 'integer',
        'max_discount_percent' => 'decimal:2',
        'max_return_amount' => 'decimal:2',
        'is_system' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'branch_user',
            'role_id',
            'user_id'
        )->withPivot('branch_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', 1);
    }
}
