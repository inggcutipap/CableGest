<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:api')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show']);
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update']);

    Route::get('/catalogo', [CatalogoController::class, 'index']);
    Route::post('/catalogo', [CatalogoController::class, 'store']);
    Route::get('/catalogo/{catalogo}', [CatalogoController::class, 'show']);
    Route::put('/catalogo/{catalogo}', [CatalogoController::class, 'update']);
    Route::patch('/catalogo/{catalogo}/desactivar', [CatalogoController::class, 'desactivar']);

    Route::get('/ventas', [VentaController::class, 'index']);
    Route::post('/ventas', [VentaController::class, 'store']);
    Route::get('/ventas/{venta}', [VentaController::class, 'show']);
    Route::patch('/ventas/{venta}/anular', [VentaController::class, 'anular']);

    Route::get('/contratos', [ContratoController::class, 'index']);
    Route::get('/contratos/{contrato}', [ContratoController::class, 'show']);
    Route::patch('/contratos/{contrato}/estado', [ContratoController::class, 'actualizarEstado']);
});

