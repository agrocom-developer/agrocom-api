<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ActualizarDron;
use App\Dominios\Operaciones\Aplicacion\CrearDron;
use App\Dominios\Operaciones\Aplicacion\EliminarDron;
use App\Dominios\Operaciones\Aplicacion\ListarDrones;
use App\Dominios\Operaciones\Dominio\Excepciones\DronDuplicado;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarDronRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearDronRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/drones*` (HU-27, tarea 36): alta y
 * mantenimiento de la flota de drones con su modelo y capacidad de carga.
 * Mismo molde que `ClientesController`/`CamposController` (HU-22/HU-24),
 * pero sin sub-entidad: un dron no tiene contactos ni lotes.
 *
 * Cuatro permisos de grano fino
 * (`operaciones.dron.ver`/`.crear`/`.editar`/`.eliminar`), verificados DENTRO
 * del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — mismo
 * criterio que el resto del panel. Ninguna regla de negocio acá: los casos de
 * uso de `Aplicacion/` hacen el trabajo.
 */
final class DronesController
{
    private const PERMISO_VER = 'operaciones.dron.ver';

    private const PERMISO_CREAR = 'operaciones.dron.crear';

    private const PERMISO_EDITAR = 'operaciones.dron.editar';

    private const PERMISO_ELIMINAR = 'operaciones.dron.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarDrones $listarDrones): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('operaciones::pages.drones.index', [
            ...$this->autorizacion->cascara($request),
            'drones' => $listarDrones->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('operaciones::pages.drones.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearDronRequest $request, CrearDron $crearDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearDron->ejecutar(
                (string) $datos['identificador'],
                $this->cadenaONull($datos['modelo'] ?? null),
                $this->cadenaONull($datos['capacidad_l'] ?? null),
            );
        } catch (DronDuplicado $excepcion) {
            return redirect()
                ->route('panel.drones.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.drones.index')
            ->with('estado', __('operaciones.drones.creado'));
    }

    public function edit(Request $request, Dron $dron): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('operaciones::pages.drones.edit', [
            ...$this->autorizacion->cascara($request),
            'dron' => $dron,
        ]);
    }

    public function update(ActualizarDronRequest $request, Dron $dron, ActualizarDron $actualizarDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarDron->ejecutar(
                $dron,
                (string) $datos['identificador'],
                $this->cadenaONull($datos['modelo'] ?? null),
                $this->cadenaONull($datos['capacidad_l'] ?? null),
            );
        } catch (DronDuplicado $excepcion) {
            return redirect()
                ->route('panel.drones.edit', $dron)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.drones.index')
            ->with('estado', __('operaciones.drones.actualizado'));
    }

    public function destroy(Request $request, Dron $dron, EliminarDron $eliminarDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarDron->ejecutar($dron);

        return redirect()
            ->route('panel.drones.index')
            ->with('estado', __('operaciones.drones.eliminado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
