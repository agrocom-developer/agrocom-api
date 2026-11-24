<?php

namespace App\Dominios\Asignacion\Infraestructura;

use App\Dominios\Asignacion\Aplicacion\EvaluarProveedor;
use App\Dominios\Asignacion\Aplicacion\IniciarAsignacion;
use App\Dominios\Asignacion\Aplicacion\RetirarOfertas;
use App\Dominios\Asignacion\Infraestructura\Console\EvaluarProveedoresCommand;
use App\Dominios\Asignacion\Infraestructura\Console\ProcesarAsignacionesCommand;
use App\Dominios\Calificaciones\Contratos\Eventos\ReputacionDeProveedorActualizada;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudPublicada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Cablea el módulo `Asignacion` (ADR 0030 y 0038): el motor que ofrece cada
 * solicitud publicada a propios y luego a terceros.
 *
 * - `SolicitudPublicada` (Solicitudes): abre la primera ronda. Si falla, la
 *   solicitud sigue `publicada` y el proceso programado la toma después; el
 *   error se reporta y no devuelve un 500 sobre algo que el cliente ya publicó.
 * - `SolicitudCancelada` (Solicitudes): retira las ofertas vivas.
 * - `ReputacionDeProveedorActualizada` (Calificaciones): la calificación del proveedor
 *   cambió; aplica la política de sanciones al instante, sin esperar a la evaluación
 *   diaria (ADR 0039). Si falla, la evaluación diaria la recoge.
 * - `asignacion:procesar` (cada minuto) vence las ofertas y abre la ronda
 *   siguiente; `asignacion:evaluar-proveedores` (diario) aplica la política de
 *   sanciones.
 */
final class AsignacionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('asignacion', app_path('Dominios/Asignacion/Infraestructura/Http/Views'));

        Event::listen(function (SolicitudPublicada $evento): void {
            try {
                app(IniciarAsignacion::class)->ejecutar($evento->solicitudId);
            } catch (Throwable $error) {
                report($error);
            }
        });

        Event::listen(function (SolicitudCancelada $evento): void {
            try {
                app(RetirarOfertas::class)->porCancelacion($evento->solicitudId);
            } catch (Throwable $error) {
                report($error);
            }
        });

        Event::listen(function (ReputacionDeProveedorActualizada $evento): void {
            try {
                app(EvaluarProveedor::class)->ejecutar($evento->proveedorId);
            } catch (Throwable $error) {
                report($error);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ProcesarAsignacionesCommand::class, EvaluarProveedoresCommand::class]);
        }
    }
}
