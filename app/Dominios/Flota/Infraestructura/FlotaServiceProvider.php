<?php

namespace App\Dominios\Flota\Infraestructura;

use App\Dominios\Flota\Aplicacion\IncrementarCiclosBateriaDron;
use App\Dominios\Flota\Aplicacion\ReaccionarADocumentosDeDron;
use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Flota\Infraestructura\Busqueda\BusquedaDrones;
use App\Dominios\Operaciones\Contratos\Eventos\RecargaRegistrada;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoAprobado;
use App\Dominios\Proveedores\Contratos\Eventos\DocumentoVencido;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Flota` (ADR 0029, ADR 0036): contratos de lectura para
 * `Proveedores` y la asignación, el namespace de vistas `flota::` y los
 * oyentes de otros módulos.
 *
 * - `RecargaRegistrada` (Operaciones): suma un ciclo de batería al dron del
 *   marketplace enlazado a la sesión.
 * - `DocumentoVencido` y `DocumentoAprobado` (Proveedores): suspenden y
 *   rehabilitan al dron según sus documentos.
 */
final class FlotaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaDrones::class, LecturaDronesEloquent::class);
        $this->app->bind(LecturaEquipos::class, LecturaEquiposEloquent::class);
        $this->app->bind(LecturaIntegrantesDeEquipo::class, LecturaIntegrantesDeEquipoEloquent::class);

        // Buscador global (`busqueda.proveedores`): el agregador de Seguridad
        // no conoce estas clases, las recibe por tag.
        $this->app->tag(BusquedaDrones::class, 'busqueda.proveedores');
    }

    public function boot(): void
    {
        View::addNamespace('flota', app_path('Dominios/Flota/Infraestructura/Http/Views'));

        Event::listen(function (RecargaRegistrada $evento): void {
            app(IncrementarCiclosBateriaDron::class)->ejecutar($evento->dronId);
        });

        Event::listen(function (DocumentoVencido $evento): void {
            app(ReaccionarADocumentosDeDron::class)->alVencer($evento);
        });

        Event::listen(function (DocumentoAprobado $evento): void {
            app(ReaccionarADocumentosDeDron::class)->alAprobar($evento);
        });
    }
}
