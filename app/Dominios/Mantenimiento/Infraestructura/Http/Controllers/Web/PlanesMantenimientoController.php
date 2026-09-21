<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Mantenimiento\Aplicacion\ActualizarPlanMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\CrearPlanMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\EliminarPlanMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\ListarPlanesMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarPlanMantenimientoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearPlanMantenimientoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\ResumenRelacionadoDePlan;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/planes-mantenimiento*` (HU-38, tarea 54): alta
 * y mantenimiento de los planes de mantenimiento preventivo por horas de
 * vuelo. Mismo molde que `BateriasController`, sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.plan.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo, incluida la alerta que
 * calcula `ListarPlanesMantenimiento`.
 *
 * No hay apertura automática de una orden de mantenimiento al cruzar el
 * umbral (fuera de alcance de esta HU, ver docblock de
 * `ListarPlanesMantenimiento`): la alerta es solo informativa, la acción de
 * abrir la orden queda manual en la pantalla de `OrdenesMantenimientoController`.
 * Por eso el resumen relacionado de la ficha ({@see ResumenRelacionadoDePlan},
 * tarea 116) habla de los drones del modelo y de SUS órdenes, nunca de
 * «órdenes generadas por el plan»: esa trazabilidad no existe en el esquema.
 */
final class PlanesMantenimientoController
{
    private const PERMISO_VER = 'mantenimiento.plan.ver';

    private const PERMISO_CREAR = 'mantenimiento.plan.crear';

    private const PERMISO_EDITAR = 'mantenimiento.plan.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.plan.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarPlanesMantenimiento $listarPlanesMantenimiento): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = trim($request->string('q')->toString());

        return view('mantenimiento::pages.planes.index', [
            ...$this->autorizacion->cascara($request),
            'planes' => $listarPlanesMantenimiento->ejecutar($busqueda),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.planes.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearPlanMantenimientoRequest $request, CrearPlanMantenimiento $crearPlan): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $plan = $crearPlan->ejecutar(
            (string) $datos['modelo'],
            (string) $datos['tarea'],
            (string) $datos['horas_umbral'],
        );

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.planes-mantenimiento.edit', $plan)
            ->with('estado', __('mantenimiento.planes.creado'));
    }

    public function edit(Request $request, PlanMantenimiento $plan, ResumenRelacionadoDePlan $resumenRelacionado): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.planes.edit', [
            ...$this->autorizacion->cascara($request),
            'plan' => $plan,
            'resumenRelacionado' => $resumenRelacionado->tarjetas($request, $plan),
        ]);
    }

    public function update(ActualizarPlanMantenimientoRequest $request, PlanMantenimiento $plan, ActualizarPlanMantenimiento $actualizarPlan): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        $actualizarPlan->ejecutar(
            $plan,
            (string) $datos['modelo'],
            (string) $datos['tarea'],
            (string) $datos['horas_umbral'],
        );

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.planes-mantenimiento.edit', $plan)
            ->with('estado', __('mantenimiento.planes.actualizado'));
    }

    public function destroy(Request $request, PlanMantenimiento $plan, EliminarPlanMantenimiento $eliminarPlan): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarPlan->ejecutar($plan);

        return redirect()
            ->route('panel.planes-mantenimiento.index')
            ->with('estado', __('mantenimiento.planes.eliminado'));
    }
}
