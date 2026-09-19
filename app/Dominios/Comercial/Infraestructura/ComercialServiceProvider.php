<?php

namespace App\Dominios\Comercial\Infraestructura;

use App\Dominios\Comercial\Aplicacion\FinalizarContratoPorUltimaAplicacion;
use App\Dominios\Comercial\Contratos\LecturaAvanceComercial;
use App\Dominios\Comercial\Contratos\LecturaContadoresPanel;
use App\Dominios\Comercial\Contratos\LecturaContrato;
use App\Dominios\Comercial\Contratos\LecturaCultivoLote;
use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Comercial\Contratos\LecturaPanelComercial;
use App\Dominios\Comercial\Contratos\LecturaResumenComercialCampania;
use App\Dominios\Comercial\Infraestructura\Busqueda\BusquedaClientes;
use App\Dominios\Comercial\Infraestructura\Busqueda\BusquedaCultivos;
use App\Dominios\Comercial\Infraestructura\Busqueda\BusquedaLotes;
use App\Dominios\Comercial\Infraestructura\Busqueda\BusquedaPropiedades;
use App\Dominios\Operaciones\Contratos\Eventos\AplicacionCerrada;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga el contrato de lectura del módulo a su implementación Eloquent (ADR
 * 0003, regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 *
 * `boot()` registra el namespace de vista `comercial::` (HU-22, tarea 33;
 * mismo patrón que `OperacionesServiceProvider`): las páginas Blade del
 * módulo viven bajo `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 *
 * También cablea el oyente real de `AplicacionCerrada` (ADR 0022): al cerrarse
 * la última aplicación del contrato, este pasa a `finalizado` y libera sus
 * lotes. Mismo patrón que `FinanzasServiceProvider` con `SesionValidada`: un
 * closure resuelto por el contenedor, sin clase de listener aparte.
 */
final class ComercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaLotes::class, LecturaLotesEloquent::class);
        $this->app->bind(LecturaContrato::class, LecturaContratoEloquent::class);
        $this->app->bind(LecturaContadoresPanel::class, LecturaContadoresPanelEloquent::class);
        $this->app->bind(LecturaPanelComercial::class, LecturaPanelComercialEloquent::class);
        $this->app->bind(LecturaCultivoLote::class, LecturaCultivoLoteEloquent::class);
        $this->app->bind(LecturaAvanceComercial::class, LecturaAvanceComercialEloquent::class);
        $this->app->bind(LecturaResumenComercialCampania::class, LecturaResumenComercialCampaniaEloquent::class);

        // Buscador global (`busqueda.proveedores`): el agregador de Seguridad
        // no conoce estas clases, las recibe por tag. Sumar una entidad al
        // buscador es escribir su proveedor y taggearlo acá.
        $this->app->tag(BusquedaClientes::class, 'busqueda.proveedores');
        $this->app->tag(BusquedaPropiedades::class, 'busqueda.proveedores');
        $this->app->tag(BusquedaLotes::class, 'busqueda.proveedores');
        $this->app->tag(BusquedaCultivos::class, 'busqueda.proveedores');
    }

    public function boot(): void
    {
        Event::listen(function (AplicacionCerrada $evento): void {
            app(FinalizarContratoPorUltimaAplicacion::class)->ejecutar($evento->contratoId, $evento->nroAplicacion);
        });

        View::addNamespace('comercial', app_path('Dominios/Comercial/Infraestructura/Http/Views'));
    }
}
