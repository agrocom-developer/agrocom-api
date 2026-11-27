<?php

namespace App\Dominios\Notificaciones\Infraestructura;

use App\Dominios\Notificaciones\Aplicacion\EliminacionDeNotificaciones;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaAvisoDeNegociacion;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaCalificacionRegistrada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaCalificacionReportada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaClienteConAviso;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaDisputaAbierta;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaDisputaApelada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaDisputaResuelta;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaDocumentoPorVencer;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaDocumentoVencido;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaEstadoDeAprobacionCambiado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaProveedorRegistrado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaPublicacionLimitada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaTrabajoCerrado;
use App\Dominios\Notificaciones\Aplicacion\ReglasDeNotificacion;
use App\Dominios\Notificaciones\Contratos\EnviadorDePush;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el motor de notificaciones (tarea 141, ADR 0025).
 *
 * `register()` junta las reglas por tag (sumar un aviso es escribir su regla y
 * taggearla acá), arma el registro que las indexa por evento y enlaza el
 * contrato de lectura de la campana. `boot()` registra el listener genérico
 * UNA vez por cada evento que tiene regla: un solo listener sirve a todos.
 *
 * Las vistas de la campana no viven en un namespace propio: la molécula
 * `notifications-menu` es del catálogo del panel y recibe ya armados los datos.
 */
final class NotificacionesServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ReglaNotificacion>> */
    private const array REGLAS = [
        ReglaTrabajoCerrado::class,
        ReglaAvisoDeNegociacion::class,
        ReglaEstadoDeAprobacionCambiado::class,
        ReglaDocumentoPorVencer::class,
        ReglaDocumentoVencido::class,
        ReglaProveedorRegistrado::class,
        ReglaCalificacionRegistrada::class,
        ReglaCalificacionReportada::class,
        ReglaDisputaAbierta::class,
        ReglaDisputaResuelta::class,
        ReglaDisputaApelada::class,
        ReglaClienteConAviso::class,
        ReglaPublicacionLimitada::class,
    ];

    public function register(): void
    {
        // Eliminación de cuenta (ADR 0047): se borran los avisos y los tokens de push; Seguridad lo recibe por tag.
        $this->app->tag(EliminacionDeNotificaciones::class, 'eliminacion.participantes');

        $this->app->tag(self::REGLAS, 'notificaciones.reglas');

        $this->app->singleton(
            ReglasDeNotificacion::class,
            static fn ($app): ReglasDeNotificacion => new ReglasDeNotificacion($app->tagged('notificaciones.reglas')),
        );

        $this->app->bind(LecturaNotificaciones::class, LecturaNotificacionesEloquent::class);
        $this->app->bind(EnviadorDePush::class, EnviadorDePushNulo::class);
    }

    public function boot(): void
    {
        View::addNamespace('notificaciones', app_path('Dominios/Notificaciones/Infraestructura/Http/Views'));

        foreach ($this->app->make(ReglasDeNotificacion::class)->eventos() as $evento) {
            Event::listen($evento, EntregarNotificacionDelEvento::class);
        }
    }
}
