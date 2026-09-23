<?php

namespace App\Dominios\Notificaciones\Infraestructura;

use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaContratoCreado;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaOrdenTrabajoCreada;
use App\Dominios\Notificaciones\Aplicacion\Reglas\ReglaTrabajoCerrado;
use App\Dominios\Notificaciones\Aplicacion\ReglasDeNotificacion;
use App\Dominios\Notificaciones\Contratos\LecturaNotificaciones;
use App\Dominios\Notificaciones\Dominio\ReglaNotificacion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el motor de notificaciones (tarea 141, ADR 0025).
 *
 * `register()` junta las reglas por tag (sumar un aviso es escribir su regla y
 * taggearla acá), arma el registro que las indexa por evento y enlaza el
 * contrato de lectura de la campana. `boot()` registra el listener genérico
 * UNA vez por cada evento que tiene regla: a diferencia de
 * `FinanzasServiceProvider` con `SesionValidada` (un cálculo de negocio propio
 * de un módulo, un closure por evento), acá un solo listener sirve a todos.
 *
 * Las vistas de la campana no viven en un namespace propio: la molécula
 * `notifications-menu` es del catálogo del panel y recibe ya armados los datos.
 */
final class NotificacionesServiceProvider extends ServiceProvider
{
    /** @var list<class-string<ReglaNotificacion>> */
    private const array REGLAS = [
        ReglaContratoCreado::class,
        ReglaOrdenTrabajoCreada::class,
        ReglaTrabajoCerrado::class,
    ];

    public function register(): void
    {
        $this->app->tag(self::REGLAS, 'notificaciones.reglas');

        $this->app->singleton(
            ReglasDeNotificacion::class,
            static fn ($app): ReglasDeNotificacion => new ReglasDeNotificacion($app->tagged('notificaciones.reglas')),
        );

        $this->app->bind(LecturaNotificaciones::class, LecturaNotificacionesEloquent::class);
    }

    public function boot(): void
    {
        foreach ($this->app->make(ReglasDeNotificacion::class)->eventos() as $evento) {
            Event::listen($evento, EntregarNotificacionDelEvento::class);
        }
    }
}
