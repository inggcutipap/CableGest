<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base para respuestas JSON consistentes en toda la API REST.
 */
abstract class ApiController extends Controller
{
    protected function exito(
        mixed $data = null,
        string $message = 'Operación exitosa.',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $cuerpo = [
            'data' => $data,
            'message' => $message,
        ];

        if ($meta !== []) {
            $cuerpo['meta'] = $meta;
        }

        return response()->json($cuerpo, $status);
    }

    protected function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $cuerpo = [
            'message' => $message,
        ];

        if ($errors !== []) {
            $cuerpo['errors'] = $errors;
        }

        return response()->json($cuerpo, $status);
    }
}

