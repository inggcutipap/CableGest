<?php

namespace App\Console\Commands;

use App\Imports\CatalogoImport;
use App\Services\CatalogoClasificador;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class CatalogoImportarCommand extends Command
{
    protected $signature = 'catalogo:importar {archivo : Ruta al archivo Excel (.xlsx) del catalogo}';

    protected $description = 'Importa o actualiza el catalogo de servicios desde Excel y genera reporte CSV de posibles duplicados.';

    public function handle(CatalogoClasificador $clasificador): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error("No se encontro el archivo especificado: {$archivo}");

            return self::FAILURE;
        }

        $importador = new CatalogoImport($clasificador);
        Excel::import($importador, $archivo);

        $rutaReporte = $clasificador->generarReporteDuplicados();

        $this->info("Importacion completada: {$importador->creados} creados, {$importador->actualizados} actualizados, {$importador->omitidos} omitidos.");
        $this->line("Reporte de posibles duplicados: {$rutaReporte}");

        return self::SUCCESS;
    }
}

