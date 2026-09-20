<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\LecturaActaConformada;
use App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria;
use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel;
use App\Dominios\Operaciones\Contratos\LecturaDesempenioPersona;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Operaciones\Contratos\LecturaEstadiasPorVehiculo;
use App\Dominios\Operaciones\Contratos\LecturaHorasVueloPorModelo;
use App\Dominios\Operaciones\Contratos\LecturaLotesConOrdenPorContrato;
use App\Dominios\Operaciones\Contratos\LecturaOrdenesVigentes;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Operaciones\Contratos\LecturaRecargasPorBateria;
use App\Dominios\Operaciones\Contratos\LecturaReporteTecnico;
use App\Dominios\Operaciones\Contratos\LecturaResumenCuadrilla;
use App\Dominios\Operaciones\Contratos\LecturaResumenOrdenesContrato;
use App\Dominios\Operaciones\Contratos\LecturaSesionesPorPersona;
use App\Dominios\Operaciones\Contratos\LecturaSesionValidada;
use App\Dominios\Operaciones\Contratos\LecturaTrabajos;
use App\Dominios\Operaciones\Contratos\LecturaTrabajosAsignados;
use App\Dominios\Operaciones\Contratos\LecturaTrabajosPorContrato;
use App\Dominios\Operaciones\Infraestructura\Busqueda\BusquedaDrones;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Liga los contratos del módulo a su implementación Eloquent (ADR 0003,
 * regla 2). Mismo patrón que `SeguridadServiceProvider` — cada módulo
 * registra su propio provider para lo que el contenedor no resuelve por
 * convención (una interfaz no se autoresuelve sola).
 *
 * `boot()` registra el namespace de vista `operaciones::` (HU-05, tarea 13;
 * mismo patrón que `DistribucionServiceProvider`): las páginas Blade del
 * módulo viven bajo `Infraestructura/Http/Views/`, no bajo `resources/views/`.
 */
final class OperacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LecturaOrdenesVigentes::class, LecturaOrdenesVigentesEloquent::class);
        $this->app->bind(LecturaTrabajosAsignados::class, LecturaTrabajosAsignadosEloquent::class);
        $this->app->bind(EscrituraSincronizacion::class, EscrituraSincronizacionEloquent::class);
        $this->app->bind(LecturaSesionValidada::class, LecturaSesionValidadaEloquent::class);
        $this->app->bind(LecturaActaConformada::class, LecturaActaConformadaEloquent::class);
        $this->app->bind(LecturaReporteTecnico::class, LecturaReporteTecnicoEloquent::class);
        $this->app->bind(LecturaAlertasTemperaturaBateria::class, LecturaAlertasTemperaturaBateriaEloquent::class);
        $this->app->bind(LecturaHorasVueloPorModelo::class, LecturaHorasVueloPorModeloEloquent::class);
        $this->app->bind(LecturaContadoresPanel::class, LecturaContadoresPanelEloquent::class);
        $this->app->bind(LecturaPanelOperaciones::class, LecturaPanelOperacionesEloquent::class);
        $this->app->bind(LecturaDesempenioPersona::class, LecturaDesempenioPersonaEloquent::class);
        $this->app->bind(LecturaTrabajos::class, LecturaTrabajosEloquent::class);
        $this->app->bind(LecturaResumenOrdenesContrato::class, LecturaResumenOrdenesContratoEloquent::class);
        $this->app->bind(LecturaTrabajosPorContrato::class, LecturaTrabajosPorContratoEloquent::class);
        $this->app->bind(LecturaLotesConOrdenPorContrato::class, LecturaLotesConOrdenPorContratoEloquent::class);
        $this->app->bind(LecturaDrones::class, LecturaDronesEloquent::class);
        $this->app->bind(LecturaResumenCuadrilla::class, LecturaResumenCuadrillaEloquent::class);
        $this->app->bind(LecturaSesionesPorPersona::class, LecturaSesionesPorPersonaEloquent::class);
        $this->app->bind(LecturaRecargasPorBateria::class, LecturaRecargasPorBateriaEloquent::class);
        $this->app->bind(LecturaEstadiasPorVehiculo::class, LecturaEstadiasPorVehiculoEloquent::class);

        // Buscador global (`busqueda.proveedores`): el agregador de Seguridad
        // no conoce estas clases, las recibe por tag. Sumar una entidad al
        // buscador es escribir su proveedor y taggearlo acá.
        $this->app->tag(BusquedaDrones::class, 'busqueda.proveedores');
    }

    public function boot(): void
    {
        View::addNamespace('operaciones', app_path('Dominios/Operaciones/Infraestructura/Http/Views'));
    }
}
