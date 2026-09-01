<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Aplicacion\GenerarDevengosSesion;
use App\Dominios\Operaciones\Dominio\Eventos\SesionValidada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el oyente real de `SesionValidada` (HU-16, tarea 16 — el docblock
 * del evento decía "sin oyente real todavía" hasta esta tarea). Mismo
 * patrón que `SeguridadServiceProvider::registrarUltimoUso()` con
 * `TokenAuthenticated`: un closure resuelto por el contenedor, no una clase
 * de listener registrada por convención — no hace falta más ceremonia para
 * un solo oyente.
 *
 * `Finanzas` no necesita `register()`: no expone contrato propio todavía, y
 * los contratos que consume (`LecturaSesionValidada` de Operaciones,
 * `LecturaTarifaPersona` de Personal) ya los liga cada módulo dueño.
 */
final class FinanzasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(function (SesionValidada $evento): void {
            app(GenerarDevengosSesion::class)->ejecutar($evento->sesionId);
        });
    }
}
