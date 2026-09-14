<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Mantenimiento\Aplicacion\ActualizarBateria;
use App\Dominios\Mantenimiento\Aplicacion\CrearBateria;
use App\Dominios\Mantenimiento\Aplicacion\EliminarBateria;
use App\Dominios\Mantenimiento\Aplicacion\ListarBaterias;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\Excepciones\BateriaDuplicada;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarBateriaRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearBateriaRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/baterias*` (HU-39, tarea 51): alta y
 * mantenimiento del catálogo de baterías, con sus ciclos acumulados, estado
 * y asignación a base. Mismo molde que `VehiculosController`, sin
 * sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.bateria.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo, incluida la alerta de
 * retiro que calcula `ListarBaterias`.
 *
 * El select de `base_id` se arma con `DB::table('per_bases')` (ADR 0003
 * regla 3, mismo criterio que `VehiculosController`), sin importar el
 * modelo Eloquent `PerBase` de `Personal`.
 */
final class BateriasController
{
    private const PERMISO_VER = 'mantenimiento.bateria.ver';

    private const PERMISO_CREAR = 'mantenimiento.bateria.crear';

    private const PERMISO_EDITAR = 'mantenimiento.bateria.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.bateria.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarBaterias $listarBaterias): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $baseQuery = $request->string('base_id')->toString();
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoBateria::tryFrom($estadoQuery) : null;

        $baterias = $listarBaterias->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('mantenimiento::pages.baterias.index', [
            ...$this->autorizacion->cascara($request),
            'baterias' => $baterias,
            'etiquetasBase' => $this->etiquetasBase($baterias->pluck('base_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.baterias.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoBateria::cases(),
        ]);
    }

    public function store(CrearBateriaRequest $request, CrearBateria $crearBateria): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $crearBateria->ejecutar(
                (string) $datos['identificador'],
                (int) $datos['ciclos_inicial'],
                (int) $datos['ciclos_acumulados'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoBateria::from((string) $datos['estado']),
            );
        } catch (BateriaDuplicada $excepcion) {
            return redirect()
                ->route('panel.baterias.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.baterias.index')
            ->with('estado', __('mantenimiento.baterias.creado'));
    }

    public function edit(Request $request, Bateria $bateria): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.baterias.edit', [
            ...$this->autorizacion->cascara($request),
            'bateria' => $bateria,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoBateria::cases(),
        ]);
    }

    public function update(ActualizarBateriaRequest $request, Bateria $bateria, ActualizarBateria $actualizarBateria): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarBateria->ejecutar(
                $bateria,
                (string) $datos['identificador'],
                (int) $datos['ciclos_acumulados'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoBateria::from((string) $datos['estado']),
            );
        } catch (BateriaDuplicada $excepcion) {
            return redirect()
                ->route('panel.baterias.edit', $bateria)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.baterias.index')
            ->with('estado', __('mantenimiento.baterias.actualizado'));
    }

    public function destroy(Request $request, Bateria $bateria, EliminarBateria $eliminarBateria): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarBateria->ejecutar($bateria);

        return redirect()
            ->route('panel.baterias.index')
            ->with('estado', __('mantenimiento.baterias.eliminado'));
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
     * borrada lógicamente después de asignada a una batería queda fuera del
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
