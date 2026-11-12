<?php

namespace App\Providers;

use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Equipo;
use App\Models\Pago;
use App\Models\Reclamo;
use App\Models\Venta;
use App\Observers\AuditoriaObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cliente::observe(AuditoriaObserver::class);
        CatalogoServicio::observe(AuditoriaObserver::class);
        Venta::observe(AuditoriaObserver::class);
        Contrato::observe(AuditoriaObserver::class);
        Pago::observe(AuditoriaObserver::class);
        Equipo::observe(AuditoriaObserver::class);
        Reclamo::observe(AuditoriaObserver::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $correo = mb_strtolower((string) $request->input('correo', ''));

            return Limit::perMinute(5)->by($correo . '|' . $request->ip());
        });
    }
}
