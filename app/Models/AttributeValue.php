<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor de atributo (Parte 2 §5.5). Único por atributo. Sin soft delete.
 */
#[Fillable(['attribute_id', 'value'])]
class AttributeValue extends Model
{
    protected $table = 'attribute_values';

    protected $casts = [
        'attribute_id' => 'integer',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }
}
