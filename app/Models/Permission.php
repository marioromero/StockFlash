<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permiso de sistema (DB-001 Parte 2 §4.8).
 * Ejemplo: sales.void, catalog.manage, cash.open_close.
 */
#[Fillable([
    'code', 'module_code', 'description',
])]
class Permission extends Model
{
    protected $table = 'permissions';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}
