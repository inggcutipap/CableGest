<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contrato de servicio recurrente asociado a un cliente y plan.
 */
class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    protected $fillable = [
        'codigo_contrato',
        'cliente_id',
        'catalogo_id',
        'venta_detalle_id',
        'fecha_inicio',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class, 'catalogo_id');
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'contrato_id');
    }

    public function instalaciones(): HasMany
    {
        return $this->hasMany(Instalacion::class, 'contrato_id');
    }

    public function reclamos(): HasMany
    {
        return $this->hasMany(Reclamo::class, 'contrato_id');
    }
}

