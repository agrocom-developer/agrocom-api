<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\CrearCombustible;
use App\Dominios\Finanzas\Aplicacion\EliminarCombustible;
use App\Dominios\Finanzas\Aplicacion\ListarCombustibles;
use App\Dominios\Finanzas\Dominio\Excepciones\CampaniaCerrada;
use App\Dominios\Finanzas\Dominio\Excepciones\RecursoNoAsignadoAlEquipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Finanzas\Infraestructura\Http\Requests\CrearCombustibleRequest;
use App\Dominios\Personal\Contratos\DatosRecursoEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/DELETE /panel/combustible*` (HU-35, tarea 49; reescrito por la
 * tarea 73, HU-50): "como encargado, quiero registrar el combustible del
 * generador y de los vehículos, para imputarlo a la campaña" — ahora
 * imputado al equipo de trabajo y al recurso concreto que lo consumió.
 * Mismo molde que `GastosController` — ABM acotado sin edición: alta,
 * listado y baja lógica.
 *
 * Tres permisos de grano fino (`finanzas.combustible.ver`/`.crear`/
 * `.eliminar`), verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb} — mismo criterio que el resto del panel.
 * Ninguna regla de negocio acá: el alta, la guarda de campaña cerrada y la
 * guarda de "el recurso pertenecía al equipo esa fecha" viven en
 * `Aplicacion/CrearCombustible`.
 *
 * `equipo_trabajo_id`/`fecha` viajan como query string en el formulario de
 * alta (`GET /panel/combustible/crear?equipo_trabajo_id=...&fecha=...`),
 * mismo patrón de recarga completa que
 * `Personal\Infraestructura\Http\Controllers\Web\EquiposTrabajoController::show()`
 * (la ficha del equipo, que también responde "quién/qué tenía este equipo
 * ese día" recargando la página con `fecha` en la URL): al elegir equipo y
 * fecha, la página se recarga y el `<select>` de recurso se puebla SOLO con
 * lo que ese equipo tenía asignado ese día
 * (`LecturaEquipoTrabajo::recursosAFecha()`) — nunca el catálogo entero.
 *
 * El select de `base_id`/`equipo_trabajo_id` se arma con `DB::table` directo
 * (ADR 0003 regla 3, mismo criterio que
 * `GastosController::basesDisponibles()`), sin importar los modelos
 * Eloquent de `Personal`.
 */
final class CombustibleController
{
    private const PERMISO_VER = 'finanzas.combustible.ver';

    private const PERMISO_CREAR = 'finanzas.combustible.crear';

    private const PERMISO_ELIMINAR = 'finanzas.combustible.eliminar';

    /** @var array<string, string> */
    private const TABLA_POR_TIPO = [
        'dron' => 'ope_drones',
        'vehiculo' => 'man_vehiculos',
        'generador' => 'man_generadores',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarCombustibles $listarCombustibles): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $baseId = $request->integer('base_id') ?: null;
        $desde = $request->string('desde')->toString();
        $hasta = $request->string('hasta')->toString();
        $equipoTrabajoId = $request->integer('equipo_trabajo_id') ?: null;
        $campaniaId = $request->integer('campania_id') ?: null;
        $desdeFiltro = $desde !== '' ? $desde : null;
        $hastaFiltro = $hasta !== '' ? $hasta : null;

        $combustibles = $listarCombustibles->ejecutar($baseId, $desdeFiltro, $hastaFiltro, $equipoTrabajoId, $campaniaId);
        $basesDisponibles = $this->basesDisponibles();
        $equiposDisponibles = $this->equiposDisponibles();

        return view('finanzas::pages.combustible.index', [
            ...$this->autorizacion->cascara($request),
            'combustibles' => $combustibles,
            'etiquetasBase' => $basesDisponibles->all(),
            'etiquetasRecurso' => $this->etiquetasRecursoDeCombustibles($combustibles->getCollection()),
            'basesDisponibles' => $basesDisponibles,
            'equiposDisponibles' => $equiposDisponibles,
            'campaniasDisponibles' => $this->todasLasCampanias(),
            'filtros' => [
                'base_id' => $baseId,
                'desde' => $desde,
                'hasta' => $hasta,
                'equipo_trabajo_id' => $equipoTrabajoId,
                'campania_id' => $campaniaId,
            ],
            'total' => $equipoTrabajoId !== null
                ? $listarCombustibles->total($baseId, $desdeFiltro, $hastaFiltro, $equipoTrabajoId, $campaniaId)
                : null,
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function create(Request $request, LecturaEquipoTrabajo $lectura): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $equipoTrabajoId = $request->integer('equipo_trabajo_id') ?: null;
        $fechaQuery = $request->string('fecha')->toString();
        $fecha = $fechaQuery !== '' ? $fechaQuery : now()->toDateString();

        return view('finanzas::pages.combustible.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'equiposDisponibles' => $this->equiposDisponibles(),
            'campaniasDisponibles' => $this->campaniasNoCerradas(),
            'equipoTrabajoIdSeleccionado' => $equipoTrabajoId,
            'fechaSeleccionada' => $fecha,
            'recursosDisponibles' => $equipoTrabajoId !== null
                ? $this->recursosDisponibles($lectura, $equipoTrabajoId, $fecha)
                : collect(),
        ]);
    }

    public function store(CrearCombustibleRequest $request, CrearCombustible $crearCombustible): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();
        [$recursoTipo, $recursoId] = explode(':', (string) $datos['recurso'], 2);

        try {
            $crearCombustible->ejecutar(
                fecha: (string) $datos['fecha'],
                baseId: (int) $datos['base_id'],
                equipoTrabajoId: (int) $datos['equipo_trabajo_id'],
                campaniaId: isset($datos['campania_id']) ? (int) $datos['campania_id'] : null,
                recursoTipo: $recursoTipo,
                recursoId: (int) $recursoId,
                litros: (string) $datos['litros'],
                monto: (string) $datos['monto'],
                descripcion: $datos['descripcion'] ?? null,
            );
        } catch (CampaniaCerrada $excepcion) {
            return redirect()
                ->route('panel.combustible.create', $this->parametrosCascada($datos))
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        } catch (RecursoNoAsignadoAlEquipo $excepcion) {
            return redirect()
                ->route('panel.combustible.create', $this->parametrosCascada($datos))
                ->withInput()
                ->withErrors(['recurso' => $excepcion->getMessage()]);
        }

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

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function parametrosCascada(array $datos): array
    {
        return array_filter([
            'equipo_trabajo_id' => $datos['equipo_trabajo_id'] ?? null,
            'fecha' => $datos['fecha'] ?? null,
        ]);
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

    /**
     * Equipos de trabajo (tarea 73, HU-50): elegir el equipo es el primer
     * paso del formulario — de él sale, junto con la fecha, qué recursos
     * ofrecer. `DB::table` directo (ADR 0003 regla 3).
     *
     * @return Collection<int, string>
     */
    private function equiposDisponibles(): Collection
    {
        return DB::table('per_equipos_trabajo')
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->mapWithKeys(fn (object $equipo): array => [
                (int) $equipo->id => $equipo->nombre !== null ? "{$equipo->codigo} — {$equipo->nombre}" : $equipo->codigo,
            ]);
    }

    /**
     * Campañas no cerradas (ADR 0015 punto 6): mismo criterio que
     * `GastosController::campaniasNoCerradas()` — imputar a una cerrada lo
     * rechaza igual `Aplicacion/CrearCombustible`, esto es solo para no
     * ofrecerla en el formulario.
     *
     * Sin `cliente_id`/`com_clientes` (ADR 0015, corregido el 15/9/2026): la
     * campaña es un catálogo compartido, sin cliente propio.
     *
     * @return Collection<int, non-falsy-string>
     */
    private function campaniasNoCerradas(): Collection
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->where('estado', '!=', 'cerrada')
            ->orderBy('codigo')
            ->pluck('codigo', 'id');
    }

    /**
     * TODAS las campañas (activas), sin filtrar por estado — a diferencia
     * de `campaniasNoCerradas()` (solo para el formulario de alta), el
     * filtro del LISTADO tiene que poder encontrar cargas de una campaña ya
     * `cerrada`: mismo criterio que `GastosController::todasLasCampanias()`.
     *
     * Sin `cliente_id`/`com_clientes` (ADR 0015, corregido el 15/9/2026):
     * mismo motivo que {@see self::campaniasNoCerradas()}.
     *
     * @return Collection<int, non-falsy-string>
     */
    private function todasLasCampanias(): Collection
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->pluck('codigo', 'id');
    }

    /**
     * Recursos que el equipo elegido tenía asignados en la fecha elegida
     * (tarea 73, HU-50) — nunca el catálogo entero. Clave compuesta
     * `"{tipo}:{id}"` (tarea 73, ver docblock de `CrearCombustibleRequest`):
     * el mismo id numérico puede repetirse entre tablas de recurso.
     *
     * @return Collection<string, string>
     */
    private function recursosDisponibles(LecturaEquipoTrabajo $lectura, int $equipoTrabajoId, string $fecha): Collection
    {
        $recursos = $lectura->recursosAFecha($equipoTrabajoId, $fecha);
        $etiquetas = $this->etiquetasRecurso($recursos);

        return collect($recursos)->mapWithKeys(function (DatosRecursoEquipo $recurso): array {
            $clave = "{$recurso->recursoTipo}:{$recurso->recursoId}";

            return [$clave => $clave];
        })->map(fn (string $clave) => $etiquetas[$clave] ?? $clave);
    }

    /**
     * Etiquetas legibles de los recursos de un listado ya paginado de
     * combustible — acotadas a la página actual (mismo criterio que
     * `GastosController::etiquetasTrabajo()`).
     *
     * @param  Collection<int, Combustible>  $combustibles
     * @return array<string, string>
     */
    private function etiquetasRecursoDeCombustibles(Collection $combustibles): array
    {
        /** @var list<DatosRecursoEquipo> $recursos */
        $recursos = $combustibles
            ->map(fn (Combustible $combustible) => new DatosRecursoEquipo(
                id: 0,
                recursoTipo: $combustible->recurso_tipo,
                recursoId: $combustible->recurso_id,
                desde: '',
                hasta: null,
            ))
            ->all();

        return $this->etiquetasRecurso($recursos);
    }

    /**
     * Resuelve `"{tipo}:{id}"` => identificador legible, por tipo en un
     * único `whereIn` por tabla (mismo criterio de agrupación que
     * `Personal\Infraestructura\Http\Controllers\Web\EquiposTrabajoController::etiquetasRecurso()`).
     *
     * @param  list<DatosRecursoEquipo>  $recursos
     * @return array<string, string>
     */
    private function etiquetasRecurso(array $recursos): array
    {
        $idsPorTipo = collect($recursos)->groupBy('recursoTipo')->map(
            fn (Collection $grupo) => $grupo->pluck('recursoId')->unique()->values()->all(),
        );

        $etiquetas = [];

        foreach (self::TABLA_POR_TIPO as $tipo => $tabla) {
            $ids = $idsPorTipo->get($tipo, []);

            if ($ids === []) {
                continue;
            }

            foreach (DB::table($tabla)->whereIn('id', $ids)->pluck('identificador', 'id') as $id => $identificador) {
                $etiquetas["{$tipo}:{$id}"] = sprintf('%s (%s)', $identificador, __('finanzas.combustible.tipo_recurso.'.$tipo));
            }
        }

        return $etiquetas;
    }
}
