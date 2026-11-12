<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $termino = trim((string) $request->input('buscar', $request->input('q', '')));
        $porPagina = max(1, min(100, (int) $request->input('per_page', 15)));

        $consulta = Cliente::query()->orderByDesc('id');

        if ($termino !== '') {
            $consulta->where(function ($sub) use ($termino): void {
                $sub->where('dni', 'like', "%{$termino}%")
                    ->orWhere('nombres', 'like', "%{$termino}%");
            });
        }

        $paginado = $consulta->paginate($porPagina);

        $registradosMes = Cliente::query()
            ->whereYear('fecha_registro', now()->year)
            ->whereMonth('fecha_registro', now()->month)
            ->count();

        return $this->exito(
            $paginado->items(),
            'Listado de clientes obtenido correctamente.',
            200,
            [
                'total' => $paginado->total(),
                'total_general' => Cliente::count(),
                'registrados_mes' => $registradosMes,
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
            ]
        );
    }

    public function store(StoreClienteRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['fecha_registro'] = $datos['fecha_registro'] ?? now()->toDateString();

        $cliente = Cliente::create($datos);

        return $this->exito($cliente, 'Cliente registrado correctamente.', 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return $this->exito($cliente, 'Cliente obtenido correctamente.');
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated());

        return $this->exito($cliente->fresh(), 'Cliente actualizado correctamente.');
    }
}

