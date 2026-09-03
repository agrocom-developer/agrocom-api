<?php

namespace App\Dominios\Personal\Infraestructura\Http\Controllers\Web;

use App\Dominios\Personal\Aplicacion\ActualizarPersona;
use App\Dominios\Personal\Aplicacion\CrearPersona;
use App\Dominios\Personal\Aplicacion\EliminarPersona;
use App\Dominios\Personal\Aplicacion\ListarPersonas;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use App\Dominios\Personal\Infraestructura\Http\Requests\ActualizarPersonaRequest;
use App\Dominios\Personal\Infraestructura\Http\Requests\CrearPersonaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/personas*` (HU-26, tarea 37): alta y
 * mantenimiento de personas operativas, con su rol y tarifa por hectárea.
 * Mismo molde que `BasesController` (misma tarea), pero con un `select`
 * adicional de base (opcional) — mismo criterio que `cliente_id` en
 * `CamposController`.
 *
 * Cuatro permisos de grano fino
 * (`personal.persona.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo. Editar `tarifa_ha` acá NO
 * afecta devengos ya generados — `Finanzas/GenerarDevengosSesion` congela su
 * propia copia al validarse la sesión (ver `ActualizarPersona`).
 */
final class PersonasController
{
    private const PERMISO_VER = 'personal.persona.ver';

    private const PERMISO_CREAR = 'personal.persona.crear';

    private const PERMISO_EDITAR = 'personal.persona.editar';

    private const PERMISO_ELIMINAR = 'personal.persona.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPersonas $listarPersonas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('personal::pages.personas.index', [
            ...$this->autorizacion->cascara($request),
            'personas' => $listarPersonas->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('personal::pages.personas.create', [
            ...$this->autorizacion->cascara($request),
            'roles' => RolOperativoPersona::cases(),
            'basesDisponibles' => $this->basesActivas(),
        ]);
    }

    public function store(CrearPersonaRequest $request, CrearPersona $crearPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $crearPersona->ejecutar(
            (string) $datos['nombre'],
            RolOperativoPersona::from((string) $datos['rol']),
            isset($datos['base_id']) && $datos['base_id'] !== '' ? (int) $datos['base_id'] : null,
            $this->cadenaONull($datos['tarifa_ha'] ?? null),
            (bool) ($datos['activo'] ?? false),
        );

        return redirect()
            ->route('panel.personas.index')
            ->with('estado', __('personal.personas.creado'));
    }

    public function edit(Request $request, PerPersona $persona): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('personal::pages.personas.edit', [
            ...$this->autorizacion->cascara($request),
            'persona' => $persona,
            'roles' => RolOperativoPersona::cases(),
            'basesDisponibles' => $this->basesActivas(),
        ]);
    }

    public function update(ActualizarPersonaRequest $request, PerPersona $persona, ActualizarPersona $actualizarPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        $actualizarPersona->ejecutar(
            $persona,
            (string) $datos['nombre'],
            RolOperativoPersona::from((string) $datos['rol']),
            isset($datos['base_id']) && $datos['base_id'] !== '' ? (int) $datos['base_id'] : null,
            $this->cadenaONull($datos['tarifa_ha'] ?? null),
            (bool) ($datos['activo'] ?? false),
        );

        return redirect()
            ->route('panel.personas.index')
            ->with('estado', __('personal.personas.actualizado'));
    }

    public function destroy(Request $request, PerPersona $persona, EliminarPersona $eliminarPersona): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarPersona->ejecutar($persona);

        return redirect()
            ->route('panel.personas.index')
            ->with('estado', __('personal.personas.eliminado'));
    }

    /** @return Collection<int, string> */
    private function basesActivas(): Collection
    {
        return PerBase::query()->orderBy('nombre')->pluck('nombre', 'id');
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
