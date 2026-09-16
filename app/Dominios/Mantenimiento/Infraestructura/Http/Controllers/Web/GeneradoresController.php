<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Mantenimiento\Aplicacion\ActualizarGenerador;
use App\Dominios\Mantenimiento\Aplicacion\CrearGenerador;
use App\Dominios\Mantenimiento\Aplicacion\EliminarGenerador;
use App\Dominios\Mantenimiento\Aplicacion\ListarGeneradores;
use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\Excepciones\GeneradorDuplicado;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarGeneradorRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearGeneradorRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/generadores*` (tarea 72, HU-49): ABM mínimo
 * del catálogo de generadores. Mismo molde que `VehiculosController`, sin
 * sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.generador.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 *
 * El select de `base_id` se arma con `DB::table('per_bases')` (ADR 0003
 * regla 3, mismo criterio que `VehiculosController`), sin importar el
 * modelo Eloquent `PerBase` de `Personal`.
 */
final class GeneradoresController
{
    private const PERMISO_VER = 'mantenimiento.generador.ver';

    private const PERMISO_CREAR = 'mantenimiento.generador.crear';

    private const PERMISO_EDITAR = 'mantenimiento.generador.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.generador.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarGeneradores $listarGeneradores): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $baseQuery = $request->string('base_id')->toString();
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoGenerador::tryFrom($estadoQuery) : null;

        $generadores = $listarGeneradores->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('mantenimiento::pages.generadores.index', [
            ...$this->autorizacion->cascara($request),
            'generadores' => $generadores,
            'etiquetasBase' => $this->etiquetasBase($generadores->pluck('base_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.generadores.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoGenerador::cases(),
        ]);
    }

    public function store(CrearGeneradorRequest $request, CrearGenerador $crearGenerador): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $generador = $crearGenerador->ejecutar(
                (string) $datos['identificador'],
                $this->stringONull($datos['modelo'] ?? null),
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoGenerador::from((string) $datos['estado']),
                $this->stringONull($datos['horas_inicial'] ?? null),
                $this->stringONull($datos['horas_actual'] ?? null),
            );
        } catch (GeneradorDuplicado $excepcion) {
            return redirect()
                ->route('panel.generadores.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.generadores.edit', $generador)
            ->with('estado', __('mantenimiento.generadores.creado'));
    }

    public function edit(Request $request, Generador $generador): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.generadores.edit', [
            ...$this->autorizacion->cascara($request),
            'generador' => $generador,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoGenerador::cases(),
        ]);
    }

    public function update(ActualizarGeneradorRequest $request, Generador $generador, ActualizarGenerador $actualizarGenerador): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarGenerador->ejecutar(
                $generador,
                (string) $datos['identificador'],
                $this->stringONull($datos['modelo'] ?? null),
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoGenerador::from((string) $datos['estado']),
                $this->stringONull($datos['horas_inicial'] ?? null),
                $this->stringONull($datos['horas_actual'] ?? null),
            );
        } catch (GeneradorDuplicado $excepcion) {
            return redirect()
                ->route('panel.generadores.edit', $generador)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.generadores.edit', $generador)
            ->with('estado', __('mantenimiento.generadores.actualizado'));
    }

    public function destroy(Request $request, Generador $generador, EliminarGenerador $eliminarGenerador): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarGenerador->ejecutar($generador);

        return redirect()
            ->route('panel.generadores.index')
            ->with('estado', __('mantenimiento.generadores.eliminado'));
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function stringONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
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
     * borrada lógicamente después de asignada a un generador queda fuera del
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
