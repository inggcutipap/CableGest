<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Notificacion;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta cobros mensuales, registro de pagos, vencimientos, estado de cuenta y morosidad.
 */
class CobranzaService
{
    public function calcularFechaVencimiento(string $periodo): string
    {
        $inicioMes = Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
        $regla = config('cablegest.cobranza.dia_vencimiento', 'fin_de_mes');

        if ($regla === 'fin_de_mes' || ! is_numeric($regla)) {
            return $inicioMes->copy()->endOfMonth()->toDateString();
        }

        $dia = max(1, min((int) $regla, $inicioMes->daysInMonth));

        return $inicioMes->copy()->day($dia)->toDateString();
    }

    /**
     * Genera cobros mensuales para contratos activos sin recibo previo en el periodo.
     *
     * @return array<string, int|string>
     */
    public function generarCobrosMensuales(?string $periodo = null): array
    {
        $periodoNormalizado = $periodo ?? now()->format('Y-m');
        $fechaVencimiento = $this->calcularFechaVencimiento($periodoNormalizado);
        $hoy = now()->toDateString();
        $estados = config('cablegest.cobranza.estados_contrato_generables', ['activo']);

        $contratos = Contrato::query()
            ->whereIn('estado', $estados)
            ->with('catalogoServicio:id,precio')
            ->get();

        $creados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($contratos, $periodoNormalizado, $fechaVencimiento, $hoy, &$creados, &$omitidos): void {
            foreach ($contratos as $contrato) {
                $precio = (float) ($contrato->catalogoServicio?->precio ?? 0);
                if ($precio <= 0) {
                    $omitidos++;
                    continue;
                }

                $yaExiste = Pago::query()
                    ->where('contrato_id', $contrato->id)
                    ->where('periodo', $periodoNormalizado)
                    ->exists();

                if ($yaExiste) {
                    $omitidos++;
                    continue;
                }

                Pago::create([
                    'contrato_id' => $contrato->id,
                    'periodo' => $periodoNormalizado,
                    'monto' => number_format(round($precio, 2), 2, '.', ''),
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => $fechaVencimiento < $hoy ? 'vencido' : 'pendiente',
                ]);
                $creados++;
            }
        });

        return [
            'periodo' => $periodoNormalizado,
            'fecha_vencimiento' => $fechaVencimiento,
            'creados' => $creados,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * Registra el pago efectivo de un cobro pendiente o vencido.
     *
     * @param array<string, mixed> $datos
     */
    public function registrarPago(Pago $pago, array $datos, int $usuarioId): Pago
    {
        return DB::transaction(function () use ($pago, $datos, $usuarioId): Pago {
            /** @var Pago $pagoBloqueado */
            $pagoBloqueado = Pago::query()
                ->whereKey($pago->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($pagoBloqueado->estado, ['pendiente', 'vencido'], true)) {
                throw ValidationException::withMessages([
                    'estado' => ['Solo se pueden cobrar pagos en estado pendiente o vencido.'],
                ]);
            }

            $pagoBloqueado->update([
                'estado' => 'pagado',
                'fecha_pago' => (string) ($datos['fecha_pago'] ?? now()->toDateString()),
                'usuario_cobro_id' => $usuarioId,
                'metodo_pago' => (string) ($datos['metodo_pago'] ?? 'efectivo'),
                'observaciones' => array_key_exists('observaciones', $datos) && $datos['observaciones'] !== null
                    ? trim((string) $datos['observaciones'])
                    : $pagoBloqueado->observaciones,
            ]);

            return $pagoBloqueado->fresh([
                'contrato.cliente:id,nombres,dni',
                'contrato.catalogoServicio:id,codigo_interno,nombre',
                'usuarioCobro:id,nombre,correo',
            ]);
        });
    }

    /**
     * Marca pagos pendientes cuya fecha de vencimiento ya paso y genera alertas sin duplicar.
     *
     * @return array<string, int|string>
     */
    public function marcarVencidosYNotificar(?string $fechaCorte = null): array
    {
        $corte = $fechaCorte ?? now()->toDateString();
        $marcados = 0;
        $notificacionesCreadas = 0;

        DB::transaction(function () use ($corte, &$marcados, &$notificacionesCreadas): void {
            $pagosVencidos = Pago::query()
                ->where('estado', 'pendiente')
                ->whereDate('fecha_vencimiento', '<', $corte)
                ->with('contrato:id,codigo_contrato')
                ->lockForUpdate()
                ->get();

            foreach ($pagosVencidos as $pago) {
                $pago->update(['estado' => 'vencido']);
                $marcados++;

                $codigoContrato = $pago->contrato?->codigo_contrato ?: "C-{$pago->contrato_id}";
                $periodoTexto = $pago->periodo ? "periodo {$pago->periodo}, " : '';
                $fechaTexto = $pago->fecha_vencimiento?->format('Y-m-d') ?? $corte;
                $montoTexto = number_format((float) $pago->monto, 2, '.', '');

                $mensaje = mb_substr(
                    "Pago vencido del contrato {$codigoContrato} ({$periodoTexto}vence {$fechaTexto}, S/ {$montoTexto}).",
                    0,
                    200
                );

                $notificacion = Notificacion::firstOrCreate(
                    [
                        'pago_id' => $pago->id,
                        'tipo' => 'vencimiento',
                    ],
                    [
                        'mensaje' => $mensaje,
                        'estado' => 'pendiente',
                        'fecha_generacion' => now(),
                    ]
                );

                if ($notificacion->wasRecentlyCreated) {
                    $notificacionesCreadas++;
                }
            }
        });

        return [
            'fecha_corte' => $corte,
            'pagos_marcados' => $marcados,
            'notificaciones_creadas' => $notificacionesCreadas,
        ];
    }

    /**
     * Genera el estado de cuenta consolidado de todos los contratos de un cliente.
     *
     * @return array<string, mixed>
     */
    public function estadoCuentaCliente(Cliente $cliente): array
    {
        $pagos = Pago::query()
            ->whereHas('contrato', static fn ($q) => $q->where('cliente_id', $cliente->id))
            ->with([
                'contrato:id,codigo_contrato,cliente_id,catalogo_id,fecha_inicio,estado',
                'contrato.catalogoServicio:id,codigo_interno,nombre,precio',
                'usuarioCobro:id,nombre',
            ])
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('id')
            ->get();

        $montoPendiente = 0.0;
        $montoVencido = 0.0;
        $montoPagado = 0.0;
        $conteo = ['pendiente' => 0, 'vencido' => 0, 'pagado' => 0, 'anulado' => 0];

        foreach ($pagos as $pago) {
            $monto = (float) $pago->monto;
            $conteo[$pago->estado] = ($conteo[$pago->estado] ?? 0) + 1;

            if ($pago->estado === 'pendiente') {
                $montoPendiente += $monto;
            } elseif ($pago->estado === 'vencido') {
                $montoVencido += $monto;
            } elseif ($pago->estado === 'pagado') {
                $montoPagado += $monto;
            }
        }

        return [
            'cliente' => $cliente->only(['id', 'nombres', 'dni', 'direccion', 'telefono']),
            'resumen' => [
                'saldo_pendiente' => round($montoPendiente + $montoVencido, 2),
                'monto_pendiente' => round($montoPendiente, 2),
                'monto_vencido' => round($montoVencido, 2),
                'monto_pagado' => round($montoPagado, 2),
                'conteo' => $conteo,
            ],
            'pagos' => $pagos,
        ];
    }
}
