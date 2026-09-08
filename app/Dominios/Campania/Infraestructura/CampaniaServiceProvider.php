<?php

namespace App\Dominios\Campania\Infraestructura;

use App\Dominios\Campania\Contratos\LecturaCampania;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * `register()` liga {@see LecturaCampania} a su implementación Eloquent (ADR
 * 0003, regla 2): la lectura cross-módulo que usan `Comercial\Aplicacion\CrearContrato`/
 * `ActualizarContrato` y `Finanzas\Aplicacion\CrearGasto` para validar
 * imputaciones contra una campaña (HU-46, tarea 69, corrección de
 * arquitectura). `boot()` registra el namespace de vista `campania::` (ADR
 * 0015, tarea 69): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class CampaniaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaCampania::class, LecturaCampaniaEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('campania', app_path('Dominios/Campania/Infraestructura/Http/Views'));
    }
}
