<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cobro periodico o pendiente de un contrato.
 */
class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'contrato_id',
        'periodo',
        'monto',
        'fecha_vencimiento',
        'fecha_pago',
        'estado',
        'usuario_cobro_id',
        'metodo_pago',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_vencimiento' => 'date:Y-m-d',
            'fecha_pago' => 'date:Y-m-d',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function usuarioCobro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_cobro_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'pago_id');
    }
}

