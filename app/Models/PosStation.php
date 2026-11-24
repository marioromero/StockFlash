<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Estación POS (Parte 2 §4.13 · CSH-02 / POS-02 · SD).
 *
 * El código identifica la estación, nunca autentica. `device_token_hash`
 * guarda únicamente el SHA-256 hexadecimal del token de dispositivo: el token
 * en claro jamás se persiste ni se serializa. `last_seen_at` permanece nulo
 * hasta que una futura ola POS defina un evento explícito de actividad.
 */
#[Fillable([
    'branch_id', 'code', 'name', 'printer_profile',
    'is_active', 'last_seen_at',
])]
#[Hidden(['device_token_hash'])]
class PosStation extends Model
{
    use SoftDeletes;

    /** Perfiles de impresora aprobados por el contrato. */
    public const PRINTER_PROFILES = ['thermal58', 'thermal80', 'a4'];

    protected $table = 'pos_stations';

    protected $casts = [
        'branch_id' => 'integer',
        'printer_profile' => 'string',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Indica si el navegador de esta estación tiene un token vigente.
     * Solo se expone el booleano; nunca el hash ni el token.
     */
    public function hasBoundDevice(): bool
    {
        return $this->getAttribute('device_token_hash') !== null;
    }
}
