<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\CrearOrdenTrabajo;
use App\Dominios\Operaciones\Aplicacion\ListarOrdenesTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\CaldaNoRegistrada;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearOrdenTrabajoRequest;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/trabajos*` (reforma 18/9/2026, "Orden de Trabajo"): alta y
 * seguimiento de tandas de trabajo — el maestro de la pantalla que el menú ya
 * llama "Orden de Trabajo" (`panel.trabajos.index`, sin tocar la fila de
 * `sec_menu`). El detalle de UN `Trabajo` puntual (equipo×lote) sigue en
 * {@see TrabajosController}, bajo `panel.trabajos.detalle*`.
 *
 * Dos permisos (`operaciones.trabajo.ver`/`.crear`), verificados DENTRO del
 * controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}. Ninguna
 * regla de negocio acá: `Aplicacion/CrearOrdenTrabajo` hace el trabajo,
 * incluidas las cuatro guardas (orden vigente, equipo vigente, lote de la
 * orden, tope de hectáreas por lote).
 *
 * Mismo caso de uso que la pantalla vieja `/panel/reparto-cuadrillas`
 * (`RepartoCuadrillasController`, que sigue viva bajo el nombre de menú
 * "Cuadrillas") — ahí la orden viene por ruta; acá se elige
 * dentro del formulario (`CrearOrdenTrabajoRequest`), porque `/panel/trabajos`
 * no cuelga de una orden puntual.
 */
final class OrdenesTrabajoController
{
    private const PERMISO_VER = 'operaciones.trabajo.ver';

    private const PERMISO_CREAR = 'operaciones.trabajo.crear';

    /** Solo para OFRECER el acceso rápido «Crear cuadrilla»: el alta la autoriza Personal. */
    private const PERMISO_CREAR_CUADRILLA = 'personal.equipo_trabajo.crear';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarOrdenesTrabajo $listarOrdenesTrabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $ordenId = $request->filled('orden_id') ? $request->integer('orden_id') : null;
        $nroAplicacion = $request->filled('nro_aplicacion') ? $request->integer('nro_aplicacion') : null;

        $tandas = $listarOrdenesTrabajo->ejecutar($ordenId, $nroAplicacion);

        $equipoIds = $tandas->getCollection()
            ->flatMap(fn (OrdenTrabajo $tanda) => $tanda->trabajos->pluck('equipo_trabajo_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('operaciones::pages.ordenes-trabajo.index', [
            ...$this->autorizacion->cascara($request),
            'tandas' => $tandas,
            'filtros' => ['orden_id' => $ordenId, 'nro_aplicacion' => $nroAplicacion],
            'etiquetasEquipo' => $this->etiquetasEquipo($equipoIds),
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
        ]);
    }

    public function create(Request $request, LecturaEquipoTrabajo $equipos, LecturaLotes $lotes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $ordenPreseleccionadaId = $request->filled('orden_id') ? $request->integer('orden_id') : null;

        return view('operaciones::pages.ordenes-trabajo.create', [
            ...$this->autorizacion->cascara($request),
            'ordenesDisponibles' => $this->ordenesVigentesDisponibles(),
            'datosOrden' => $this->datosOrdenParaFormulario($lotes),
            'ordenPreseleccionadaId' => $ordenPreseleccionadaId,
            'equiposDisponibles' => $this->equiposDisponibles($equipos),
            'puedeCrearCuadrilla' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR_CUADRILLA),
        ]);
    }

    public function store(CrearOrdenTrabajoRequest $request, CrearOrdenTrabajo $crearOrdenTrabajo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();
        $orden = OrdenAplicacion::query()->findOrFail($datos['orden_id']);

        try {
            $ordenTrabajo = $crearOrdenTrabajo->ejecutar(
                $orden,
                $this->normalizarParametros($datos['parametros'] ?? []),
                $this->normalizarEquipos($datos['equipos']),
            );
        } catch (OrdenNoVigenteParaAsignacion|EquipoTrabajoNoVigente|LoteNoPerteneceAOrden|HectareasAsignadasSuperanLote|CaldaNoRegistrada $excepcion) {
            return redirect()
                ->route('panel.trabajos.create', ['orden_id' => $orden->id])
                ->withErrors(['equipos' => $excepcion->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('panel.trabajos.show', $ordenTrabajo)
            ->with('estado', __('operaciones.ordenes_trabajo.creada'));
    }

    public function show(Request $request, OrdenTrabajo $ordenTrabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $ordenTrabajo->load(['orden', 'trabajos.sesiones']);

        $hectareasTotales = $ordenTrabajo->trabajos->reduce(
            fn (BigDecimal $acumulado, $trabajo): BigDecimal => $acumulado->plus((string) $trabajo->hectareas_declaradas),
            BigDecimal::zero(),
        );

        $equipoIds = $ordenTrabajo->trabajos->pluck('equipo_trabajo_id')->filter()->unique()->values()->all();

        return view('operaciones::pages.ordenes-trabajo.show', [
            ...$this->autorizacion->cascara($request),
            'ordenTrabajo' => $ordenTrabajo,
            'hectareasTotales' => (string) $hectareasTotales,
            'cantidadEquipos' => count($equipoIds),
            'etiquetasEquipo' => $this->etiquetasEquipo($equipoIds),
            'etiquetasLote' => $this->etiquetasLote($ordenTrabajo->trabajos->pluck('lote_id')->unique()->values()->all()),
            'puedeEditarTrabajo' => $this->autorizacion->tienePermiso($request, 'operaciones.trabajo.editar'),
            'puedeEliminarTrabajo' => $this->autorizacion->tienePermiso($request, 'operaciones.trabajo.eliminar'),
        ]);
    }

    /** @return Collection<int, string> */
    private function ordenesVigentesDisponibles(): Collection
    {
        return OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->orderByDesc('fecha_emision')
            ->get()
            ->mapWithKeys(fn (OrdenAplicacion $orden): array => [
                $orden->id => __('operaciones.ordenes_trabajo.campo_orden_opcion', ['id' => $orden->id, 'aplicacion' => $orden->nro_aplicacion]),
            ]);
    }

    /**
     * Por cada orden vigente: sus lotes con hectáreas restantes por repartir
     * (mismo cálculo que `RepartoCuadrillasController::resumenPorLote()`), si
     * es de insumo líquido (para que la vista muestre Ph/calda solo ahí), y
     * cuántos equipos definió la orden (`cantidad_equipos_necesarios`, HU-92):
     * el formulario dibuja ESA cantidad de bloques — no se agregan ni se
     * quitan equipos a mano (pedido del dueño, 19/9/2026).
     *
     * @return array<int, array{es_liquido: bool, cantidad_equipos: int, restantes_total: string, litros_ha: string|null, lotes: list<array{lote_id: int, label: string, restantes: string, pendiente: bool, limpieza: string|null}>}>
     */
    private function datosOrdenParaFormulario(LecturaLotes $lecturaLotes): array
    {
        $ordenes = OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->with('categoriaInsumo')
            ->get();

        $resultado = [];

        foreach ($ordenes as $orden) {
            $ordenLotes = $orden->ordenLotes()->orderBy('lote_id')->get();
            $idsLote = $ordenLotes->pluck('lote_id')->map(fn ($id) => (int) $id)->all();
            $etiquetas = $this->etiquetasLote($idsLote);
            // Estado del terreno de cada lote (dato de Comercial, por contrato):
            // el formulario lo usa para repartir las hectáreas por dificultad.
            $limpiezas = $lecturaLotes->limpiezaPorIds($idsLote);

            $lotes = $ordenLotes->map(function ($ordenLote) use ($orden, $etiquetas, $limpiezas): array {
                $asignadas = BigDecimal::of((string) DB::table('ope_trabajos')
                    ->where('orden_id', $orden->id)
                    ->where('lote_id', $ordenLote->lote_id)
                    ->whereNull('deleted_at')
                    ->sum('hectareas_declaradas'));
                $solicitadas = BigDecimal::of((string) $ordenLote->hectareas_solicitadas);
                $restantes = $solicitadas->minus($asignadas);

                return [
                    'lote_id' => $ordenLote->lote_id,
                    'label' => $etiquetas[$ordenLote->lote_id] ?? "#{$ordenLote->lote_id}",
                    'restantes' => (string) $restantes,
                    'pendiente' => $restantes->isPositive(),
                    'limpieza' => $limpiezas[(int) $ordenLote->lote_id] ?? null,
                ];
            })->values()->all();

            $restantesTotal = array_reduce(
                $lotes,
                fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['restantes']),
                BigDecimal::zero(),
            );

            $resultado[$orden->id] = [
                'es_liquido' => $orden->categoriaInsumo?->tipo_insumo === TipoInsumo::Liquido,
                'cantidad_equipos' => max(1, (int) $orden->cantidad_equipos_necesarios),
                'restantes_total' => (string) $restantesTotal,
                'litros_ha' => $orden->litros_ha !== null ? (string) $orden->litros_ha : null,
                'lotes' => $lotes,
            ];
        }

        return $resultado;
    }

    /** @return Collection<int, string> */
    private function equiposDisponibles(LecturaEquipoTrabajo $equipos): Collection
    {
        return collect($equipos->vigentesAFecha(now()->toDateString()))
            ->mapWithKeys(fn (DatosEquipoTrabajo $equipo): array => [
                $equipo->id => $equipo->nombre !== null ? "{$equipo->codigo} — {$equipo->nombre}" : $equipo->codigo,
            ]);
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    private function normalizarParametros(array $parametros): array
    {
        $cadena = fn (mixed $valor): ?string => $valor === null || $valor === '' ? null : (string) $valor;

        return [
            'humedad_min_pct' => $cadena($parametros['humedad_min_pct'] ?? null),
            'viento_max_kmh' => $cadena($parametros['viento_max_kmh'] ?? null),
            'temperatura_max_c' => $cadena($parametros['temperatura_max_c'] ?? null),
            'humedad_max_pct' => $cadena($parametros['humedad_max_pct'] ?? null),
            'velocidad_max_kmh' => $cadena($parametros['velocidad_max_kmh'] ?? null),
            'altura_vuelo_m' => $cadena($parametros['altura_vuelo_m'] ?? null),
            'velocidad_vuelo_kmh' => $cadena($parametros['velocidad_vuelo_kmh'] ?? null),
            'ancho_pasada_m' => $cadena($parametros['ancho_pasada_m'] ?? null),
            'ph_agua' => $cadena($parametros['ph_agua'] ?? null),
            'ph_calda' => $cadena($parametros['ph_calda'] ?? null),
            'litros_ha' => $cadena($parametros['litros_ha'] ?? null),
            'kilos_ha' => $cadena($parametros['kilos_ha'] ?? null),
            // Casillas sin cantidades: quedan en la cabecera de la tanda. Esta
            // pantalla no registra productos por `Mezclas` (que exige cantidad
            // y unidad), así que `calda` va siempre vacía.
            'calda_productos' => array_values(array_unique(array_map('strval', $parametros['calda_productos'] ?? []))),
            'calda' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $equipos
     * @return list<array{equipo_trabajo_id: int, lotes: list<array{lote_id: int, hectareas: string, turno: string, turno_hora_inicio: string, turno_hora_fin: string}>}>
     */
    private function normalizarEquipos(array $equipos): array
    {
        return array_map(fn (array $equipo): array => [
            'equipo_trabajo_id' => (int) $equipo['equipo_trabajo_id'],
            'lotes' => array_map(fn (array $lote): array => [
                'lote_id' => (int) $lote['lote_id'],
                'hectareas' => (string) $lote['hectareas'],
                'turno' => (string) $lote['turno'],
                'turno_hora_inicio' => (string) $lote['turno_hora_inicio'],
                'turno_hora_fin' => (string) $lote['turno_hora_fin'],
            ], $equipo['lotes']),
        ], $equipos);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasEquipo(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('per_equipos_trabajo')
            ->whereIn('id', $ids)
            ->pluck('codigo', 'id')
            ->map(fn ($valor) => (string) $valor)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasLote(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('com_lotes as l')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereIn('l.id', $ids)
            ->get(['l.id', 'p.nombre', 'l.codigo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_lote_opcion', [
                    'campo' => $fila->nombre,
                    'codigo' => $fila->codigo,
                ]),
            ])
            ->all();
    }
}
