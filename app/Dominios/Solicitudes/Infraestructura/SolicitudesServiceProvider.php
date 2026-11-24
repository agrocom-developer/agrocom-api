<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Aplicacion\AvanceDeSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use App\Dominios\Solicitudes\Contratos\LecturaServiciosAsignados;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Solicitudes` (especificación del marketplace §4.1 y §4.2,
 * ADR 0027 y 0036). Aloja al cliente, las zonas, los terrenos y la solicitud
 * de servicio con su máquina de estados.
 */
final class SolicitudesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El registro del cliente usa el contrato `RegistroCuentas` que
        // Seguridad enlaza; acá solo se ofrece la lectura de zonas.
        $this->app->bind(LecturaZonas::class, LecturaZonasEloquent::class);
        $this->app->bind(SolicitudParaTrabajo::class, AvanceDeSolicitud::class);
        $this->app->bind(LecturaServiciosAsignados::class, LecturaServiciosAsignadosEloquent::class);
        $this->app->bind(LecturaContextoSolicitudes::class, LecturaContextoSolicitudesEloquent::class);
    }

    public function boot(): void
    {
        View::addNamespace('solicitudes', app_path('Dominios/Solicitudes/Infraestructura/Http/Views'));
    }
}
