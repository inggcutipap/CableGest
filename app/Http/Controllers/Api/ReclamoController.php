<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AsignarReclamoRequest;
use App\Http\Requests\DiagnosticoReclamoRequest;
use App\Http\Requests\StoreReclamoRequest;
use App\Models\Reclamo;
use App\Services\ReclamoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReclamoController extends ApiController
{
    public function __construct(
        private readonly ReclamoService $reclamoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $estado = trim((string) $request->input('estado', ''));
        $diagnostico = trim((string) $request->input('diagnostico', ''));
        $tecnicoId = $request->input('tecnico_id');
        $clienteId = $request->input('cliente_id');
        $buscar = trim((string) $request->input('buscar', $request->input('q', '')));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Reclamo::query()
            ->with([
                'cliente:id,nombres,dni,telefono,direccion',
                'contrato:id,codigo_contrato,estado',
                'usuarioRegistro:id,nombre',
                'tecnico:id,nombre,rol',
            ])
            ->orderByRaw("FIELD(estado, 'abierto', 'en_proceso', 'resuelto')")
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id');

        if (in_array($estado, ['abierto', 'en_proceso', 'resuelto'], true)) {
            $consulta->where('estado', $estado);
        }

        if (in_array($diagnostico, ['pendiente', 'falla_confirmada', 'sin_falla'], true)) {
            $consulta->where('diagnostico', $diagnostico);
        }

        if ($tecnicoId !== null && $tecnicoId !== '') {
            $consulta->where('tecnico_id', (int) $tecnicoId);
        }

        if ($clienteId !== null && $clienteId !== '') {
            $consulta->where('cliente_id', (int) $clienteId);
        }

        if ($buscar !== '') {
            $consulta->where(function ($sub) use ($buscar): void {
                $sub->where('motivo', 'like', "%{$buscar}%")
                    ->orWhere('observaciones', 'like', "%{$buscar}%")
                    ->orWhereHas('cliente', function ($q) use ($buscar): void {
                        $q->where('nombres', 'like', "%{$buscar}%")
                            ->orWhere('dni', 'like', "%{$buscar}%");
                    })
                    ->orWhereHas('contrato', static fn ($q) => $q->where('codigo_contrato', 'like', "%{$buscar}%"));
            });
        }

        $paginado = $consulta->paginate($porPagina);

        return $this->exito(
            $paginado->items(),
            'Listado de reclamos obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'abiertos' => Reclamo::where('estado', 'abierto')->count(),
                'en_proceso' => Reclamo::where('estado', 'en_proceso')->count(),
                'resueltos' => Reclamo::where('estado', 'resuelto')->count(),
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function store(StoreReclamoRequest $request): JsonResponse
    {
        $usuarioId = (int) auth('api')->id();
        $reclamo = $this->reclamoService->registrar($request->validated(), $usuarioId);

        return $this->exito($reclamo, 'Reclamo registrado correctamente.', 201);
    }

    public function show(Reclamo $reclamo): JsonResponse
    {
        $reclamo->load([
            'cliente:id,nombres,dni,telefono,direccion',
            'contrato:id,codigo_contrato,estado',
            'usuarioRegistro:id,nombre',
            'tecnico:id,nombre,rol',
        ]);

        return $this->exito($reclamo, 'Detalle del reclamo obtenido correctamente.');
    }

    public function asignar(AsignarReclamoRequest $request, Reclamo $reclamo): JsonResponse
    {
        $actualizado = $this->reclamoService->asignarTecnico(
            $reclamo,
            (int) $request->validated('tecnico_id')
        );

        return $this->exito($actualizado, 'Tecnico asignado al reclamo correctamente.');
    }

    public function diagnostico(DiagnosticoReclamoRequest $request, Reclamo $reclamo): JsonResponse
    {
        $actualizado = $this->reclamoService->registrarDiagnostico($reclamo, $request->validated());

        return $this->exito($actualizado, 'Diagnostico registrado y reclamo resuelto.');
    }
}
