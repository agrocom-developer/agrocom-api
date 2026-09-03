<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Aplicacion\GenerarDevengosSesion;
use App\Dominios\Finanzas\Contratos\EscrituraGastoMantenimiento;
use App\Dominios\Operaciones\Dominio\Eventos\SesionValidada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el oyente real de `SesionValidada` (HU-16, tarea 16 — el docblock
 * del evento decía "sin oyente real todavía" hasta esta tarea). Mismo
 * patrón que `SeguridadServiceProvider::registrarUltimoUso()` con
 * `TokenAuthenticated`: un closure resuelto por el contenedor, no una clase
 * de listener registrada por convención — no hace falta más ceremonia para
 * un solo oyente.
 *
 * `boot()` también registra el namespace de vista `finanzas::` (HU-28, tarea
 * 40 — primer `Http/` del módulo): mismo patrón que
 * `PersonalServiceProvider`/`OperacionesServiceProvider`.
 *
 * `register()` bindea {@see EscrituraGastoMantenimiento} (HU-37, tarea 53):
 * primer contrato de escritura que `Finanzas` expone hacia afuera, consumido
 * por `Mantenimiento` para generar el gasto del cierre de una orden.
 */
final class FinanzasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EscrituraGastoMantenimiento::class, EscrituraGastoMantenimientoEloquent::class);
    }

    public function boot(): void
    {
        Event::listen(function (SesionValidada $evento): void {
            app(GenerarDevengosSesion::class)->ejecutar($evento->sesionId);
        });

        View::addNamespace('finanzas', app_path('Dominios/Finanzas/Infraestructura/Http/Views'));
    }
}
