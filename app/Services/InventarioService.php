<?php

namespace App\Services;

use App\Models\CatalogoServicio;
use App\Models\ConteoInventario;
use App\Models\Contrato;
use App\Models\Equipo;
use App\Models\Instalacion;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta entradas, salidas, instalaciones, stock calculado y exactitud de inventario.
 */
class InventarioService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function registrarEntrada(array $datos, int $usuarioId): Equipo
    {
        return DB::transaction(function () use ($datos, $usuarioId): Equipo {
            /** @var CatalogoServicio $item */
            $item = CatalogoServicio::query()->findOrFail((int) $datos['catalogo_id']);

            if ($item->tipo !== 'equipo' || ! $item->activo) {
                throw ValidationException::withMessages([
                    'catalogo_id' => ['Solo se pueden ingresar equipos activos de tipo equipo en el catalogo.'],
                ]);
            }

            $serieNormalizada = mb_strtoupper(trim((string) $datos['serie']));

            $equipo = Equipo::create([
                'catalogo_id' => $item->id,
                'marca' => trim((string) $datos['marca']),
                'serie' => $serieNormalizada,
                'estado' => 'disponible',
            ]);

            MovimientoInventario::create([
                'equipo_id' => $equipo->id,
                'usuario_id' => $usuarioId,
                'tipo_movimiento' => 'entrada',
                'motivo' => trim((string) ($datos['motivo'] ?? 'Ingreso a almacen')),
                'fecha' => now(),
            ]);

            return $equipo->load([
                'catalogoServicio:id,codigo_interno,nombre,tipo,precio',
                'movimientos.usuario:id,nombre',
            ]);
        });
    }

    public function instalarEnContrato(
        Equipo $equipo,
        int $contratoId,
        ?string $fechaInstalacion,
        int $usuarioId
    ): Equipo {
        return DB::transaction(function () use ($equipo, $contratoId, $fechaInstalacion, $usuarioId): Equipo {
            /** @var Equipo $equipoBloqueado */
            $equipoBloqueado = Equipo::query()
                ->whereKey($equipo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($equipoBloqueado->estado !== 'disponible') {
                throw ValidationException::withMessages([
                    'estado' => ['Solo se pueden instalar equipos en estado disponible.'],
                ]);
            }

            /** @var Contrato $contrato */
            $contrato = Contrato::query()->findOrFail($contratoId);
            if ($contrato->estado === 'baja') {
                throw ValidationException::withMessages([
                    'contrato_id' => ['No se puede instalar un equipo en un contrato dado de baja.'],
                ]);
            }

            $fecha = $fechaInstalacion ?: now()->toDateString();

            $equipoBloqueado->update(['estado' => 'instalado']);

            Instalacion::create([
                'contrato_id' => $contrato->id,
                'equipo_id' => $equipoBloqueado->id,
                'usuario_id' => $usuarioId,
                'fecha_instalacion' => $fecha,
                'activo' => true,
            ]);

            $codigoContrato = $contrato->codigo_contrato ?: "C-{$contrato->id}";
            MovimientoInventario::create([
                'equipo_id' => $equipoBloqueado->id,
                'usuario_id' => $usuarioId,
                'tipo_movimiento' => 'salida',
                'motivo' => "Instalacion en contrato {$codigoContrato}",
                'fecha' => now(),
            ]);

            return $equipoBloqueado->fresh([
                'catalogoServicio:id,codigo_interno,nombre,tipo',
                'instalaciones' => static fn ($q) => $q->where('activo', true)->with('contrato.cliente:id,nombres,dni'),
                'movimientos' => static fn ($q) => $q->latest('fecha')->latest('id')->with('usuario:id,nombre'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function registrarSalida(Equipo $equipo, array $datos, int $usuarioId): Equipo
    {
        return DB::transaction(function () use ($equipo, $datos, $usuarioId): Equipo {
            /** @var Equipo $equipoBloqueado */
            $equipoBloqueado = Equipo::query()
                ->whereKey($equipo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($equipoBloqueado->estado === 'baja') {
                throw ValidationException::withMessages([
                    'estado' => ['El equipo ya se encuentra dado de baja.'],
                ]);
            }

            $nuevoEstado = (string) ($datos['estado'] ?? 'baja');

            if ($equipoBloqueado->estado === 'instalado') {
                Instalacion::query()
                    ->where('equipo_id', $equipoBloqueado->id)
                    ->where('activo', true)
                    ->update([
                        'activo' => false,
                        'fecha_retiro' => now()->toDateString(),
                    ]);
            }

            $equipoBloqueado->update(['estado' => $nuevoEstado]);

            MovimientoInventario::create([
                'equipo_id' => $equipoBloqueado->id,
                'usuario_id' => $usuarioId,
                'tipo_movimiento' => 'salida',
                'motivo' => trim((string) $datos['motivo']),
                'fecha' => now(),
            ]);

            return $equipoBloqueado->fresh([
                'catalogoServicio:id,codigo_interno,nombre,tipo',
                'movimientos' => static fn ($q) => $q->latest('fecha')->latest('id')->with('usuario:id,nombre'),
            ]);
        });
    }

    /**
     * Calcula el stock disponible por tipo de equipo sin almacenarlo como columna.
     *
     * @return array<string, mixed>
     */
    public function stockPorTipo(): array
    {
        $items = CatalogoServicio::query()
            ->where('tipo', 'equipo')
            ->where('activo', true)
            ->withCount([
                'equipos as stock_disponible' => static fn ($q) => $q->where('estado', 'disponible'),
                'equipos as instalados' => static fn ($q) => $q->where('estado', 'instalado'),
                'equipos as en_reparacion' => static fn ($q) => $q->where('estado', 'en_reparacion'),
                'equipos as baja' => static fn ($q) => $q->where('estado', 'baja'),
                'equipos as total_unidades',
            ])
            ->orderBy('nombre')
            ->get(['id', 'codigo_interno', 'nombre', 'precio', 'activo']);

        return [
            'items' => $items,
            'resumen' => [
                'total_tipos' => $items->count(),
                'total_disponible' => (int) $items->sum('stock_disponible'),
                'total_instalado' => (int) $items->sum('instalados'),
                'total_en_reparacion' => (int) $items->sum('en_reparacion'),
            ],
        ];
    }

    /**
     * Calcula la exactitud del inventario (coincidentes / verificados * 100) y persiste el conteo.
     *
     * @param array<int, string> $seriesBrutas
     */
    public function calcularExactitud(array $seriesBrutas, int $usuarioId): ConteoInventario
    {
        $seriesFisicas = array_values(array_unique(array_filter(array_map(
            static fn (mixed $serie): string => mb_strtoupper(trim((string) $serie)),
            $seriesBrutas
        ), static fn (string $serie): bool => $serie !== '')));

        $seriesSistema = Equipo::query()
            ->where('estado', 'disponible')
            ->pluck('serie')
            ->map(static fn (string $serie): string => mb_strtoupper(trim($serie)))
            ->unique()
            ->values()
            ->all();

        $coincidentesLista = array_values(array_intersect($seriesSistema, $seriesFisicas));
        $faltantesLista = array_values(array_diff($seriesSistema, $seriesFisicas));
        $sobrantesLista = array_values(array_diff($seriesFisicas, $seriesSistema));

        $universo = array_values(array_unique(array_merge($seriesSistema, $seriesFisicas)));

        $totalSistema = count($seriesSistema);
        $totalFisico = count($seriesFisicas);
        $coincidentes = count($coincidentesLista);
        $verificados = count($universo);

        $exactitud = $verificados > 0
            ? round(($coincidentes / $verificados) * 100, 2)
            : 100.00;

        $equiposDetalle = Equipo::query()
            ->whereIn('serie', array_merge($faltantesLista, $sobrantesLista))
            ->with('catalogoServicio:id,nombre')
            ->get()
            ->keyBy(static fn (Equipo $equipo): string => mb_strtoupper($equipo->serie));

        $faltantes = array_map(static function (string $serie) use ($equiposDetalle): array {
            /** @var Equipo|null $eq */
            $eq = $equiposDetalle->get($serie);

            return [
                'serie' => $serie,
                'tipo' => 'faltante_en_fisico',
                'equipo' => $eq?->catalogoServicio?->nombre,
                'marca' => $eq?->marca,
            ];
        }, $faltantesLista);

        $sobrantes = array_map(static function (string $serie) use ($equiposDetalle): array {
            /** @var Equipo|null $eq */
            $eq = $equiposDetalle->get($serie);

            return [
                'serie' => $serie,
                'tipo' => $eq ? 'estado_distinto_' . $eq->estado : 'no_registrada_en_sistema',
                'estado_sistema' => $eq?->estado,
                'equipo' => $eq?->catalogoServicio?->nombre,
            ];
        }, $sobrantesLista);

        return ConteoInventario::create([
            'usuario_id' => $usuarioId,
            'total_sistema' => $totalSistema,
            'total_fisico' => $totalFisico,
            'coincidentes' => $coincidentes,
            'verificados' => $verificados,
            'exactitud' => number_format($exactitud, 2, '.', ''),
            'discrepancias' => [
                'faltantes' => $faltantes,
                'sobrantes' => $sobrantes,
            ],
            'fecha' => now(),
        ])->load('usuario:id,nombre');
    }
}
