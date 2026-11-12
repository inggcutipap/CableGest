<?php

namespace App\Http\Controllers\Api;

use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $estado = trim((string) $request->input('estado', ''));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Notificacion::query()
            ->with([
                'pago:id,contrato_id,periodo,monto,fecha_vencimiento,estado',
                'pago.contrato:id,codigo_contrato,cliente_id',
                'pago.contrato.cliente:id,nombres,dni',
            ])
            ->orderByRaw("FIELD(estado, 'pendiente', 'vista')")
            ->orderByDesc('fecha_generacion')
            ->orderByDesc('id');

        if (in_array($estado, ['pendiente', 'vista'], true)) {
            $consulta->where('estado', $estado);
        }

        $paginado = $consulta->paginate($porPagina);

        return $this->exito(
            $paginado->items(),
            'Listado de notificaciones obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'pendientes' => Notificacion::where('estado', 'pendiente')->count(),
                'vistas' => Notificacion::where('estado', 'vista')->count(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function marcarVista(Notificacion $notificacion): JsonResponse
    {
        if ($notificacion->estado !== 'vista') {
            $notificacion->update(['estado' => 'vista']);
        }

        return $this->exito(
            $notificacion->fresh([
                'pago:id,contrato_id,periodo,monto,fecha_vencimiento,estado',
                'pago.contrato:id,codigo_contrato,cliente_id',
                'pago.contrato.cliente:id,nombres,dni',
            ]),
            'Notificación marcada como vista.'
        );
    }
}
