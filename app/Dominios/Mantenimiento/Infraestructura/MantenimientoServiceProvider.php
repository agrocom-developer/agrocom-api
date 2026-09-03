<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Primer `ServiceProvider` del módulo `Mantenimiento` (HU-40, tarea 50; ADR
 * 0011, extensión 3/9/2026, punto 14). Mismo molde que
 * `OperacionesServiceProvider`/`PersonalServiceProvider`: cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención.
 *
 * `register()` queda vacío a propósito: esta tarea (vehículos) no define
 * ningún contrato de lectura hacia otro módulo — se completa cuando haga
 * falta uno (ver `PersonalServiceProvider` para el patrón de binding).
 *
 * `boot()` registra el namespace de vista `mantenimiento::` (mismo patrón
 * que `operaciones::`/`personal::`): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class MantenimientoServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::addNamespace('mantenimiento', app_path('Dominios/Mantenimiento/Infraestructura/Http/Views'));
    }
}
