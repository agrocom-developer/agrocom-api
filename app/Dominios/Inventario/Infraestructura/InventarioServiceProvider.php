<?php

namespace App\Dominios\Inventario\Infraestructura;

use App\Dominios\Inventario\Contratos\EscrituraConsumoStock;
use App\Dominios\Inventario\Contratos\LecturaContadoresPanel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Primer `ServiceProvider` del módulo `Inventario` (HU-36, tarea 52; ADR
 * 0011, extensión 3/9/2026, punto 15). Mismo molde que
 * `MantenimientoServiceProvider`: cada módulo registra su propio provider
 * para lo que el contenedor no resuelve por convención.
 *
 * `register()` bindea {@see EscrituraConsumoStock} (HU-37, tarea 53): primer
 * contrato de ESCRITURA cross-módulo de `Inventario`, consumido por
 * `Mantenimiento` para descontar stock al cerrar una orden — mismo patrón de
 * binding que `PersonalServiceProvider`.
 *
 * `boot()` registra el namespace de vista `inventario::` (mismo patrón que
 * `mantenimiento::`/`operaciones::`): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class InventarioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EscrituraConsumoStock::class, EscrituraConsumoStockEloquent::class);
        $this->app->bind(LecturaContadoresPanel::class, LecturaContadoresPanelEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('inventario', app_path('Dominios/Inventario/Infraestructura/Http/Views'));
    }
}
