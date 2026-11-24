<?php

namespace App\Dominios\Calificaciones\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Calificaciones` (especificación del marketplace §4.7, ADR 0031
 * y 0039): la calificación bidireccional de cada servicio completado, su
 * moderación y la reputación que de ella se deriva. Solo registra el namespace de
 * vistas `calificaciones::`: lee los demás módulos por sus contratos, que ellos
 * mismos enlazan.
 */
final class CalificacionesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('calificaciones', app_path('Dominios/Calificaciones/Infraestructura/Http/Views'));
    }
}
