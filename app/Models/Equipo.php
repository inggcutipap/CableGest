<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidad fisica serializada de equipo de red o television.
 */
class Equipo extends Model
{
    use HasFactory;

    protected $table = 'equipos';

    protected $fillable = [
        'catalogo_id',
        'marca',
        'serie',
        'estado',
    ];

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class, 'catalogo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'equipo_id');
    }

    public function instalaciones(): HasMany
    {
        return $this->hasMany(Instalacion::class, 'equipo_id');
    }
}

