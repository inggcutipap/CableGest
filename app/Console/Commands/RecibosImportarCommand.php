<?php

namespace App\Console\Commands;

use App\Imports\RecibosImport;
use App\Support\MigracionHelper;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class RecibosImportarCommand extends Command
{
    protected $signature = 'recibos:importar {archivo : Ruta al archivo Excel (.xlsx/.csv) de recibos de cobro}';

    protected $description = 'Importa recibos pendientes o cobros desde Excel/CSV asociandolos a contratos existentes.';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error("No se encontro el archivo especificado: {$archivo}");

            return self::FAILURE;
        }

        $importador = new RecibosImport();
        Excel::import($importador, $archivo);

        $filasRechazos = array_map(
            static fn (array $fila): array => [$fila['fila'], $fila['motivo']],
            $importador->rechazados
        );
        $rutaRechazos = MigracionHelper::escribirCsv('recibos_rechazados.csv', ['fila', 'motivo'], $filasRechazos);

        $this->info(
            "Importacion de recibos completada: {$importador->creados} creados, "
            . "{$importador->actualizados} actualizados, "
            . count($importador->rechazados) . ' filas rechazadas.'
        );
        $this->line("Reporte de filas rechazadas: {$rutaRechazos}");

        return self::SUCCESS;
    }
}
