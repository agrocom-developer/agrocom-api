<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria;
use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel;
use App\Dominios\Operaciones\Contratos\LecturaDesempenioPersona;
use App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo;
use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Contratos\LecturaSesionValidada;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos del módulo a su implementación Eloquent (ADR 0003,
 * regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 *
 * `boot()` registra el namespace de vista `operaciones::` (HU-05, tarea 13;
 * mismo patrón que `DistribucionServiceProvider`): las páginas Blade del
 * módulo viven bajo `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class OperacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaOrdenesVigentes::class, LecturaOrdenesVigentesEloquent::class);
        $this->app->bind(EscrituraSincronizacion::class, EscrituraSincronizacionEloquent::class);
        $this->app->bind(LecturaSesionValidada::class, LecturaSesionValidadaEloquent::class);
        $this->app->bind(LecturaActaConformada::class, LecturaActaConformadaEloquent::class);
        $this->app->bind(LecturaReporteTecnico::class, LecturaReporteTecnicoEloquent::class);
        $this->app->bind(LecturaAlertasTemperaturaBateria::class, LecturaAlertasTemperaturaBateriaEloquent::class);
        $this->app->bind(LecturaHorasVueloPorModelo::class, LecturaHorasVueloPorModeloEloquent::class);
        $this->app->bind(LecturaContadoresPanel::class, LecturaContadoresPanelEloquent::class);
        $this->app->bind(LecturaPanelOperaciones::class, LecturaPanelOperacionesEloquent::class);
        $this->app->bind(LecturaDesempenioPersona::class, LecturaDesempenioPersonaEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('operaciones', app_path('Dominios/Operaciones/Infraestructura/Http/Views'));
    }
}
