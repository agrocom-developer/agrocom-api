<?php

namespace App\Dominios\Solicitudes\Infraestructura;

use App\Dominios\Solicitudes\Aplicacion\AvanceDeSolicitud;
use App\Dominios\Solicitudes\Aplicacion\EliminacionDeCliente;
use App\Dominios\Solicitudes\Aplicacion\PasosDeSolicitudPorMaquina;
use App\Dominios\Solicitudes\Aplicacion\ReputacionDeClientePorSolicitudes;
use App\Dominios\Solicitudes\Aplicacion\ResolucionDeServicioPorMaquina;
use App\Dominios\Solicitudes\Contratos\LecturaContextoSolicitudes;
use App\Dominios\Solicitudes\Contratos\LecturaEstadoDeCliente;
use App\Dominios\Solicitudes\Contratos\LecturaMercado;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\LecturaServiciosAsignados;
use App\Dominios\Solicitudes\Contratos\LecturaUbicaciones;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use App\Dominios\Solicitudes\Contratos\ReputacionDeClientes;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use App\Dominios\Solicitudes\Infraestructura\Busqueda\BusquedaClientes;
use App\Dominios\Solicitudes\Infraestructura\Console\EvaluarClientesCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Solicitudes` (especificación del marketplace §4.1 y §4.2,
 * ADR 0027 y 0036). Aloja al cliente, los terrenos y la solicitud
 * de servicio con su máquina de estados.
 */
final class SolicitudesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eliminación de cuenta (ADR 0047): lo que este módulo aporta; Seguridad lo recibe por tag.
        $this->app->tag(EliminacionDeCliente::class, 'eliminacion.participantes');

        $this->app->bind(LecturaUbicaciones::class, LecturaUbicacionesEloquent::class);
        $this->app->bind(SolicitudParaTrabajo::class, AvanceDeSolicitud::class);
        $this->app->bind(LecturaServiciosAsignados::class, LecturaServiciosAsignadosEloquent::class);
        $this->app->bind(LecturaContextoSolicitudes::class, LecturaContextoSolicitudesEloquent::class);
        $this->app->bind(LecturaMercado::class, LecturaMercadoEloquent::class);
        $this->app->bind(PasosDeSolicitud::class, PasosDeSolicitudPorMaquina::class);
        $this->app->bind(LecturaServicios::class, LecturaServiciosEloquent::class);
        $this->app->bind(ResolucionDeServicio::class, ResolucionDeServicioPorMaquina::class);
        $this->app->bind(ReputacionDeClientes::class, ReputacionDeClientePorSolicitudes::class);
        $this->app->bind(LecturaEstadoDeCliente::class, LecturaEstadoDeClienteEloquent::class);

        // Buscador global (`busqueda.proveedores`): el agregador de Seguridad
        // no conoce estas clases, las recibe por tag.
        $this->app->tag(BusquedaClientes::class, 'busqueda.proveedores');
    }

    public function boot(): void
    {
        View::addNamespace('solicitudes', app_path('Dominios/Solicitudes/Infraestructura/Http/Views'));

        if ($this->app->runningInConsole()) {
            $this->commands([EvaluarClientesCommand::class]);
        }
    }
}
