<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarPropiedad;
use App\Dominios\Comercial\Aplicacion\CrearPropiedad;
use App\Dominios\Comercial\Aplicacion\EliminarPropiedad;
use App\Dominios\Comercial\Aplicacion\ListarPropiedades;
use App\Dominios\Comercial\Dominio\Excepciones\PropiedadConCamposAsociados;
use App\Dominios\Comercial\Dominio\Excepciones\PropiedadDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\ActualizarPropiedadRequest;
use App\Dominios\Comercial\Infraestructura\Http\Requests\CrearPropiedadRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/propiedades*` (ADR 0018): alta y mantenimiento
 * de propiedades — nivel de terreno entre `Cliente` y `Campo`. Mismo molde
 * que `CamposController` (HU-24, tarea 35), sin sub-entidad propia en esta
 * pantalla: los campos de una propiedad se cargan desde `CamposController`,
 * no acá.
 *
 * Cuatro permisos de grano fino
 * (`comercial.propiedad.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * Ninguna regla de negocio acá: los casos de uso de `Aplicacion/` hacen el
 * trabajo.
 */
final class PropiedadesController
{
    private const PERMISO_VER = 'comercial.propiedad.ver';

    private const PERMISO_CREAR = 'comercial.propiedad.crear';

    private const PERMISO_EDITAR = 'comercial.propiedad.editar';

    private const PERMISO_ELIMINAR = 'comercial.propiedad.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPropiedades $listarPropiedades): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('comercial::pages.propiedades.index', [
            ...$this->autorizacion->cascara($request),
            'propiedades' => $listarPropiedades->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('comercial::pages.propiedades.create', [
            ...$this->autorizacion->cascara($request),
            'clientesDisponibles' => $this->clientesActivos(),
        ]);
    }

    public function store(CrearPropiedadRequest $request, CrearPropiedad $crearPropiedad): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearPropiedad->ejecutar(
                (int) $datos['cliente_id'],
                (string) $datos['nombre'],
                $this->cadenaONull($datos['ubicacion'] ?? null),
            );
        } catch (PropiedadDuplicada $excepcion) {
            return redirect()
                ->route('panel.propiedades.create')
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.propiedades.index')
            ->with('estado', __('comercial.propiedades.creado'));
    }

    public function edit(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('comercial::pages.propiedades.edit', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad,
            'clientesDisponibles' => $this->clientesActivos(),
        ]);
    }

    public function update(ActualizarPropiedadRequest $request, Propiedad $propiedad, ActualizarPropiedad $actualizarPropiedad): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarPropiedad->ejecutar(
                $propiedad,
                (int) $datos['cliente_id'],
                (string) $datos['nombre'],
                $this->cadenaONull($datos['ubicacion'] ?? null),
            );
        } catch (PropiedadDuplicada $excepcion) {
            return redirect()
                ->route('panel.propiedades.edit', $propiedad)
                ->withInput()
                ->withErrors(['nombre' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.propiedades.index')
            ->with('estado', __('comercial.propiedades.actualizado'));
    }

    public function destroy(Request $request, Propiedad $propiedad, EliminarPropiedad $eliminarPropiedad): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarPropiedad->ejecutar($propiedad);
        } catch (PropiedadConCamposAsociados $excepcion) {
            return redirect()
                ->route('panel.propiedades.index')
                ->withErrors(['propiedad' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.propiedades.index')
            ->with('estado', __('comercial.propiedades.eliminado'));
    }

    /** @return Collection<int, string> */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id');
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
