<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categoría jerárquica de catálogo (Parte 2 §5.2 · SD).
 */
#[Fillable([
    'parent_id', 'name', 'slug', 'max_discount_percent', 'default_tax_rate_id',
    'sort_order', 'is_active',
])]
class Category extends Model
{
    use SoftDeletes;

    protected $table = 'categories';

    protected $casts = [
        'parent_id' => 'integer',
        'max_discount_percent' => 'decimal:2',
        'default_tax_rate_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function defaultTaxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_tax_rate_id');
    }
}
