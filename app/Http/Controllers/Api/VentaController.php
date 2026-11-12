<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AnularVentaRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaController extends ApiController
{
    public function __construct(
        private readonly VentaService $ventaService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $estado = trim((string) $request->input('estado', ''));
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $clienteId = $request->input('cliente_id');
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Venta::query()
            ->with([
                'cliente:id,nombres,dni',
                'usuario:id,nombre',
                'detalles.catalogoServicio:id,codigo_interno,nombre,tipo',
                'detalles.contrato:id,codigo_contrato,venta_detalle_id,estado',
            ])
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id');

        if (in_array($estado, ['registrada', 'anulada'], true)) {
            $consulta->where('estado', $estado);
        }

        if ($clienteId !== null && $clienteId !== '') {
            $consulta->where('cliente_id', (int) $clienteId);
        }

        if ($fechaDesde !== '') {
            $consulta->whereDate('fecha_venta', '>=', $fechaDesde);
        }

        if ($fechaHasta !== '') {
            $consulta->whereDate('fecha_venta', '<=', $fechaHasta);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                if (ctype_digit($buscar)) {
                    $sub->orWhere('id', (int) $buscar);
                }

                $sub->orWhereHas('cliente', function ($q) use ($buscar): void {
                    $q->where('dni', 'like', "%{$buscar}%")
                        ->orWhere('nombres', 'like', "%{$buscar}%");
                });
            });
        }

        $paginado = $consulta->paginate($porPagina);

        $montoMes = (float) Venta::query()
            ->where('estado', 'registrada')
            ->whereYear('fecha_venta', now()->year)
            ->whereMonth('fecha_venta', now()->month)
            ->sum('monto_total');

        return $this->exito(
            $paginado->items(),
            'Listado de ventas obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'total_general' => Venta::count(),
                'registradas' => Venta::where('estado', 'registrada')->count(),
                'anuladas' => Venta::where('estado', 'anulada')->count(),
                'monto_mes' => round($montoMes, 2),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function store(StoreVentaRequest $request): JsonResponse
    {
        $usuarioId = (int) auth('api')->id();
        $venta = $this->ventaService->registrar($request->validated(), $usuarioId);

        return $this->exito($venta, 'Venta registrada correctamente.', 201);
    }

    public function show(Venta $venta): JsonResponse
    {
        $venta->load([
            'cliente',
            'usuario:id,nombre,correo,rol',
            'detalles.catalogoServicio',
            'detalles.contrato.catalogoServicio',
        ]);

        return $this->exito($venta, 'Detalle de venta obtenido correctamente.');
    }

    public function anular(AnularVentaRequest $request, Venta $venta): JsonResponse
    {
        $motivo = $request->validated('motivo_anulacion');
        $actualizada = $this->ventaService->anular($venta, $motivo);

        return $this->exito($actualizada, 'Venta anulada correctamente.');
    }
}
