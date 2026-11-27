<?php

namespace App\Dominios\Proveedores\Infraestructura;

use App\Dominios\Proveedores\Aplicacion\ActualizarCalificacionDeProveedor;
use App\Dominios\Proveedores\Aplicacion\ActualizarTasasDeProveedor;
use App\Dominios\Proveedores\Aplicacion\CargarDocumento;
use App\Dominios\Proveedores\Aplicacion\EliminacionDeProveedor;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Aplicacion\ListarDocumentosDeSujeto;
use App\Dominios\Proveedores\Contratos\CalificacionDeProveedores;
use App\Dominios\Proveedores\Contratos\CargaDocumentos;
use App\Dominios\Proveedores\Contratos\LecturaDocumentos;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Contratos\ReputacionDeProveedores;
use App\Dominios\Proveedores\Contratos\VerificacionDocumental;
use App\Dominios\Proveedores\Infraestructura\Busqueda\BusquedaProveedores;
use App\Dominios\Proveedores\Infraestructura\Console\AsignarTitularPropioCommand;
use App\Dominios\Proveedores\Infraestructura\Console\ProcesarVencimientosCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Cablea el módulo `Proveedores` (ADR 0028, 0032 y 0036): los contratos que
 * `Flota` y la asignación consumen, el namespace de vistas `proveedores::` y el
 * comando diario de vencimientos.
 */
final class ProveedoresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Eliminación de cuenta (ADR 0047): lo que este módulo aporta; Seguridad lo recibe por tag.
        $this->app->tag(EliminacionDeProveedor::class, 'eliminacion.participantes');

        $this->app->bind(LecturaProveedores::class, LecturaProveedoresEloquent::class);
        $this->app->bind(CargaDocumentos::class, CargarDocumento::class);
        $this->app->bind(VerificacionDocumental::class, EstadoDocumentalDeSujeto::class);
        $this->app->bind(LecturaDocumentos::class, ListarDocumentosDeSujeto::class);
        $this->app->bind(ReputacionDeProveedores::class, ActualizarTasasDeProveedor::class);
        $this->app->bind(CalificacionDeProveedores::class, ActualizarCalificacionDeProveedor::class);

        // Buscador global (`busqueda.proveedores`): el agregador de Seguridad
        // no conoce estas clases, las recibe por tag.
        $this->app->tag(BusquedaProveedores::class, 'busqueda.proveedores');
    }

    public function boot(): void
    {
        View::addNamespace('proveedores', app_path('Dominios/Proveedores/Infraestructura/Http/Views'));

        if ($this->app->runningInConsole()) {
            $this->commands([ProcesarVencimientosCommand::class, AsignarTitularPropioCommand::class]);
        }
    }
}
