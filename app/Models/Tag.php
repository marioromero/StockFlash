<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Etiqueta de catálogo (Parte 2 §5.4). Sin soft delete.
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    protected $table = 'tags';
}
