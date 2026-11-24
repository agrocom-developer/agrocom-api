<?php

namespace App\Dominios\Metricas\Infraestructura;

use App\Dominios\Metricas\Aplicacion\ArmarTableroDeMercado;
use App\Dominios\Metricas\Contratos\LecturaTableroDeMercado;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Metricas` (especificación del marketplace §14): lecturas de solo
 * lectura del mercado para el panel. Registra el namespace de vistas
 * `metricas::` y liga el contrato del tablero.
 */
final class MetricasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaTableroDeMercado::class, ArmarTableroDeMercado::class);
    }

    public function boot(): void
    {
        View::addNamespace('metricas', app_path('Dominios/Metricas/Infraestructura/Http/Views'));
    }
}
