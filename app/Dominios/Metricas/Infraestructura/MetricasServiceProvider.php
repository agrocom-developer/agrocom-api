<?php

namespace App\Dominios\Metricas\Infraestructura;

use App\Dominios\Metricas\Contratos\LecturaTablero;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Metricas` (especificación del marketplace §14): lecturas de solo
 * lectura del mercado para el panel. Liga el contrato del tablero y registra el
 * namespace de vistas `metricas::`.
 */
final class MetricasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaTablero::class, LecturaTableroEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('metricas', app_path('Dominios/Metricas/Infraestructura/Http/Views'));
    }
}
