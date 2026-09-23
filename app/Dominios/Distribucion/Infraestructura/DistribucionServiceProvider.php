<?php

namespace App\Dominios\Distribucion\Infraestructura;

use App\Dominios\Distribucion\Contratos\LecturaVersionesApk;
use App\Dominios\Seguridad\Infraestructura\SeguridadServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Registra el namespace de vista `distribucion::` (mismo patrón que
 * {@see SeguridadServiceProvider}): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`, así que Blade
 * necesita que algo le informe esa ruta adicional. También enlaza su contrato
 * de lectura con la implementación Eloquent.
 */
final class DistribucionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaVersionesApk::class, LecturaVersionesApkEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('distribucion', app_path('Dominios/Distribucion/Infraestructura/Http/Views'));
    }
}
