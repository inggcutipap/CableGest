<?php

namespace App\Console\Commands;

use App\Imports\CatalogoImport;
use App\Models\CatalogoServicio;
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

        $rutaReporte = $this->generarReporteDuplicados($clasificador);

        $this->info("Importacion completada: {$importador->creados} creados, {$importador->actualizados} actualizados, {$importador->omitidos} omitidos.");
        $this->line("Reporte de posibles duplicados: {$rutaReporte}");

        return self::SUCCESS;
    }

    private function generarReporteDuplicados(CatalogoClasificador $clasificador): string
    {
        $items = CatalogoServicio::orderBy('codigo_interno')->get();
        $grupos = $items->groupBy(fn (CatalogoServicio $item): string => $clasificador->normalizarNombre($item->nombre));

        $ruta = storage_path('catalogo_duplicados.csv');
        $archivo = fopen($ruta, 'wb');

        if ($archivo === false) {
            throw new \RuntimeException("No se pudo escribir el reporte en {$ruta}");
        }

        fwrite($archivo, "\xEF\xBB\xBF");
        fputcsv($archivo, ['grupo_normalizado', 'id', 'codigo_interno', 'nombre', 'tipo', 'precio', 'activo']);

        foreach ($grupos as $nombreNormalizado => $grupo) {
            if ($grupo->count() < 2) {
                continue;
            }

            foreach ($grupo as $item) {
                fputcsv($archivo, [
                    $nombreNormalizado,
                    $item->id,
                    $item->codigo_interno,
                    $item->nombre,
                    $item->tipo,
                    number_format((float) $item->precio, 2, '.', ''),
                    $item->activo ? '1' : '0',
                ]);
            }
        }

        fclose($archivo);

        return $ruta;
    }
}

