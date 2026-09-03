<?php

namespace App\Dominios\Inventario\Infraestructura;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Primer `ServiceProvider` del módulo `Inventario` (HU-36, tarea 52; ADR
 * 0011, extensión 3/9/2026, punto 15). Mismo molde que
 * `MantenimientoServiceProvider`: cada módulo registra su propio provider
 * para lo que el contenedor no resuelve por convención.
 *
 * `register()` queda vacío a propósito: esta tarea no define ningún contrato
 * de lectura hacia otro módulo, ni lo consume — `Inventario` solo referencia
 * `per_bases` por id plano (ADR 0003 regla 3). Se completa cuando haga falta
 * un contrato real (ver `PersonalServiceProvider` para el patrón de binding).
 *
 * `boot()` registra el namespace de vista `inventario::` (mismo patrón que
 * `mantenimiento::`/`operaciones::`): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class InventarioServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::addNamespace('inventario', app_path('Dominios/Inventario/Infraestructura/Http/Views'));
    }
}
