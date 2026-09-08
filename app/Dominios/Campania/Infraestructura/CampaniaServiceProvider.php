<?php

namespace App\Dominios\Campania\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos de lectura del módulo Campania a su implementación
 * Eloquent (ADR 0003, regla 2), mismo patrón que `ComercialServiceProvider`.
 *
 * `boot()` registra el namespace de vista `campania::` (ADR 0015, tarea 69):
 * las páginas Blade del módulo viven bajo `Infraestructura/Http/Views/`, no
 * bajo `resources/views/`.
 */
final class CampaniaServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::addNamespace('campania', app_path('Dominios/Campania/Infraestructura/Http/Views'));
    }
}
