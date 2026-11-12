<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pagos:marcar-vencidos')->dailyAt('00:30');
Schedule::command('pagos:generar-mes')->monthlyOn((int) config('cablegest.cobranza.generar_dia_mes', 1), '01:00');
