<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ReclamoController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:api')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/usuarios', [UsuarioController::class, 'index']);

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

    Route::get('/pagos', [PagoController::class, 'index']);
    Route::post('/pagos', [PagoController::class, 'store']);
    Route::post('/pagos/{pago}/registrar', [PagoController::class, 'registrar']);
    Route::get('/clientes/{cliente}/estado-cuenta', [PagoController::class, 'estadoCuenta']);
    Route::get('/reportes/morosidad', [PagoController::class, 'morosidad']);

    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::patch('/notificaciones/{notificacion}/vista', [NotificacionController::class, 'marcarVista']);

    Route::get('/equipos', [EquipoController::class, 'index']);
    Route::post('/equipos', [EquipoController::class, 'store']);
    Route::get('/equipos/{equipo}', [EquipoController::class, 'show']);
    Route::post('/equipos/{equipo}/instalar', [EquipoController::class, 'instalar']);
    Route::post('/equipos/{equipo}/salida', [EquipoController::class, 'salida']);
    Route::get('/inventario/stock', [EquipoController::class, 'stock']);
    Route::post('/inventario/exactitud', [EquipoController::class, 'exactitud']);
    Route::get('/inventario/conteos', [EquipoController::class, 'conteos']);

    Route::get('/reclamos', [ReclamoController::class, 'index']);
    Route::post('/reclamos', [ReclamoController::class, 'store']);
    Route::get('/reclamos/{reclamo}', [ReclamoController::class, 'show']);
    Route::patch('/reclamos/{reclamo}/asignar', [ReclamoController::class, 'asignar']);
    Route::patch('/reclamos/{reclamo}/diagnostico', [ReclamoController::class, 'diagnostico']);

    Route::get('/auditoria', [AuditoriaController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

