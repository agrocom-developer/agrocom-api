<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\CrearCombustible;
use App\Dominios\Finanzas\Aplicacion\EliminarCombustible;
use App\Dominios\Finanzas\Aplicacion\ListarCombustibles;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearCombustibleRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/DELETE /panel/combustible*` (HU-35, tarea 49): "como
 * encargado, quiero registrar el combustible del generador y de los
 * vehículos, para imputarlo a la campaña". Mismo molde que
 * `GastosController` — ABM acotado sin edición: alta, listado y baja
 * lógica.
 *
 * Tres permisos de grano fino (`finanzas.combustible.ver`/`.crear`/
 * `.eliminar`), verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 * Ninguna regla de negocio acá: el alta la hace
 * `Aplicacion/CrearCombustible`.
 *
 * El select de `base_id` se arma con `DB::table` directo (ADR 0003 regla
 * 3, mismo criterio que `GastosController::basesDisponibles()`), sin
 * importar el modelo Eloquent de `Personal`.
 */
final class CombustibleController
{
    private const PERMISO_VER = 'finanzas.combustible.ver';

    private const PERMISO_CREAR = 'finanzas.combustible.crear';

    private const PERMISO_ELIMINAR = 'finanzas.combustible.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarCombustibles $listarCombustibles): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $baseId = $request->integer('base_id') ?: null;
        $desde = $request->string('desde')->toString();
        $hasta = $request->string('hasta')->toString();

        $combustibles = $listarCombustibles->ejecutar(
            $baseId,
            $desde !== '' ? $desde : null,
            $hasta !== '' ? $hasta : null,
        );
        $basesDisponibles = $this->basesDisponibles();

        return view('finanzas::pages.combustible.index', [
            ...$this->autorizacion->cascara($request),
            'combustibles' => $combustibles,
            'etiquetasBase' => $basesDisponibles->all(),
            'basesDisponibles' => $basesDisponibles,
            'filtros' => ['base_id' => $baseId, 'desde' => $desde, 'hasta' => $hasta],
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('finanzas::pages.combustible.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
        ]);
    }

    public function store(CrearCombustibleRequest $request, CrearCombustible $crearCombustible): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $crearCombustible->ejecutar(
            (string) $datos['fecha'],
            (int) $datos['base_id'],
            (string) $datos['destino'],
            (string) $datos['litros'],
            (string) $datos['monto'],
            $datos['descripcion'] ?? null,
        );

        return redirect()
            ->route('panel.combustible.index')
            ->with('estado', __('finanzas.combustible.creado'));
    }

    public function destroy(Request $request, Combustible $combustible, EliminarCombustible $eliminarCombustible): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarCombustible->ejecutar($combustible);

        return redirect()
            ->route('panel.combustible.index')
            ->with('estado', __('finanzas.combustible.eliminado'));
    }

    /** @return Collection<int, string> */
    private function basesDisponibles(): Collection
    {
        return DB::table('per_bases')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn (string $nombre, int|string $id): array => [(int) $id => $nombre]);
    }
}
