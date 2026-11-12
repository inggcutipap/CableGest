<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoginRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciales = [
            'correo' => mb_strtolower(trim((string) $request->validated('correo'))),
            'password' => (string) $request->validated('password'),
            'activo' => true,
        ];

        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->attempt($credenciales);

        if (! $token) {
            return $this->error('Credenciales inválidas o usuario inactivo.', 401);
        }

        /** @var Usuario $usuario */
        $usuario = $guard->user();

        return $this->exito([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'usuario' => $this->formatearUsuario($usuario),
        ], 'Inicio de sesión exitoso.');
    }

    public function me(): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();

        return $this->exito(
            $this->formatearUsuario($usuario),
            'Usuario autenticado.'
        );
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->exito(null, 'Sesión cerrada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearUsuario(Usuario $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol,
        ];
    }
}

