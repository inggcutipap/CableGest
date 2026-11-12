<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado persistido de un conteo fisico de inventario en almacen.
 */
class ConteoInventario extends Model
{
    protected $table = 'conteos_inventario';

    protected $fillable = [
        'usuario_id',
        'total_sistema',
        'total_fisico',
        'coincidentes',
        'verificados',
        'exactitud',
        'discrepancias',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'total_sistema' => 'integer',
            'total_fisico' => 'integer',
            'coincidentes' => 'integer',
            'verificados' => 'integer',
            'exactitud' => 'decimal:2',
            'discrepancias' => 'array',
            'fecha' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

