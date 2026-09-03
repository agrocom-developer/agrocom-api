<?php

namespace App\Dominios\Inventario\Infraestructura\Http\Controllers\Web;

use App\Dominios\Inventario\Aplicacion\ActualizarRepuesto;
use App\Dominios\Inventario\Aplicacion\CrearRepuesto;
use App\Dominios\Inventario\Aplicacion\EliminarRepuesto;
use App\Dominios\Inventario\Aplicacion\ListarRepuestos;
use App\Dominios\Inventario\Dominio\Excepciones\RepuestoDuplicado;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Http\Requests\ActualizarRepuestoRequest;
use App\Dominios\Inventario\Infraestructura\Http\Requests\CrearRepuestoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/repuestos*` (HU-36, tarea 52): alta y
 * mantenimiento del catálogo de repuestos. Mismo molde que
 * `BateriasController`, sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`inventario.repuesto.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 */
final class RepuestosController
{
    private const PERMISO_VER = 'inventario.repuesto.ver';

    private const PERMISO_CREAR = 'inventario.repuesto.crear';

    private const PERMISO_EDITAR = 'inventario.repuesto.editar';

    private const PERMISO_ELIMINAR = 'inventario.repuesto.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarRepuestos $listarRepuestos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        $repuestos = $listarRepuestos->ejecutar(busqueda: $busqueda !== '' ? $busqueda : null);

        return view('inventario::pages.repuestos.index', [
            ...$this->autorizacion->cascara($request),
            'repuestos' => $repuestos,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('inventario::pages.repuestos.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearRepuestoRequest $request, CrearRepuesto $crearRepuesto): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearRepuesto->ejecutar(
                (string) $datos['codigo'],
                (string) $datos['descripcion'],
                (string) $datos['unidad'],
                $this->decimalONull($datos['costo_unitario'] ?? null),
            );
        } catch (RepuestoDuplicado $excepcion) {
            return redirect()
                ->route('panel.repuestos.create')
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.repuestos.index')
            ->with('estado', __('inventario.repuestos.creado'));
    }

    public function edit(Request $request, Repuesto $repuesto): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('inventario::pages.repuestos.edit', [
            ...$this->autorizacion->cascara($request),
            'repuesto' => $repuesto,
        ]);
    }

    public function update(ActualizarRepuestoRequest $request, Repuesto $repuesto, ActualizarRepuesto $actualizarRepuesto): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarRepuesto->ejecutar(
                $repuesto,
                (string) $datos['codigo'],
                (string) $datos['descripcion'],
                (string) $datos['unidad'],
                $this->decimalONull($datos['costo_unitario'] ?? null),
            );
        } catch (RepuestoDuplicado $excepcion) {
            return redirect()
                ->route('panel.repuestos.edit', $repuesto)
                ->withInput()
                ->withErrors(['codigo' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.repuestos.index')
            ->with('estado', __('inventario.repuestos.actualizado'));
    }

    public function destroy(Request $request, Repuesto $repuesto, EliminarRepuesto $eliminarRepuesto): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarRepuesto->ejecutar($repuesto);

        return redirect()
            ->route('panel.repuestos.index')
            ->with('estado', __('inventario.repuestos.eliminado'));
    }

    private function decimalONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
