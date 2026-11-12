<?php

namespace App\Imports;

use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Support\MigracionHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa clientes depurados y contratos asociados desde Excel sin duplicar DNI.
 */
class CarteraImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $clientesCreados = 0;

    public int $clientesActualizados = 0;

    public int $contratosCreados = 0;

    public int $contratosActualizados = 0;

    /** @var array<int, array<string, mixed>> */
    public array $rechazados = [];

    public function collection(Collection $rows): void
    {
        $dnisVistosEnArchivo = [];
        $catalogoPorCodigo = CatalogoServicio::query()->pluck('id', 'codigo_interno');

        DB::transaction(function () use ($rows, &$dnisVistosEnArchivo, $catalogoPorCodigo): void {
            $filaNumero = 1;

            foreach ($rows as $row) {
                $filaNumero++;

                $dni = trim((string) ($row['dni'] ?? $row['codigo'] ?? $row['tprocli'] ?? ''));
                $nombres = trim((string) ($row['nombres'] ?? $row['nombre'] ?? ''));
                $direccion = trim((string) ($row['direccion'] ?? $row['direcc'] ?? $row['tdireccion'] ?? ''));
                $telefono = trim((string) ($row['telefono'] ?? $row['telefo'] ?? $row['celular'] ?? ''));
                $fechaRegistro = MigracionHelper::normalizarFecha(
                    $row['fecha_registro'] ?? $row['tdate'] ?? null,
                    null,
                    now()->toDateString()
                );

                if (preg_match('/^\d{8}$/', $dni) !== 1) {
                    $this->registrarRechazo($filaNumero, 'DNI invalido: debe tener exactamente 8 digitos numericos.');
                    continue;
                }

                if ($nombres === '') {
                    $this->registrarRechazo($filaNumero, 'El nombre del cliente es obligatorio.');
                    continue;
                }

                if (isset($dnisVistosEnArchivo[$dni])) {
                    $this->registrarRechazo($filaNumero, 'DNI duplicado dentro del archivo.');
                    continue;
                }

                $dnisVistosEnArchivo[$dni] = true;

                $clienteExistente = Cliente::query()->where('dni', $dni)->first();
                $payloadCliente = [
                    'nombres' => mb_substr($nombres, 0, 150),
                    'direccion' => mb_substr($direccion !== '' ? $direccion : 'Sin direccion registrada', 0, 200),
                    'telefono' => $telefono !== '' && $telefono !== '0' ? mb_substr($telefono, 0, 20) : null,
                    'fecha_registro' => $fechaRegistro,
                ];

                if ($clienteExistente !== null) {
                    $clienteExistente->update($payloadCliente);
                    $cliente = $clienteExistente;
                    $this->clientesActualizados++;
                } else {
                    $cliente = Cliente::create(array_merge(['dni' => $dni], $payloadCliente));
                    $this->clientesCreados++;
                }

                $codigoPlan = trim((string) ($row['codigo_plan'] ?? $row['codigo_servicio'] ?? $row['codigo_interno'] ?? $row['tcodigo'] ?? ''));
                if ($codigoPlan === '') {
                    continue;
                }

                $catalogoId = $catalogoPorCodigo->get($codigoPlan);
                if ($catalogoId === null) {
                    $this->registrarRechazo($filaNumero, "Plan no encontrado en el catalogo ({$codigoPlan}).");
                    continue;
                }

                $codigoContrato = trim((string) ($row['codigo_contrato'] ?? $row['tnumcontrato'] ?? ''));
                $fechaInicio = MigracionHelper::normalizarFecha(
                    $row['fecha_inicio'] ?? $row['tfecini'] ?? null,
                    null,
                    $fechaRegistro
                );
                $estado = MigracionHelper::mapearEstadoContrato((string) ($row['estado'] ?? $row['testado'] ?? 'activo'));

                if ($codigoContrato !== '') {
                    $contratoExistente = Contrato::query()->where('codigo_contrato', $codigoContrato)->first();

                    if ($contratoExistente !== null) {
                        $contratoExistente->update([
                            'cliente_id' => $cliente->id,
                            'catalogo_id' => $catalogoId,
                            'fecha_inicio' => $fechaInicio,
                            'estado' => $estado,
                        ]);
                        $this->contratosActualizados++;
                    } else {
                        Contrato::create([
                            'codigo_contrato' => mb_substr($codigoContrato, 0, 20),
                            'cliente_id' => $cliente->id,
                            'catalogo_id' => $catalogoId,
                            'venta_detalle_id' => null,
                            'fecha_inicio' => $fechaInicio,
                            'estado' => $estado,
                        ]);
                        $this->contratosCreados++;
                    }
                } else {
                    Contrato::create([
                        'codigo_contrato' => null,
                        'cliente_id' => $cliente->id,
                        'catalogo_id' => $catalogoId,
                        'venta_detalle_id' => null,
                        'fecha_inicio' => $fechaInicio,
                        'estado' => $estado,
                    ]);
                    $this->contratosCreados++;
                }
            }
        });
    }

    private function registrarRechazo(int $fila, string $motivo): void
    {
        $this->rechazados[] = [
            'fila' => $fila,
            'motivo' => $motivo,
        ];
    }
}
