<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateContratoEstadoRequest;
use App\Models\Contrato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContratoController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $estado = trim((string) $request->input('estado', ''));
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $clienteId = $request->input('cliente_id');
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Contrato::query()
            ->with([
                'cliente:id,nombres,dni,direccion,telefono',
                'catalogoServicio:id,codigo_interno,nombre,tipo,precio',
                'ventaDetalle:id,venta_id',
            ])
            ->orderByDesc('id');

        if (in_array($estado, ['activo', 'suspendido', 'baja'], true)) {
            $consulta->where('estado', $estado);
        }

        if ($clienteId !== null && $clienteId !== '') {
            $consulta->where('cliente_id', (int) $clienteId);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                $sub->where('codigo_contrato', 'like', "%{$buscar}%")
                    ->orWhereHas('cliente', function ($q) use ($buscar): void {
                        $q->where('dni', 'like', "%{$buscar}%")
                            ->orWhere('nombres', 'like', "%{$buscar}%");
                    })
                    ->orWhereHas('catalogoServicio', function ($q) use ($buscar): void {
                        $q->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('codigo_interno', 'like', "%{$buscar}%");
                    });
            });
        }

        $paginado = $consulta->paginate($porPagina);

        return $this->exito(
            $paginado->items(),
            'Listado de contratos obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'total_general' => Contrato::count(),
                'activos' => Contrato::where('estado', 'activo')->count(),
                'suspendidos' => Contrato::where('estado', 'suspendido')->count(),
                'baja' => Contrato::where('estado', 'baja')->count(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function show(Contrato $contrato): JsonResponse
    {
        $contrato->load([
            'cliente',
            'catalogoServicio',
            'ventaDetalle.venta:id,fecha_venta,monto_total,estado',
        ])->loadCount('pagos');

        return $this->exito($contrato, 'Contrato obtenido correctamente.');
    }

    public function actualizarEstado(UpdateContratoEstadoRequest $request, Contrato $contrato): JsonResponse
    {
        $nuevoEstado = (string) $request->validated('estado');

        $contrato->loadMissing('ventaDetalle.venta');

        if (
            $nuevoEstado !== 'baja'
            && $contrato->ventaDetalle?->venta?->estado === 'anulada'
        ) {
            throw ValidationException::withMessages([
                'estado' => ['No se puede reactivar un contrato cuya venta de origen fue anulada.'],
            ]);
        }

        if ($contrato->estado !== $nuevoEstado) {
            $contrato->update(['estado' => $nuevoEstado]);
        }

        return $this->exito(
            $contrato->fresh([
                'cliente:id,nombres,dni,direccion,telefono',
                'catalogoServicio:id,codigo_interno,nombre,tipo,precio',
                'ventaDetalle:id,venta_id',
            ]),
            'Estado del contrato actualizado correctamente.'
        );
    }
}
