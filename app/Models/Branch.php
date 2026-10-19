<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sucursal (DB-001 Parte 2 §4.6 · SD).
 * store, warehouse u online.
 */
#[Fillable([
    'code', 'name', 'type', 'address', 'commune', 'city', 'region',
    'phone', 'is_active', 'requires_cash_control', 'sales_flow_mode',
    'seller_code_policy', 'allow_negative_stock', 'settings',
])]
class Branch extends Model
{
    use SoftDeletes;

    protected $table = 'branches';

    protected $casts = [
        'is_active' => 'boolean',
        'requires_cash_control' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'settings' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(BranchUser::class, 'branch_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
