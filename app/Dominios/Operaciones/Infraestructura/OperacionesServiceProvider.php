<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria;
use App\Dominios\Operaciones\Contratos\LecturaLimitesDeVuelo;
use App\Dominios\Operaciones\Contratos\LecturaTrabajos;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos del módulo a su implementación Eloquent (ADR 0003, regla 2)
 * y registra el namespace de vistas `operaciones::`, que aloja los PDF del acta y
 * del reporte técnico del lote. El panel no tiene pantallas de este módulo: los
 * servicios se siguen desde `panel/solicitudes`.
 */
final class OperacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EscrituraSincronizacion::class, EscrituraSincronizacionEloquent::class);
        $this->app->bind(LecturaLimitesDeVuelo::class, LimitesDeVueloDelSistema::class);
        $this->app->bind(LecturaAlertasTemperaturaBateria::class, LecturaAlertasTemperaturaBateriaEloquent::class);
        $this->app->bind(LecturaTrabajos::class, LecturaTrabajosEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('operaciones', app_path('Dominios/Operaciones/Infraestructura/Http/Views'));
    }
}
