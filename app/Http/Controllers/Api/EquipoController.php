<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ExactitudInventarioRequest;
use App\Http\Requests\InstalarEquipoRequest;
use App\Http\Requests\SalidaEquipoRequest;
use App\Http\Requests\StoreEquipoRequest;
use App\Models\ConteoInventario;
use App\Models\Equipo;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipoController extends ApiController
{
    public function __construct(
        private readonly InventarioService $inventarioService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $estado = trim((string) $request->input('estado', ''));
        $catalogoId = $request->input('catalogo_id');
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Equipo::query()
            ->with([
                'catalogoServicio:id,codigo_interno,nombre,tipo,precio',
                'instalaciones' => static fn ($q) => $q->where('activo', true)
                    ->with([
                        'contrato:id,codigo_contrato,cliente_id,estado',
                        'contrato.cliente:id,nombres,dni',
                    ]),
            ])
            ->orderByRaw("FIELD(estado, 'disponible', 'instalado', 'en_reparacion', 'baja')")
            ->orderByDesc('id');

        if (in_array($estado, ['disponible', 'instalado', 'en_reparacion', 'baja'], true)) {
            $consulta->where('estado', $estado);
        }

        if ($catalogoId !== null && $catalogoId !== '') {
            $consulta->where('catalogo_id', (int) $catalogoId);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                $sub->where('serie', 'like', "%{$buscar}%")
                    ->orWhere('marca', 'like', "%{$buscar}%")
                    ->orWhereHas('catalogoServicio', function ($q) use ($buscar): void {
                        $q->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('codigo_interno', 'like', "%{$buscar}%");
                    });
            });
        }

        $paginado = $consulta->paginate($porPagina);

        return $this->exito(
            $paginado->items(),
            'Listado de equipos obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'disponibles' => Equipo::where('estado', 'disponible')->count(),
                'instalados' => Equipo::where('estado', 'instalado')->count(),
                'en_reparacion' => Equipo::where('estado', 'en_reparacion')->count(),
                'baja' => Equipo::where('estado', 'baja')->count(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function store(StoreEquipoRequest $request): JsonResponse
    {
        $usuarioId = (int) auth('api')->id();
        $equipo = $this->inventarioService->registrarEntrada($request->validated(), $usuarioId);

        return $this->exito($equipo, 'Entrada de equipo registrada correctamente.', 201);
    }

    public function show(Equipo $equipo): JsonResponse
    {
        $equipo->load([
            'catalogoServicio:id,codigo_interno,nombre,tipo,precio',
            'instalaciones' => static fn ($q) => $q->orderByDesc('id')
                ->with([
                    'contrato:id,codigo_contrato,cliente_id,estado',
                    'contrato.cliente:id,nombres,dni',
                    'usuario:id,nombre',
                ]),
            'movimientos' => static fn ($q) => $q->orderByDesc('fecha')
                ->orderByDesc('id')
                ->with('usuario:id,nombre'),
        ]);

        return $this->exito($equipo, 'Detalle del equipo obtenido correctamente.');
    }

    public function instalar(InstalarEquipoRequest $request, Equipo $equipo): JsonResponse
    {
        $datos = $request->validated();
        $usuarioId = (int) auth('api')->id();

        $actualizado = $this->inventarioService->instalarEnContrato(
            $equipo,
            (int) $datos['contrato_id'],
            $datos['fecha_instalacion'] ?? null,
            $usuarioId
        );

        return $this->exito($actualizado, 'Equipo instalado correctamente en el contrato.');
    }

    public function salida(SalidaEquipoRequest $request, Equipo $equipo): JsonResponse
    {
        $usuarioId = (int) auth('api')->id();
        $actualizado = $this->inventarioService->registrarSalida($equipo, $request->validated(), $usuarioId);

        return $this->exito($actualizado, 'Salida de equipo registrada correctamente.');
    }

    public function stock(): JsonResponse
    {
        $resultado = $this->inventarioService->stockPorTipo();

        return $this->exito(
            $resultado['items'],
            'Stock de equipos calculado correctamente.',
            200,
            $resultado['resumen']
        );
    }

    public function exactitud(ExactitudInventarioRequest $request): JsonResponse
    {
        $usuarioId = (int) auth('api')->id();
        $conteo = $this->inventarioService->calcularExactitud(
            $request->validated('series'),
            $usuarioId
        );

        return $this->exito($conteo, 'Conteo fisico y exactitud calculados correctamente.', 201);
    }

    public function conteos(): JsonResponse
    {
        $conteos = ConteoInventario::query()
            ->with('usuario:id,nombre')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return $this->exito($conteos, 'Historial de conteos fisicos obtenido correctamente.');
    }
}
