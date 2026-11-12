<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reclamo tecnico o comercial registrado para un cliente.
 */
class Reclamo extends Model
{
    use HasFactory;

    protected $table = 'reclamos';

    protected $fillable = [
        'cliente_id',
        'contrato_id',
        'usuario_registro_id',
        'tecnico_id',
        'motivo',
        'diagnostico',
        'estado',
        'fecha_registro',
        'fecha_atencion',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'date:Y-m-d',
            'fecha_atencion' => 'date:Y-m-d',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_registro_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }
}

