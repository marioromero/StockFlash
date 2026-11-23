<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vendedor (Parte 2 §6.5 · SEL-01 · SD · PII).
 *
 * El código identifica al vendedor, nunca autentica (RN-14). Se normaliza a
 * mayúsculas y es inmutable después de crear el registro. La fila reservada
 * `NONE` ("Sin asignar") es un agregado para ventas históricas y no debe
 * editarse ni asignarse a sucursales desde la aplicación.
 */
#[Fillable([
    'code', 'name', 'user_id', 'commission_percent',
    'is_active', 'anonymized_at',
])]
class Seller extends Model
{
    use SoftDeletes;

    public const RESERVED_CODE = 'NONE';

    public const RESERVED_NAME = 'Sin asignar';

    protected $table = 'sellers';

    protected $casts = [
        'user_id' => 'integer',
        'commission_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'anonymized_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'branch_seller',
            'seller_id',
            'branch_id'
        );
    }

    public function isReserved(): bool
    {
        return (string) $this->code === self::RESERVED_CODE;
    }
}
