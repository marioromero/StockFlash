<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Producto del catálogo P0 (Parte 2 §5.4).
 *
 * No administra inventario, stock, ventas ni composición de combos: solo los
 * datos contractuales del producto y sus relaciones de catálogo.
 */
#[Fillable([
    'category_id', 'brand_id', 'type', 'name', 'description', 'internal_code',
    'image_url', 'tax_rate_id', 'tracks_stock', 'is_sellable', 'is_active', 'origin',
])]
class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $casts = [
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'tax_rate_id' => 'integer',
        'tracks_stock' => 'boolean',
        'is_sellable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag', 'product_id', 'tag_id');
    }
}
