<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Aplicacion\IncrementarCiclosBateria;
use App\Dominios\Mantenimiento\Contratos\LecturaCiclosBateria;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Mantenimiento\Contratos\LecturaEquipamientoPorBase;
use App\Dominios\Mantenimiento\Contratos\LecturaMantenimientoPorDron;
use App\Dominios\Mantenimiento\Infraestructura\Busqueda\BusquedaBaterias;
use App\Dominios\Mantenimiento\Infraestructura\Busqueda\BusquedaGeneradores;
use App\Dominios\Mantenimiento\Infraestructura\Busqueda\BusquedaVehiculos;
use App\Dominios\Operaciones\Contratos\Eventos\RecargaRegistrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Primer `ServiceProvider` del módulo `Mantenimiento` (HU-40, tarea 50; ADR
 * 0011, extensión 3/9/2026, punto 14). Mismo molde que
 * `OperacionesServiceProvider`/`PersonalServiceProvider`: cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención.
 *
 * `register()` liga {@see LecturaCiclosBateria} (HU-80, tarea 86) — mismo
 * patrón que `OperacionesServiceProvider` con
 * `LecturaAlertasTemperaturaBateria`, en la dirección inversa.
 *
 * `boot()` registra el namespace de vista `mantenimiento::` (mismo patrón
 * que `operaciones::`/`personal::`): las páginas Blade del módulo viven bajo
 * `Infraestructura/Http/Views/`, no bajo `resources/views/`. También cablea
 * el oyente real de `RecargaRegistrada` (HU-87, tarea 102), mismo molde que
 * `FinanzasServiceProvider::boot()` con `SesionValidada`: un closure
 * resuelto por el contenedor, no una clase de listener registrada por
 * convención.
 */
final class MantenimientoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaCiclosBateria::class, LecturaCiclosBateriaEloquent::class);
        $this->app->bind(LecturaEquipamiento::class, LecturaEquipamientoEloquent::class);
        $this->app->bind(LecturaEquipamientoPorBase::class, LecturaEquipamientoPorBaseEloquent::class);
        $this->app->bind(LecturaMantenimientoPorDron::class, LecturaMantenimientoPorDronEloquent::class);

        // Buscador global (`busqueda.proveedores`): el agregador de Seguridad
        // no conoce estas clases, las recibe por tag. Sumar una entidad al
        // buscador es escribir su proveedor y taggearlo acá.
        $this->app->tag(BusquedaBaterias::class, 'busqueda.proveedores');
        $this->app->tag(BusquedaVehiculos::class, 'busqueda.proveedores');
        $this->app->tag(BusquedaGeneradores::class, 'busqueda.proveedores');
    }

    public function boot(): void
    {
        Event::listen(function (RecargaRegistrada $evento): void {
            app(IncrementarCiclosBateria::class)->ejecutar($evento->bateriaSalienteId);
        });

        View::addNamespace('mantenimiento', app_path('Dominios/Mantenimiento/Infraestructura/Http/Views'));
    }
}
