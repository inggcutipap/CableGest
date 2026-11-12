<?php

namespace App\Services;

use App\Models\ConteoInventario;
use App\Models\Contrato;
use App\Models\Equipo;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reclamo;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consolida los indicadores clave del dashboard gerencial (RF10).
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function obtenerIndicadores(?string $periodo = null): array
    {
        $hoy = Carbon::today();
        $periodoFiltro = $periodo !== null && preg_match('/^\d{4}-\d{2}$/', $periodo) === 1
            ? $periodo
            : null;

        $clientesActivos = Contrato::query()
            ->where('estado', 'activo')
            ->count();

        $ingresosMes = (float) Pago::query()
            ->where('estado', 'pagado')
            ->whereYear('fecha_pago', $hoy->year)
            ->whereMonth('fecha_pago', $hoy->month)
            ->sum('monto');

        $consultaPagos = Pago::query()->whereIn('estado', ['pendiente', 'vencido', 'pagado']);
        if ($periodoFiltro !== null) {
            $consultaPagos->where('periodo', $periodoFiltro);
        }

        $totalPagos = (clone $consultaPagos)->count();
        $pagosVencidos = (clone $consultaPagos)->where('estado', 'vencido')->count();
        $tasaMorosidad = $totalPagos > 0
            ? round(($pagosVencidos / $totalPagos) * 100, 2)
            : 0.0;

        $reclamosAbiertos = Reclamo::query()
            ->whereIn('estado', ['abierto', 'en_proceso'])
            ->count();
        $reclamosSinAsignar = Reclamo::query()->where('estado', 'abierto')->count();
        $reclamosEnProceso = Reclamo::query()->where('estado', 'en_proceso')->count();
        $reclamosResueltos = Reclamo::query()->where('estado', 'resuelto')->count();

        $ultimoConteo = ConteoInventario::query()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        $stockDisponible = Equipo::query()
            ->where('estado', 'disponible')
            ->count();

        $itemsMasVendidos = VentaDetalle::query()
            ->join('ventas', 'ventas.id', '=', 'venta_detalle.venta_id')
            ->join('catalogo_servicios', 'catalogo_servicios.id', '=', 'venta_detalle.catalogo_id')
            ->where('ventas.estado', 'registrada')
            ->groupBy('venta_detalle.catalogo_id', 'catalogo_servicios.codigo_interno', 'catalogo_servicios.nombre', 'catalogo_servicios.tipo')
            ->select([
                'venta_detalle.catalogo_id',
                'catalogo_servicios.codigo_interno',
                'catalogo_servicios.nombre',
                'catalogo_servicios.tipo',
                DB::raw('SUM(venta_detalle.cantidad) as cantidad_total'),
                DB::raw('ROUND(SUM(venta_detalle.subtotal), 2) as monto_total'),
            ])
            ->orderByDesc('cantidad_total')
            ->limit(5)
            ->get()
            ->map(static fn ($row): array => [
                'catalogo_id' => (int) $row->catalogo_id,
                'codigo_interno' => $row->codigo_interno,
                'nombre' => $row->nombre,
                'tipo' => $row->tipo,
                'cantidad_total' => (int) $row->cantidad_total,
                'monto_total' => round((float) $row->monto_total, 2),
            ])
            ->values();

        $alertasPendientes = Notificacion::query()
            ->where('estado', 'pendiente')
            ->count();

        return [
            'clientes_activos' => $clientesActivos,
            'ingresos_mes' => round($ingresosMes, 2),
            'tasa_morosidad' => $tasaMorosidad,
            'pagos_vencidos' => $pagosVencidos,
            'total_pagos_evaluados' => $totalPagos,
            'reclamos_abiertos' => $reclamosAbiertos,
            'reclamos_sin_asignar' => $reclamosSinAsignar,
            'reclamos_en_proceso' => $reclamosEnProceso,
            'reclamos_resueltos' => $reclamosResueltos,
            'exactitud_inventario' => $ultimoConteo !== null ? round((float) $ultimoConteo->exactitud, 2) : null,
            'ultimo_conteo_fecha' => $ultimoConteo?->fecha?->toIso8601String(),
            'stock_disponible' => $stockDisponible,
            'items_mas_vendidos' => $itemsMasVendidos,
            'alertas_pendientes' => $alertasPendientes,
            'mes_actual' => $hoy->format('Y-m'),
        ];
    }
}
