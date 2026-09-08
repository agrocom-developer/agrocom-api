<?php

namespace App\Dominios\Campania\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * `boot()` registra el namespace de vista `campania::` (ADR 0015, tarea 69):
 * las páginas Blade del módulo viven bajo `Infraestructura/Http/Views/`, no
 * bajo `resources/views/`. Sin campaña activa de sesión (corregida el
 * 8/9/2026): no hay contrato de lectura que ligar acá.
 */
final class CampaniaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('campania', app_path('Dominios/Campania/Infraestructura/Http/Views'));
    }
}
