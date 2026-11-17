<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Variante vendible de un producto (Parte 2 §5.5).
 *
 * `cost_price` es dato protegido: se expone y edita solo con
 * `catalog.view_cost`. `last_cost` no es editable desde esta pantalla.
 */
#[Fillable([
    'product_id', 'sku', 'price', 'cost_price', 'unit', 'sold_by_weight',
    'max_discount_percent', 'min_price', 'allow_price_override', 'tax_rate_id',
    'combo_deducts_components', 'is_active',
])]
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $table = 'product_variants';

    protected $casts = [
        'product_id' => 'integer',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:4',
        'last_cost' => 'decimal:4',
        'max_discount_percent' => 'decimal:2',
        'min_price' => 'decimal:2',
        'tax_rate_id' => 'integer',
        'sold_by_weight' => 'boolean',
        'allow_price_override' => 'boolean',
        'combo_deducts_components' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'variant_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attribute_values',
            'variant_id',
            'attribute_value_id',
        );
    }
}
