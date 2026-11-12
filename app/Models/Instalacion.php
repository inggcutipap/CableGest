<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instalacion de un equipo en el domicilio vinculado a un contrato.
 */
class Instalacion extends Model
{
    use HasFactory;

    protected $table = 'instalaciones';

    protected $fillable = [
        'contrato_id',
        'equipo_id',
        'usuario_id',
        'fecha_instalacion',
        'fecha_retiro',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date:Y-m-d',
            'fecha_retiro' => 'date:Y-m-d',
            'activo' => 'boolean',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

