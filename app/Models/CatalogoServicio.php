<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item del catalogo unificado (planes, cargos unicos y equipos).
 */
class CatalogoServicio extends Model
{
    use HasFactory;

    protected $table = 'catalogo_servicios';

    public const TIPOS = [
        'plan_internet',
        'plan_tv',
        'plan_combo',
        'cargo_unico',
        'equipo',
    ];

    public const TIPOS_PLAN = [
        'plan_internet',
        'plan_tv',
        'plan_combo',
    ];

    public const SEGMENTOS = [
        'residencial',
        'comercial',
        'empresarial',
    ];

    protected $fillable = [
        'codigo_interno',
        'nombre',
        'tipo',
        'segmento',
        'velocidad_mbps',
        'precio',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'velocidad_mbps' => 'integer',
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function esPlan(): bool
    {
        return in_array($this->tipo, self::TIPOS_PLAN, true);
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'catalogo_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'catalogo_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'catalogo_id');
    }
}
