<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de vendedor a sucursal (Parte 2 §6.5).
 * PK compuesta (seller_id, branch_id), sin id ni timestamps.
 */
#[Fillable([
    'seller_id', 'branch_id',
])]
class BranchSeller extends Model
{
    protected $table = 'branch_seller';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'seller_id' => 'integer',
        'branch_id' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
