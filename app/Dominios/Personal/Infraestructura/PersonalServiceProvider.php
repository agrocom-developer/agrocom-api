<?php

namespace App\Dominios\Personal\Infraestructura;

use App\Dominios\Personal\Contratos\LecturaPanelPersonal;
use App\Dominios\Personal\Contratos\LecturaPersonas;
use App\Dominios\Personal\Contratos\LecturaTarifaPersona;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos de lectura del módulo a su implementación Eloquent (ADR
 * 0003, regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 *
 * `boot()` registra el namespace de vista `personal::` (HU-26, tarea 37;
 * mismo patrón que `OperacionesServiceProvider`/`ComercialServiceProvider`):
 * las páginas Blade del módulo viven bajo `Infraestructura/Http/Views/`, no
 * bajo `resources/views/`.
 */
final class PersonalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaPersonas::class, LecturaPersonasEloquent::class);
        $this->app->bind(LecturaTarifaPersona::class, LecturaTarifaPersonaEloquent::class);
        $this->app->bind(LecturaPanelPersonal::class, LecturaPanelPersonalEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('personal', app_path('Dominios/Personal/Infraestructura/Http/Views'));
    }
}
