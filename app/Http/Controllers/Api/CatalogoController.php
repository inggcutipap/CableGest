<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCatalogoRequest;
use App\Http\Requests\UpdateCatalogoRequest;
use App\Models\CatalogoServicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tipo = trim((string) $request->input('tipo', ''));
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $activo = $request->input('activo');

        $consulta = CatalogoServicio::query()->orderBy('codigo_interno');

        if ($tipo === 'planes') {
            $consulta->whereIn('tipo', CatalogoServicio::TIPOS_PLAN);
        } elseif ($tipo !== '' && in_array($tipo, CatalogoServicio::TIPOS, true)) {
            $consulta->where('tipo', $tipo);
        }

        if ($activo !== null && $activo !== '') {
            $consulta->where('activo', filter_var($activo, FILTER_VALIDATE_BOOLEAN));
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                $sub->where('codigo_interno', 'like', "%{$buscar}%")
                    ->orWhere('nombre', 'like', "%{$buscar}%");
            });
        }

        $items = $consulta->get();

        $resumen = [
            'total' => CatalogoServicio::count(),
            'activos' => CatalogoServicio::where('activo', true)->count(),
            'inactivos' => CatalogoServicio::where('activo', false)->count(),
            'planes_recurrentes' => CatalogoServicio::whereIn('tipo', CatalogoServicio::TIPOS_PLAN)->count(),
            'cargos_unicos' => CatalogoServicio::where('tipo', 'cargo_unico')->count(),
            'equipos' => CatalogoServicio::where('tipo', 'equipo')->count(),
        ];

        return $this->exito(
            $items,
            'Catálogo obtenido correctamente.',
            200,
            $resumen
        );
    }

    public function store(StoreCatalogoRequest $request): JsonResponse
    {
        $datos = $this->normalizarCamposPlan($request->validated());
        $datos['activo'] = $datos['activo'] ?? true;

        $item = CatalogoServicio::create($datos);

        return $this->exito($item, 'Ítem del catálogo registrado correctamente.', 201);
    }

    public function show(CatalogoServicio $catalogo): JsonResponse
    {
        return $this->exito($catalogo, 'Ítem del catálogo obtenido correctamente.');
    }

    public function update(UpdateCatalogoRequest $request, CatalogoServicio $catalogo): JsonResponse
    {
        $datos = $this->normalizarCamposPlan($request->validated());
        $catalogo->update($datos);

        return $this->exito($catalogo->fresh(), 'Ítem del catálogo actualizado correctamente.');
    }

    public function desactivar(CatalogoServicio $catalogo): JsonResponse
    {
        if ($catalogo->activo) {
            $catalogo->update(['activo' => false]);
        }

        return $this->exito(
            $catalogo->fresh(),
            'Ítem del catálogo desactivado correctamente.'
        );
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function normalizarCamposPlan(array $datos): array
    {
        if (! in_array($datos['tipo'], CatalogoServicio::TIPOS_PLAN, true)) {
            $datos['segmento'] = null;
            $datos['velocidad_mbps'] = null;
        }

        return $datos;
    }
}

