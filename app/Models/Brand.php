<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Marca de catálogo (Parte 2 §5.3 · SD).
 */
#[Fillable(['name', 'slug', 'description', 'logo_url', 'is_active'])]
class Brand extends Model
{
    use SoftDeletes;

    protected $table = 'brands';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
