<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Aplicacion\IncrementarCiclosBateria;
use App\Dominios\Mantenimiento\Contratos\LecturaCiclosBateria;
use App\Dominios\Operaciones\Contratos\Eventos\RecargaRegistrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea lo que el módulo `Mantenimiento` conserva para la ejecución en el lote:
 * la lectura de los ciclos de una batería y el oyente de `RecargaRegistrada`, que
 * suma un ciclo a la batería que salió. No tiene pantallas en el panel.
 */
final class MantenimientoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaCiclosBateria::class, LecturaCiclosBateriaEloquent::class);
    }

    public function boot(): void
    {
        Event::listen(function (RecargaRegistrada $evento): void {
            app(IncrementarCiclosBateria::class)->ejecutar($evento->bateriaSalienteId);
        });
    }
}
