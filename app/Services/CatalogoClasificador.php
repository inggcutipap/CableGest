<?php

namespace App\Services;

use App\Models\CatalogoServicio;
use App\Support\MigracionHelper;
use Illuminate\Support\Str;

/**
 * Clasifica y normaliza items del catalogo segun las reglas de Cable Vision.
 */
class CatalogoClasificador
{
    public function clasificarTipo(string $nombre): string
    {
        $texto = $this->normalizarTexto($nombre);

        $cargos = [
            'RECONEXION',
            'INSTALACION',
            'TRASLADO',
            'SERVICIO TECNICO',
            'MATERIAL EXTRA',
            'MULTA',
            'APORTES',
        ];

        foreach ($cargos as $palabra) {
            if (str_contains($texto, $palabra)) {
                return 'cargo_unico';
            }
        }

        if (
            str_contains($texto, 'VENTA DE EQUIPOS')
            || str_contains($texto, 'BATERIA')
            || str_contains($texto, 'TP LINK')
            || str_contains($texto, 'ROUTER')
            || preg_match('/\bONUS?\b/', $texto) === 1
        ) {
            return 'equipo';
        }

        $tieneInternet = str_contains($texto, 'INTERNET');
        $tieneTv = str_contains($texto, 'TELEVISION')
            || str_contains($texto, 'TVISION')
            || str_contains($texto, 'TLEVISION')
            || str_contains($texto, 'TELVISION')
            || preg_match('/\bTV\b/', $texto) === 1;
        $esDuo = preg_match('/\bDUO\b/', $texto) === 1;

        if (($tieneInternet && $tieneTv) || $esDuo) {
            return 'plan_combo';
        }

        if ($tieneTv) {
            return 'plan_tv';
        }

        if ($tieneInternet) {
            return 'plan_internet';
        }

        return 'cargo_unico';
    }

    public function detectarSegmento(string $nombre, string $tipo): ?string
    {
        if (! in_array($tipo, CatalogoServicio::TIPOS_PLAN, true)) {
            return null;
        }

        $texto = $this->normalizarTexto($nombre);

        if (str_contains($texto, 'EMPRESARIAL')) {
            return 'empresarial';
        }

        if (str_contains($texto, 'COMERCIAL')) {
            return 'comercial';
        }

        return 'residencial';
    }

    public function extraerVelocidadMbps(string $nombre, string $tipo): ?int
    {
        if (! in_array($tipo, CatalogoServicio::TIPOS_PLAN, true)) {
            return null;
        }

        if (preg_match('/(\d+)\s*(?:MBPS|MG|MB)\b/i', $nombre, $coincidencia) === 1) {
            return (int) $coincidencia[1];
        }

        return null;
    }

    public function normalizarNombre(string $nombre): string
    {
        $texto = $this->normalizarTexto($nombre);
        $texto = (string) preg_replace('/\s*-\s*/', '-', $texto);
        $texto = (string) preg_replace('/\s*\+\s*/', ' + ', $texto);
        $texto = (string) preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    public function generarReporteDuplicados(): string
    {
        $items = CatalogoServicio::orderBy('codigo_interno')->get();
        $grupos = $items->groupBy(fn (CatalogoServicio $item): string => $this->normalizarNombre($item->nombre));

        $filas = [];
        foreach ($grupos as $nombreNormalizado => $grupo) {
            if ($grupo->count() < 2) {
                continue;
            }

            foreach ($grupo as $item) {
                $filas[] = [
                    $nombreNormalizado,
                    $item->id,
                    $item->codigo_interno,
                    $item->nombre,
                    $item->tipo,
                    number_format((float) $item->precio, 2, '.', ''),
                    $item->activo ? '1' : '0',
                ];
            }
        }

        return MigracionHelper::escribirCsv(
            'catalogo_duplicados.csv',
            ['grupo_normalizado', 'id', 'codigo_interno', 'nombre', 'tipo', 'precio', 'activo'],
            $filas
        );
    }

    private function normalizarTexto(string $texto): string
    {
        return trim(Str::upper(Str::ascii($texto)));
    }
}

