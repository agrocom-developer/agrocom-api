<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Aplicacion\AsignacionDeSolicitudPorMaquina;
use App\Dominios\Solicitudes\Aplicacion\AvanceDeSolicitud;
use App\Dominios\Solicitudes\Aplicacion\ReputacionDeClientePorSolicitudes;
use App\Dominios\Solicitudes\Aplicacion\ResolucionDeServicioPorMaquina;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\LecturaServiciosAsignados;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use App\Dominios\Solicitudes\Contratos\ReputacionDeClientes;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use App\Dominios\Solicitudes\Infraestructura\Console\CompletarSolicitudesVencidasCommand;
use App\Dominios\Solicitudes\Infraestructura\Console\EvaluarClientesCommand;
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
        $this->app->bind(LecturaSolicitudesParaAsignar::class, LecturaSolicitudesParaAsignarEloquent::class);
        $this->app->bind(AsignacionDeSolicitud::class, AsignacionDeSolicitudPorMaquina::class);
        $this->app->bind(LecturaServicios::class, LecturaServiciosEloquent::class);
        $this->app->bind(ResolucionDeServicio::class, ResolucionDeServicioPorMaquina::class);
        $this->app->bind(ReputacionDeClientes::class, ReputacionDeClientePorSolicitudes::class);
    }

    public function boot(): void
    {
        View::addNamespace('solicitudes', app_path('Dominios/Solicitudes/Infraestructura/Http/Views'));

        if ($this->app->runningInConsole()) {
            $this->commands([CompletarSolicitudesVencidasCommand::class, EvaluarClientesCommand::class]);
        }
    }
}
