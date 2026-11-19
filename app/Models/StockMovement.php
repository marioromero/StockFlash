<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Movimiento fisico del kardex (Parte 2 §7.4).
 *
 * Inmutable a nivel de aplicacion: se crea y luego no se actualiza ni elimina.
 * La correccion contractual es otro movimiento `reversal` que referencia al
 * original. Las escrituras del ledger usan el query builder dentro de la
 * transaccion de StockLedger; este modelo bloquea los caminos Eloquent de
 * update/delete. La base de datos no impone triggers (Parte 2 §17.2); la
 * inmutabilidad es, por tanto, una garantia de aplicacion documentada, no una
 * promesa de enforcement fisico del motor.
 */
#[Fillable([
    'branch_id', 'variant_id', 'type', 'quantity', 'balance_after', 'unit_cost',
    'source_type', 'source_id', 'reservation_id', 'reverses_movement_id',
    'user_id', 'reason_code_id', 'note', 'occurred_at',
])]
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    public $timestamps = false;

    protected $casts = [
        'branch_id' => 'integer',
        'variant_id' => 'integer',
        'source_id' => 'integer',
        'reservation_id' => 'integer',
        'reverses_movement_id' => 'integer',
        'user_id' => 'integer',
        'reason_code_id' => 'integer',
        'quantity' => 'string',
        'balance_after' => 'string',
        'unit_cost' => 'string',
        'occurred_at' => 'datetime',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Los movimientos de inventario son inmutables: no se pueden modificar.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Los movimientos de inventario son inmutables: use un movimiento de reversa.');
    }

    public function delete()
    {
        throw new LogicException('Los movimientos de inventario son inmutables: no se pueden eliminar.');
    }

    public function forceDelete()
    {
        throw new LogicException('Los movimientos de inventario son inmutables: no se pueden eliminar.');
    }
}
