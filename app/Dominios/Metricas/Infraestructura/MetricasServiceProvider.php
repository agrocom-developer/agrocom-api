<?php

namespace App\Dominios\Metricas\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Metricas` (especificación del marketplace §14): lecturas de solo
 * lectura del mercado para el panel. Solo registra el namespace de vistas
 * `metricas::`.
 */
final class MetricasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('metricas', app_path('Dominios/Metricas/Infraestructura/Http/Views'));
    }
}
