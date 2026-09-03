<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Mantenimiento\Aplicacion\ActualizarVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\CrearVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\EliminarVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\ListarVehiculos;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\Excepciones\VehiculoDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarVehiculoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearVehiculoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/vehiculos*` (HU-40, tarea 50): alta y
 * mantenimiento de la flota de vehículos, con su asignación a base y estado.
 * Mismo molde que `DronesController` (Operaciones), sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.vehiculo.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 *
 * El select de `base_id` se arma con `DB::table('per_bases')` (ADR 0003
 * regla 3, mismo criterio que los selects de `OrdenesController`), sin
 * importar el modelo Eloquent `PerBase` de `Personal` — `Vehiculo` y
 * `PerBase` son de módulos distintos.
 */
final class VehiculosController
{
    private const PERMISO_VER = 'mantenimiento.vehiculo.ver';

    private const PERMISO_CREAR = 'mantenimiento.vehiculo.crear';

    private const PERMISO_EDITAR = 'mantenimiento.vehiculo.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.vehiculo.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarVehiculos $listarVehiculos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $baseQuery = $request->string('base_id')->toString();
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoVehiculo::tryFrom($estadoQuery) : null;

        $vehiculos = $listarVehiculos->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('mantenimiento::pages.vehiculos.index', [
            ...$this->autorizacion->cascara($request),
            'vehiculos' => $vehiculos,
            'etiquetasBase' => $this->etiquetasBase($vehiculos->pluck('base_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.vehiculos.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoVehiculo::cases(),
        ]);
    }

    public function store(CrearVehiculoRequest $request, CrearVehiculo $crearVehiculo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearVehiculo->ejecutar(
                (string) $datos['identificador'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoVehiculo::from((string) $datos['estado']),
            );
        } catch (VehiculoDuplicado $excepcion) {
            return redirect()
                ->route('panel.vehiculos.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.vehiculos.index')
            ->with('estado', __('mantenimiento.vehiculos.creado'));
    }

    public function edit(Request $request, Vehiculo $vehiculo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.vehiculos.edit', [
            ...$this->autorizacion->cascara($request),
            'vehiculo' => $vehiculo,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoVehiculo::cases(),
        ]);
    }

    public function update(ActualizarVehiculoRequest $request, Vehiculo $vehiculo, ActualizarVehiculo $actualizarVehiculo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarVehiculo->ejecutar(
                $vehiculo,
                (string) $datos['identificador'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoVehiculo::from((string) $datos['estado']),
            );
        } catch (VehiculoDuplicado $excepcion) {
            return redirect()
                ->route('panel.vehiculos.edit', $vehiculo)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.vehiculos.index')
            ->with('estado', __('mantenimiento.vehiculos.actualizado'));
    }

    public function destroy(Request $request, Vehiculo $vehiculo, EliminarVehiculo $eliminarVehiculo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarVehiculo->ejecutar($vehiculo);

        return redirect()
            ->route('panel.vehiculos.index')
            ->with('estado', __('mantenimiento.vehiculos.eliminado'));
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    /** @return Collection<int, string> */
    private function basesDisponibles(): Collection
    {
        return DB::table('per_bases')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('nombre', 'id');
    }

    /**
     * Etiquetas legibles para la columna "Base" del listado (mismo criterio
     * de lectura directa por `DB::table` que `basesDisponibles()`). Una base
     * borrada lógicamente después de asignada a un vehículo queda fuera del
     * mapa a propósito: la vista cae al `#id` crudo.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasBase(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_bases')
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->map(fn ($nombre) => (string) $nombre)
            ->all();
    }
}
