<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de barras de una variante (Parte 2 §5.5).
 *
 * La cadena es única global. El máximo de un primario por variante se valida
 * en la transacción de aplicación; el esquema no agrega un UNIQUE nuevo.
 */
#[Fillable(['variant_id', 'barcode', 'type', 'is_primary'])]
class ProductBarcode extends Model
{
    protected $table = 'product_barcodes';

    protected $casts = [
        'variant_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
