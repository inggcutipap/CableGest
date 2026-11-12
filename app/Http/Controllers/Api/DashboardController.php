<?php

namespace App\Http\Controllers\Api;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $periodo = $request->input('periodo');
        $indicadores = $this->dashboardService->obtenerIndicadores(
            is_string($periodo) && $periodo !== '' ? $periodo : null
        );

        return $this->exito($indicadores, 'Indicadores del dashboard obtenidos correctamente.');
    }
}
