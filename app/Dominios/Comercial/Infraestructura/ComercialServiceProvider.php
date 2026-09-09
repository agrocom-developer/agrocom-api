<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga el contrato de lectura del módulo a su implementación Eloquent (ADR
 * 0003, regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 *
 * `boot()` registra el namespace de vista `comercial::` (HU-22, tarea 33;
 * mismo patrón que `OperacionesServiceProvider`): las páginas Blade del
 * módulo viven bajo `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class ComercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaLotes::class, LecturaLotesEloquent::class);
        $this->app->bind(LecturaContrato::class, LecturaContratoEloquent::class);
        $this->app->bind(LecturaPanelComercial::class, LecturaPanelComercialEloquent::class);
        $this->app->bind(LecturaCultivoLote::class, LecturaCultivoLoteEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('comercial', app_path('Dominios/Comercial/Infraestructura/Http/Views'));
    }
}
