<?php

namespace App\Imports;

use App\Models\Contrato;
use App\Models\Pago;
use App\Support\MigracionHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa recibos pendientes o historicos desde Excel/CSV asociandolos a su contrato.
 */
class RecibosImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $creados = 0;

    public int $actualizados = 0;

    /** @var array<int, array<string, mixed>> */
    public array $rechazados = [];

    public function collection(Collection $rows): void
    {
        $contratoPorCodigo = Contrato::query()->pluck('id', 'codigo_contrato');
        $hoy = now()->toDateString();

        DB::transaction(function () use ($rows, $contratoPorCodigo, $hoy): void {
            $filaNumero = 1;

            foreach ($rows as $row) {
                $filaNumero++;

                $codigoContrato = trim((string) ($row['codigo_contrato'] ?? $row['tnumcontrato'] ?? ''));
                if ($codigoContrato === '' || ! $contratoPorCodigo->has($codigoContrato)) {
                    $this->rechazados[] = [
                        'fila' => $filaNumero,
                        'motivo' => "Contrato no encontrado ({$codigoContrato}).",
                    ];
                    continue;
                }

                $contratoId = (int) $contratoPorCodigo->get($codigoContrato);
                $montoBruto = $row['monto'] ?? $row['tsaldo'] ?? $row['ttotal'] ?? null;
                $monto = is_string($montoBruto)
                    ? (float) str_replace(['S/', 's/', ',', ' '], '', $montoBruto)
                    : (float) $montoBruto;

                if ($monto <= 0) {
                    $this->rechazados[] = [
                        'fila' => $filaNumero,
                        'motivo' => 'El monto debe ser mayor a cero.',
                    ];
                    continue;
                }

                $periodo = $this->extraerPeriodo($row['periodo'] ?? null, $row['tnumrecibo'] ?? null);
                $fechaVencimiento = MigracionHelper::normalizarFecha(
                    $row['fecha_vencimiento'] ?? $row['tfecven'] ?? null,
                    null,
                    $hoy
                );
                $estado = $this->mapearEstado(
                    (string) ($row['estado'] ?? $row['tsit'] ?? 'pendiente'),
                    $fechaVencimiento,
                    $hoy
                );
                $fechaPago = $estado === 'pagado'
                    ? MigracionHelper::normalizarFecha($row['fecha_pago'] ?? null, null, $hoy)
                    : null;
                $observaciones = trim((string) ($row['observaciones'] ?? $row['tdescripcion'] ?? ''));

                $datos = [
                    'monto' => number_format(round($monto, 2), 2, '.', ''),
                    'fecha_vencimiento' => $fechaVencimiento,
                    'fecha_pago' => $fechaPago,
                    'estado' => $estado,
                    'observaciones' => $observaciones !== '' ? mb_substr($observaciones, 0, 255) : null,
                ];

                if ($periodo !== null) {
                    $existente = Pago::query()
                        ->where('contrato_id', $contratoId)
                        ->where('periodo', $periodo)
                        ->first();

                    if ($existente !== null) {
                        $existente->update($datos);
                        $this->actualizados++;
                    } else {
                        Pago::create(array_merge([
                            'contrato_id' => $contratoId,
                            'periodo' => $periodo,
                        ], $datos));
                        $this->creados++;
                    }
                } else {
                    Pago::create(array_merge([
                        'contrato_id' => $contratoId,
                        'periodo' => null,
                    ], $datos));
                    $this->creados++;
                }
            }
        });
    }

    private function extraerPeriodo(mixed $periodo, mixed $numRecibo): ?string
    {
        $textoPeriodo = trim((string) ($periodo ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $textoPeriodo) === 1) {
            return $textoPeriodo;
        }

        $recibo = trim((string) ($numRecibo ?? ''));
        if (preg_match('/^(\d{2})(\d{2})/', $recibo, $m) === 1) {
            return '20' . $m[1] . '-' . $m[2];
        }

        return null;
    }

    private function mapearEstado(string $bruto, string $fechaVencimiento, string $hoy): string
    {
        $texto = strtoupper(trim($bruto));

        return match ($texto) {
            'PAGADO', 'CAN' => 'pagado',
            'VENCIDO' => 'vencido',
            'ANULADO' => 'anulado',
            default => $fechaVencimiento < $hoy ? 'vencido' : 'pendiente',
        };
    }
}
