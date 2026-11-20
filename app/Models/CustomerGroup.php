<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Grupo de clientes (Parte 2 §6.1 · CUS-02).
 *
 * `deleted_at` existe por la convención SD, pero los endpoints no borran
 * filas: solo alternan `is_active`. El código es único y el porcentaje
 * máximo puede ser nulo (sin tope propio).
 */
#[Fillable(['code', 'name', 'default_discount_percent', 'max_discount_percent', 'is_active'])]
class CustomerGroup extends Model
{
    use SoftDeletes;

    protected $table = 'customer_groups';

    protected $casts = [
        'default_discount_percent' => 'decimal:2',
        'max_discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'customer_group_id');
    }
}
