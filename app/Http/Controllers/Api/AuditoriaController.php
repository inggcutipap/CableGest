<?php

namespace App\Http\Controllers\Api;

use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tabla = trim((string) $request->input('tabla_afectada', ''));
        $accion = trim((string) $request->input('accion', ''));
        $usuarioId = $request->input('usuario_id');
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 20)));

        $consulta = Auditoria::query()
            ->with('usuario:id,nombre,correo,rol')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($tabla !== '') {
            $consulta->where('tabla_afectada', $tabla);
        }

        if (in_array($accion, ['crear', 'actualizar', 'anular'], true)) {
            $consulta->where('accion', $accion);
        }

        if ($usuarioId !== null && $usuarioId !== '') {
            $consulta->where('usuario_id', (int) $usuarioId);
        }

        if ($fechaDesde !== '') {
            $consulta->whereDate('fecha', '>=', $fechaDesde);
        }

        if ($fechaHasta !== '') {
            $consulta->whereDate('fecha', '<=', $fechaHasta);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                if (ctype_digit($buscar)) {
                    $sub->orWhere('registro_id', (int) $buscar);
                }
                $sub->orWhere('tabla_afectada', 'like', "%{$buscar}%")
                    ->orWhereHas('usuario', static fn ($q) => $q->where('nombre', 'like', "%{$buscar}%"));
            });
        }

        $paginado = $consulta->paginate($porPagina);

        return $this->exito(
            $paginado->items(),
            'Bitacora de auditoria obtenida correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'tablas' => Auditoria::query()->distinct()->orderBy('tabla_afectada')->pluck('tabla_afectada')->values(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }
}
