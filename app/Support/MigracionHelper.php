<?php

namespace App\Support;

/**
 * Utilidades compartidas para normalizacion de fechas, estados heredados y reportes CSV.
 */
class MigracionHelper
{
    public static function mapearEstadoContrato(string $estadoBruto): string
    {
        return match (strtoupper(trim($estadoBruto))) {
            'CORTADO', 'SUSPENDIDO' => 'suspendido',
            'ELIMINADO', 'BAJA', 'ANULADO' => 'baja',
            default => 'activo',
        };
    }

    public static function normalizarFecha(mixed $primaria, mixed $secundaria = null, ?string $respaldo = null): string
    {
        foreach ([$primaria, $secundaria] as $candidata) {
            if (! is_string($candidata) && ! is_numeric($candidata)) {
                continue;
            }

            $texto = trim((string) $candidata);
            if ($texto === '' || str_starts_with($texto, '1000-01-01') || str_starts_with($texto, '0000-00-00')) {
                continue;
            }

            $marca = strtotime(str_replace('/', '-', $texto));
            if ($marca !== false && (int) date('Y', $marca) >= 1990) {
                return date('Y-m-d', $marca);
            }
        }

        return $respaldo ?? now()->toDateString();
    }

    /**
     * @param array<int, string> $cabeceras
     * @param array<int, array<int, mixed>> $filas
     */
    public static function escribirCsv(string $nombreArchivo, array $cabeceras, array $filas): string
    {
        $ruta = storage_path($nombreArchivo);
        $archivo = fopen($ruta, 'wb');

        if ($archivo === false) {
            throw new \RuntimeException("No se pudo escribir el reporte en {$ruta}");
        }

        fwrite($archivo, "\xEF\xBB\xBF");
        fputcsv($archivo, $cabeceras);

        foreach ($filas as $fila) {
            fputcsv($archivo, $fila);
        }

        fclose($archivo);

        return $ruta;
    }
}
