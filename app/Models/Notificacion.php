<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta generada por vencimiento de pago sin registro de cobro.
 */
class Notificacion extends Model
{
    public $timestamps = false;

    protected $table = 'notificaciones';

    protected $fillable = [
        'pago_id',
        'tipo',
        'mensaje',
        'estado',
        'fecha_generacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_generacion' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }
}

