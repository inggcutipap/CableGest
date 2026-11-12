<?php

namespace App\Services;

use App\Models\CatalogoServicio;
use App\Models\Contrato;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta el registro transaccional y la anulacion logica de ventas.
 */
class VentaService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function registrar(array $datos, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($datos, $usuarioId): Venta {
            $lineasEntrada = $datos['lineas'];
            $catalogoIds = array_values(array_unique(array_map(
                static fn (array $linea): int => (int) $linea['catalogo_id'],
                $lineasEntrada
            )));

            $catalogoPorId = CatalogoServicio::query()
                ->whereIn('id', $catalogoIds)
                ->get()
                ->keyBy('id');

            $lineasPreparadas = [];
            $montoTotal = 0.0;

            foreach ($lineasEntrada as $indice => $linea) {
                /** @var CatalogoServicio|null $item */
                $item = $catalogoPorId->get((int) $linea['catalogo_id']);

                if ($item === null || ! $item->activo) {
                    throw ValidationException::withMessages([
                        "lineas.{$indice}.catalogo_id" => [
                            'No se puede vender un ítem inactivo o inexistente del catálogo.',
                        ],
                    ]);
                }

                $cantidad = (int) $linea['cantidad'];
                $precioUnitario = round((float) $item->precio, 2);
                $subtotal = round($cantidad * $precioUnitario, 2);
                $montoTotal = round($montoTotal + $subtotal, 2);

                $lineasPreparadas[] = [
                    'item' => $item,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                ];
            }

            $fechaVenta = (string) ($datos['fecha_venta'] ?? now()->toDateString());

            $venta = Venta::create([
                'cliente_id' => (int) $datos['cliente_id'],
                'usuario_id' => $usuarioId,
                'fecha_venta' => $fechaVenta,
                'monto_total' => $montoTotal,
                'estado' => 'registrada',
            ]);

            $siguienteNumero = $this->siguienteNumeroContrato();

            foreach ($lineasPreparadas as $linea) {
                /** @var CatalogoServicio $item */
                $item = $linea['item'];

                $detalle = VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'catalogo_id' => $item->id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                ]);

                if ($item->esPlan()) {
                    Contrato::create([
                        'codigo_contrato' => $this->formatearCodigoContrato($siguienteNumero++),
                        'cliente_id' => $venta->cliente_id,
                        'catalogo_id' => $item->id,
                        'venta_detalle_id' => $detalle->id,
                        'fecha_inicio' => $fechaVenta,
                        'estado' => 'activo',
                    ]);
                }
            }

            return $venta->load([
                'cliente',
                'usuario:id,nombre,correo,rol',
                'detalles.catalogoServicio',
                'detalles.contrato.catalogoServicio',
            ]);
        });
    }

    public function anular(Venta $venta, ?string $motivo = null): Venta
    {
        return DB::transaction(function () use ($venta, $motivo): Venta {
            /** @var Venta $ventaBloqueada */
            $ventaBloqueada = Venta::query()
                ->whereKey($venta->id)
                ->lockForUpdate()
                ->with('detalles.contrato')
                ->firstOrFail();

            if ($ventaBloqueada->estado === 'anulada') {
                throw ValidationException::withMessages([
                    'estado' => ['La venta ya se encuentra anulada.'],
                ]);
            }

            $contratos = $ventaBloqueada->detalles
                ->map(static fn (VentaDetalle $detalle): ?Contrato => $detalle->contrato)
                ->filter()
                ->values();

            foreach ($contratos as $contrato) {
                $tienePagos = $contrato->pagos()
                    ->where('estado', '!=', 'anulado')
                    ->exists();

                if ($tienePagos) {
                    throw ValidationException::withMessages([
                        'venta' => [
                            'No se puede anular la venta porque el contrato asociado ya tiene pagos registrados.',
                        ],
                    ]);
                }
            }

            $ventaBloqueada->update([
                'estado' => 'anulada',
                'motivo_anulacion' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            ]);

            foreach ($contratos as $contrato) {
                if ($contrato->estado !== 'baja') {
                    $contrato->update(['estado' => 'baja']);
                }
            }

            return $ventaBloqueada->fresh([
                'cliente',
                'usuario:id,nombre,correo,rol',
                'detalles.catalogoServicio',
                'detalles.contrato.catalogoServicio',
            ]);
        });
    }

    private function siguienteNumeroContrato(): int
    {
        $ultimoCodigo = Contrato::query()
            ->where('codigo_contrato', 'like', 'CG-%')
            ->orderByDesc('codigo_contrato')
            ->lockForUpdate()
            ->value('codigo_contrato');

        if (! is_string($ultimoCodigo)) {
            return 1;
        }

        return (int) substr($ultimoCodigo, 3) + 1;
    }

    private function formatearCodigoContrato(int $numero): string
    {
        return 'CG-' . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }
}
