<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Atributo de catálogo (Parte 2 §5.5). Los valores viven en `attribute_values`.
 * Sin soft delete.
 */
#[Fillable(['name'])]
class Attribute extends Model
{
    protected $table = 'attributes';

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id');
    }
}
