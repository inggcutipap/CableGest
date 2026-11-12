<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RegistrarPagoRequest;
use App\Http\Requests\StorePagoRequest;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Services\CobranzaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PagoController extends ApiController
{
    public function __construct(
        private readonly CobranzaService $cobranzaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $estado = trim((string) $request->input('estado', ''));
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $contratoId = $request->input('contrato_id');
        $clienteId = $request->input('cliente_id');
        $periodo = trim((string) $request->input('periodo', ''));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Pago::query()
            ->with([
                'contrato:id,codigo_contrato,cliente_id,catalogo_id,estado',
                'contrato.cliente:id,nombres,dni',
                'contrato.catalogoServicio:id,codigo_interno,nombre',
                'usuarioCobro:id,nombre',
            ])
            ->orderByRaw("FIELD(estado, 'vencido', 'pendiente', 'pagado', 'anulado')")
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('id');

        if (in_array($estado, ['pendiente', 'pagado', 'vencido', 'anulado'], true)) {
            $consulta->where('estado', $estado);
        }

        if ($contratoId !== null && $contratoId !== '') {
            $consulta->where('contrato_id', (int) $contratoId);
        }

        if ($clienteId !== null && $clienteId !== '') {
            $consulta->whereHas('contrato', static fn ($q) => $q->where('cliente_id', (int) $clienteId));
        }

        if ($periodo !== '') {
            $consulta->where('periodo', $periodo);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                $sub->where('observaciones', 'like', "%{$buscar}%")
                    ->orWhereHas('contrato', function ($q) use ($buscar): void {
                        $q->where('codigo_contrato', 'like', "%{$buscar}%")
                            ->orWhereHas('cliente', function ($cq) use ($buscar): void {
                                $cq->where('dni', 'like', "%{$buscar}%")
                                    ->orWhere('nombres', 'like', "%{$buscar}%");
                            });
                    });
            });
        }

        $paginado = $consulta->paginate($porPagina);

        $recaudadoMes = (float) Pago::query()
            ->where('estado', 'pagado')
            ->whereYear('fecha_pago', now()->year)
            ->whereMonth('fecha_pago', now()->month)
            ->sum('monto');

        return $this->exito(
            $paginado->items(),
            'Listado de pagos obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'pendientes' => Pago::where('estado', 'pendiente')->count(),
                'vencidos' => Pago::where('estado', 'vencido')->count(),
                'pagados' => Pago::where('estado', 'pagado')->count(),
                'recaudado_mes' => round($recaudadoMes, 2),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function store(StorePagoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $periodo = $datos['periodo'] ?? null;

        if ($periodo !== null) {
            $duplicado = Pago::query()
                ->where('contrato_id', (int) $datos['contrato_id'])
                ->where('periodo', $periodo)
                ->exists();

            if ($duplicado) {
                throw ValidationException::withMessages([
                    'periodo' => ["Ya existe un cobro registrado para este contrato en el periodo {$periodo}."],
                ]);
            }
        }

        $fechaVencimiento = (string) $datos['fecha_vencimiento'];
        $estado = $fechaVencimiento < now()->toDateString() ? 'vencido' : 'pendiente';

        $pago = Pago::create([
            'contrato_id' => (int) $datos['contrato_id'],
            'periodo' => $periodo,
            'monto' => number_format(round((float) $datos['monto'], 2), 2, '.', ''),
            'fecha_vencimiento' => $fechaVencimiento,
            'estado' => $estado,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);

        return $this->exito(
            $pago->load(['contrato.cliente:id,nombres,dni', 'contrato.catalogoServicio:id,nombre']),
            'Cobro registrado correctamente.',
            201
        );
    }

    public function registrar(RegistrarPagoRequest $request, Pago $pago): JsonResponse
    {
        $usuarioId = (int) auth('api')->id();
        $actualizado = $this->cobranzaService->registrarPago($pago, $request->validated(), $usuarioId);

        return $this->exito($actualizado, 'Pago registrado correctamente.');
    }

    public function estadoCuenta(Cliente $cliente): JsonResponse
    {
        $datos = $this->cobranzaService->estadoCuentaCliente($cliente);

        return $this->exito($datos, 'Estado de cuenta obtenido correctamente.');
    }

    public function morosidad(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $estadoContrato = trim((string) $request->input('estado_contrato', ''));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Contrato::query()
            ->whereHas('pagos', static fn ($q) => $q->where('estado', 'vencido'))
            ->with([
                'cliente:id,nombres,dni,telefono,direccion',
                'catalogoServicio:id,codigo_interno,nombre',
            ])
            ->withCount([
                'pagos as pagos_vencidos_count' => static fn ($q) => $q->where('estado', 'vencido'),
            ])
            ->withSum([
                'pagos as monto_adeudado' => static fn ($q) => $q->where('estado', 'vencido'),
            ], 'monto')
            ->withMin([
                'pagos as vencimiento_mas_antiguo' => static fn ($q) => $q->where('estado', 'vencido'),
            ], 'fecha_vencimiento')
            ->orderBy('vencimiento_mas_antiguo');

        if (in_array($estadoContrato, ['activo', 'suspendido', 'baja'], true)) {
            $consulta->where('estado', $estadoContrato);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                $sub->where('codigo_contrato', 'like', "%{$buscar}%")
                    ->orWhereHas('cliente', function ($q) use ($buscar): void {
                        $q->where('dni', 'like', "%{$buscar}%")
                            ->orWhere('nombres', 'like', "%{$buscar}%");
                    });
            });
        }

        $paginado = $consulta->paginate($porPagina);
        $hoy = Carbon::today();

        $filas = collect($paginado->items())->map(static function (Contrato $contrato) use ($hoy): array {
            $fechaAntigua = $contrato->vencimiento_mas_antiguo
                ? Carbon::parse((string) $contrato->vencimiento_mas_antiguo)
                : $hoy;

            return [
                'contrato_id' => $contrato->id,
                'codigo_contrato' => $contrato->codigo_contrato ?? "C-{$contrato->id}",
                'estado_contrato' => $contrato->estado,
                'cliente' => $contrato->cliente,
                'plan' => $contrato->catalogoServicio,
                'pagos_vencidos_count' => (int) $contrato->pagos_vencidos_count,
                'monto_adeudado' => round((float) $contrato->monto_adeudado, 2),
                'vencimiento_mas_antiguo' => $fechaAntigua->toDateString(),
                'dias_vencidos' => max(0, (int) $fechaAntigua->diffInDays($hoy)),
            ];
        })->values();

        $montoTotalAdeudado = (float) Pago::query()->where('estado', 'vencido')->sum('monto');
        $totalContratosMorosos = Contrato::query()
            ->whereHas('pagos', static fn ($q) => $q->where('estado', 'vencido'))
            ->count();

        return $this->exito(
            $filas,
            'Reporte de morosidad obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'total_contratos_morosos' => $totalContratosMorosos,
                'monto_total_adeudado' => round($montoTotalAdeudado, 2),
                'total_pagos_vencidos' => Pago::where('estado', 'vencido')->count(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }
}
