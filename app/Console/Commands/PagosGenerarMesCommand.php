<?php

namespace App\Console\Commands;

use App\Services\CobranzaService;
use Illuminate\Console\Command;

class PagosGenerarMesCommand extends Command
{
    protected $signature = 'pagos:generar-mes {--periodo= : Periodo AAAA-MM (por defecto el mes actual)}';

    protected $description = 'Genera los cobros mensuales para los contratos activos segun config/cablegest.php.';

    public function handle(CobranzaService $cobranzaService): int
    {
        $periodo = $this->option('periodo');
        $resultado = $cobranzaService->generarCobrosMensuales(is_string($periodo) && $periodo !== '' ? $periodo : null);

        $this->info(
            "Periodo {$resultado['periodo']} (vence {$resultado['fecha_vencimiento']}): "
            . "{$resultado['creados']} cobros generados, {$resultado['omitidos']} omitidos."
        );

        return self::SUCCESS;
    }
}
