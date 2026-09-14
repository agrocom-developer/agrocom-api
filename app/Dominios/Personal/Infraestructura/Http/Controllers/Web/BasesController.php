<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Personal\Aplicacion\ActualizarBase;
use App\Dominios\Personal\Aplicacion\CrearBase;
use App\Dominios\Personal\Aplicacion\EliminarBase;
use App\Dominios\Personal\Aplicacion\ListarBases;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarBaseRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearBaseRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/bases*` (HU-26, tarea 37): alta y
 * mantenimiento de bases operativas. Mismo molde que `DronesController`
 * (HU-27, tarea 36) — ABM simple, sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`personal.base.ver`/`.crear`/`.editar`/`.eliminar`), verificados DENTRO
 * del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} —
 * mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 */
final class BasesController
{
    private const PERMISO_VER = 'personal.base.ver';

    private const PERMISO_CREAR = 'personal.base.crear';

    private const PERMISO_EDITAR = 'personal.base.editar';

    private const PERMISO_ELIMINAR = 'personal.base.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarBases $listarBases): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('personal::pages.bases.index', [
            ...$this->autorizacion->cascara($request),
            'bases' => $listarBases->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.bases.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearBaseRequest $request, CrearBase $crearBase): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $crearBase->ejecutar(
            (string) $datos['nombre'],
            $this->cadenaONull($datos['ubicacion'] ?? null),
            $this->cadenaONull($datos['latitud'] ?? null),
            $this->cadenaONull($datos['longitud'] ?? null),
        );

        return redirect()
            ->route('panel.bases.index')
            ->with('estado', __('personal.bases.creado'));
    }

    public function edit(Request $request, PerBase $base): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('personal::pages.bases.edit', [
            ...$this->autorizacion->cascara($request),
            'base' => $base,
        ]);
    }

    public function update(ActualizarBaseRequest $request, PerBase $base, ActualizarBase $actualizarBase): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        $actualizarBase->ejecutar(
            $base,
            (string) $datos['nombre'],
            $this->cadenaONull($datos['ubicacion'] ?? null),
            $this->cadenaONull($datos['latitud'] ?? null),
            $this->cadenaONull($datos['longitud'] ?? null),
        );

        return redirect()
            ->route('panel.bases.index')
            ->with('estado', __('personal.bases.actualizado'));
    }

    public function destroy(Request $request, PerBase $base, EliminarBase $eliminarBase): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarBase->ejecutar($base);

        return redirect()
            ->route('panel.bases.index')
            ->with('estado', __('personal.bases.eliminado'));
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
