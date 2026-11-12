<?php

namespace App\Console\Commands;

use App\Services\CobranzaService;
use Illuminate\Console\Command;

class PagosMarcarVencidosCommand extends Command
{
    protected $signature = 'pagos:marcar-vencidos {--fecha= : Fecha de corte AAAA-MM-DD (por defecto hoy)}';

    protected $description = 'Marca como vencidos los pagos pendientes con fecha anterior al corte y crea notificaciones sin duplicar.';

    public function handle(CobranzaService $cobranzaService): int
    {
        $fecha = $this->option('fecha');
        $resultado = $cobranzaService->marcarVencidosYNotificar(is_string($fecha) && $fecha !== '' ? $fecha : null);

        $this->info(
            "Corte {$resultado['fecha_corte']}: {$resultado['pagos_marcados']} pagos marcados como vencidos, "
            . "{$resultado['notificaciones_creadas']} notificaciones creadas."
        );

        return self::SUCCESS;
    }
}
