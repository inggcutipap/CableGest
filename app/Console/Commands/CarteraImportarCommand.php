<?php

namespace App\Console\Commands;

use App\Imports\CarteraImport;
use App\Support\MigracionHelper;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class CarteraImportarCommand extends Command
{
    protected $signature = 'cartera:importar {archivo : Ruta al archivo Excel (.xlsx/.csv) de clientes y contratos}';

    protected $description = 'Importa clientes depurados y contratos desde Excel validando DNI de 8 digitos sin duplicados.';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error("No se encontro el archivo especificado: {$archivo}");

            return self::FAILURE;
        }

        $importador = new CarteraImport();
        Excel::import($importador, $archivo);

        $filasRechazos = array_map(
            static fn (array $fila): array => [$fila['fila'], $fila['motivo']],
            $importador->rechazados
        );
        $rutaRechazos = MigracionHelper::escribirCsv('cartera_rechazados.csv', ['fila', 'motivo'], $filasRechazos);

        $this->info(
            "Importacion de cartera completada: {$importador->clientesCreados} clientes creados, "
            . "{$importador->clientesActualizados} actualizados, {$importador->contratosCreados} contratos creados, "
            . "{$importador->contratosActualizados} contratos actualizados, "
            . count($importador->rechazados) . ' filas rechazadas.'
        );
        $this->line("Reporte de filas rechazadas: {$rutaRechazos}");

        return self::SUCCESS;
    }
}
