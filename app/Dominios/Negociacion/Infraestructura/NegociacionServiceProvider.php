<?php

namespace App\Dominios\Negociacion\Infraestructura;

use App\Dominios\Negociacion\Aplicacion\EliminacionDeNegociaciones;
use App\Dominios\Negociacion\Contratos\Eventos\AvisoDeNegociacion;
use App\Dominios\Negociacion\Contratos\LecturaNegociacionParaPanel;
use App\Dominios\Negociacion\Infraestructura\Console\AvisarInactividadCommand;
use App\Dominios\Negociacion\Infraestructura\Console\ProcesarNegociacionCommand;
use App\Dominios\Negociacion\Infraestructura\Oyentes\AvisarCambioDePrecio;
use App\Dominios\Negociacion\Infraestructura\Oyentes\AvisarNuevaSolicitud;
use App\Dominios\Negociacion\Infraestructura\Oyentes\CerrarNegociacionPorCancelacion;
use App\Dominios\Negociacion\Infraestructura\Oyentes\RegistrarEventoEnElChat;
use App\Dominios\Solicitudes\Contratos\Eventos\PrecioPropuestoCambiado;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudAbierta;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Negociacion` (`docs/especificacion/negociacion-contrato.md`): ofertas y
 * contraofertas, adjudicación, seguimiento con fotos, mensajes, ubicación del piloto, mapa
 * de solicitudes abiertas y preferencias de aviso. Reacciona a los hechos de `Solicitudes`
 * por eventos de dominio (abrir, cancelar, cambiar el precio) y habla con los demás módulos
 * solo por sus contratos.
 */
final class NegociacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eliminación de cuenta (ADR 0047): lo que este módulo aporta; Seguridad lo recibe por tag.
        $this->app->tag(EliminacionDeNegociaciones::class, 'eliminacion.participantes');

        $this->app->bind(LecturaNegociacionParaPanel::class, LecturaNegociacionParaPanelEloquent::class);
    }

    public function boot(): void
    {
        Event::listen(SolicitudAbierta::class, AvisarNuevaSolicitud::class);
        Event::listen(SolicitudCancelada::class, CerrarNegociacionPorCancelacion::class);
        Event::listen(PrecioPropuestoCambiado::class, AvisarCambioDePrecio::class);
        Event::listen(AvisoDeNegociacion::class, RegistrarEventoEnElChat::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ProcesarNegociacionCommand::class, AvisarInactividadCommand::class]);
        }
    }
}
