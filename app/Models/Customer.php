<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente persona o empresa (Parte 2 §6.2 · CUS-01/03 · PII).
 *
 * El RUT solo vive cifrado (`fiscal_id_encrypted`) y su índice ciego
 * (`fiscal_id_hash`). Ambos se ocultan de cualquier serialización accidental.
 * `deleted_at` existe por la convención SD, pero los endpoints no borran:
 * desactivan con `is_active`.
 */
#[Fillable([
    'type', 'name', 'trade_name', 'fiscal_id_encrypted', 'fiscal_id_hash',
    'email', 'phone', 'business_activity', 'fiscal_address', 'fiscal_commune',
    'fiscal_city', 'customer_group_id', 'default_discount_percent',
    'max_discount_percent', 'marketing_opt_in', 'origin', 'last_activity_at',
    'is_active', 'blocked_at', 'anonymized_at',
])]
#[Hidden(['fiscal_id_encrypted', 'fiscal_id_hash'])]
class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    protected $casts = [
        'customer_group_id' => 'integer',
        'default_discount_percent' => 'decimal:2',
        'max_discount_percent' => 'decimal:2',
        'marketing_opt_in' => 'boolean',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
        'blocked_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }
}
