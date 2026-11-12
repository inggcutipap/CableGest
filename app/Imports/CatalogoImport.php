<?php

namespace App\Imports;

use App\Models\CatalogoServicio;
use App\Services\CatalogoClasificador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa items del catalogo desde Excel conservando ceros a la izquierda.
 */
class CatalogoImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $creados = 0;

    public int $actualizados = 0;

    public int $omitidos = 0;

    public function __construct(
        private readonly CatalogoClasificador $clasificador
    ) {
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $codigo = trim((string) ($row['internal_id'] ?? ''));
                $nombre = trim((string) ($row['description'] ?? ''));
                $precioBruto = $row['sale_unit_price'] ?? null;

                if ($codigo === '' || $nombre === '' || $precioBruto === null || $precioBruto === '') {
                    $this->omitidos++;
                    continue;
                }

                $precio = $this->parsearPrecio($precioBruto);
                $tipo = $this->clasificador->clasificarTipo($nombre);
                $segmento = $this->clasificador->detectarSegmento($nombre, $tipo);
                $velocidad = $this->clasificador->extraerVelocidadMbps($nombre, $tipo);

                $existente = CatalogoServicio::where('codigo_interno', $codigo)->first();

                if ($existente !== null) {
                    $existente->update([
                        'nombre' => $nombre,
                        'tipo' => $tipo,
                        'segmento' => $segmento,
                        'velocidad_mbps' => $velocidad,
                        'precio' => $precio,
                    ]);
                    $this->actualizados++;
                } else {
                    CatalogoServicio::create([
                        'codigo_interno' => $codigo,
                        'nombre' => $nombre,
                        'tipo' => $tipo,
                        'segmento' => $segmento,
                        'velocidad_mbps' => $velocidad,
                        'precio' => $precio,
                        'activo' => true,
                    ]);
                    $this->creados++;
                }
            }
        });
    }

    private function parsearPrecio(mixed $valor): string
    {
        if (is_string($valor)) {
            $valor = str_replace(['S/', 's/', ',', ' '], '', $valor);
        }

        return number_format(round((float) $valor, 2), 2, '.', '');
    }
}

