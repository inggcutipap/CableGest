<?php

namespace App\Http\Controllers\Api;

use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $rol = trim((string) $request->input('rol', ''));
        $soloActivos = $request->boolean('activos', true);

        $consulta = Usuario::query()
            ->select(['id', 'nombre', 'correo', 'rol', 'activo'])
            ->orderBy('nombre');

        if ($soloActivos) {
            $consulta->where('activo', true);
        }

        if ($rol !== '') {
            $consulta->where('rol', $rol);
        }

        return $this->exito($consulta->get(), 'Usuarios obtenidos correctamente.');
    }
}
