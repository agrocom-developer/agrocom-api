<?php

namespace App\Dominios\Metricas\Infraestructura;

use App\Dominios\Metricas\Aplicacion\ArmarTableroDeMercado;
use App\Dominios\Metricas\Contratos\LecturaMetricasCalidad;
use App\Dominios\Metricas\Contratos\LecturaMetricasIngreso;
use App\Dominios\Metricas\Contratos\LecturaMetricasMercado;
use App\Dominios\Metricas\Contratos\LecturaMetricasOferta;
use App\Dominios\Metricas\Contratos\LecturaTableroDeMercado;
use App\Dominios\Metricas\Infraestructura\Lectura\LecturaMetricasCalidadVistas;
use App\Dominios\Metricas\Infraestructura\Lectura\LecturaMetricasIngresoVistas;
use App\Dominios\Metricas\Infraestructura\Lectura\LecturaMetricasMercadoVistas;
use App\Dominios\Metricas\Infraestructura\Lectura\LecturaMetricasOfertaVistas;
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
        $this->app->bind(LecturaMetricasMercado::class, LecturaMetricasMercadoVistas::class);
        $this->app->bind(LecturaMetricasCalidad::class, LecturaMetricasCalidadVistas::class);
        $this->app->bind(LecturaMetricasOferta::class, LecturaMetricasOfertaVistas::class);
        $this->app->bind(LecturaMetricasIngreso::class, LecturaMetricasIngresoVistas::class);
    }

    public function boot(): void
    {
        View::addNamespace('metricas', app_path('Dominios/Metricas/Infraestructura/Http/Views'));
    }
}
