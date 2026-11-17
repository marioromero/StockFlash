<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tasa de impuesto / exención (Parte 2 §5.1).
 *
 * La columna contractual del porcentaje es `rate` DECIMAL(5,2). Este modelo
 * no calcula impuestos ni siembra IVA19/EXENTO: solo administra los datos.
 */
#[Fillable([
    'code', 'name', 'rate', 'is_exempt', 'is_default', 'fiscal_code', 'is_active',
])]
class TaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $casts = [
        'rate' => 'decimal:2',
        'is_exempt' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'default_tax_rate_id');
    }
}
