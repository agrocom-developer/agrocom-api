<?php

namespace App\Dominios\Disputas\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Disputas` (especificación del marketplace §4.8, ADR 0033 y
 * 0039): la disputa de un servicio, su mediación y sus reembolsos. Solo registra el
 * namespace de vistas `disputas::`: lee y mueve la solicitud por los contratos de
 * `Solicitudes`, que ese módulo enlaza.
 */
final class DisputasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('disputas', app_path('Dominios/Disputas/Infraestructura/Http/Views'));
    }
}
