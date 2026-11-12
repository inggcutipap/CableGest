<?php

namespace App\Services;

use App\Models\CatalogoServicio;
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
            || preg_match('/\bONUS?\b/', $texto) === 1
        ) {
            return 'equipo';
        }

        $tieneInternet = str_contains($texto, 'INTERNET');
        $tieneTv = str_contains($texto, 'TELEVISION') || preg_match('/\bTV\b/', $texto) === 1;

        if ($tieneInternet && $tieneTv) {
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

        if (preg_match('/(\d+)\s*MBPS/i', $nombre, $coincidencia) === 1) {
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

    private function normalizarTexto(string $texto): string
    {
        return trim(Str::upper(Str::ascii($texto)));
    }
}

