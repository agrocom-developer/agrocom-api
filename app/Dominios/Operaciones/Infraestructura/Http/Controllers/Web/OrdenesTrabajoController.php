<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\CrearOrdenTrabajo;
use App\Dominios\Operaciones\Aplicacion\ListarOrdenesTrabajo;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\CaldaNoRegistrada;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearOrdenTrabajoRequest;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** Solo para OFRECER el enlace a la orden de aplicación desde la ficha. */
    private const PERMISO_VER_ORDEN = 'operaciones.orden.ver';

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
        $datosOrden = $this->datosOrdenParaFormulario($lotes);

        return view('operaciones::pages.ordenes-trabajo.create', [
            ...$this->autorizacion->cascara($request),
            'ordenesDisponibles' => $this->opcionesDeOrden($datosOrden),
            'datosOrden' => $datosOrden,
            'ordenPreseleccionadaId' => $ordenPreseleccionadaId,
            // Se llegó con una orden que ya no tiene nada por repartir (o dejó de
            // estar vigente): el formulario lo dice en vez de quedar mudo.
            'ordenSinPendiente' => $ordenPreseleccionadaId !== null && ! isset($datosOrden[$ordenPreseleccionadaId]),
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

    /**
     * Ficha de la Orden de Trabajo, con la misma distribución que la de la
     * orden de aplicación (`OrdenesController::show()`, pedido del dueño,
     * 21/9/2026): KPI, columna principal con lo que se lee de corrido —la orden
     * de aplicación, las indicaciones (calda, clima, vuelo) y los trabajos— y
     * aside con avance, relacionado y actividad.
     *
     * Los trabajos van AGRUPADOS POR EQUIPO: la orden se reparte entre equipos,
     * y cada lote que le toca a un equipo es un trabajo con su propio estado.
     */
    public function show(Request $request, OrdenTrabajo $ordenTrabajo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $ordenTrabajo->load(['orden.categoriaInsumo', 'trabajos.sesiones']);

        $trabajos = $ordenTrabajo->trabajos->sortBy('id')->values();
        $sumar = fn (Collection $grupo): BigDecimal => $grupo->reduce(
            fn (BigDecimal $acumulado, Trabajo $trabajo): BigDecimal => $acumulado->plus((string) $trabajo->hectareas_declaradas),
            BigDecimal::zero(),
        );

        $hectareasTotales = $sumar($trabajos);
        $terminados = $trabajos->filter(fn (Trabajo $trabajo): bool => $trabajo->estadoTablero() !== EstadoTableroTrabajo::Abierto)->values();
        $hectareasTerminadas = $sumar($terminados);

        $equipoIds = $trabajos->pluck('equipo_trabajo_id')->filter()->unique()->values()->all();
        $etiquetasEquipo = $this->etiquetasEquipo($equipoIds);
        $orden = $ordenTrabajo->orden;

        $equipos = $trabajos
            ->groupBy(fn (Trabajo $trabajo): int => (int) $trabajo->equipo_trabajo_id)
            ->map(fn (Collection $grupo, int $equipoId): array => [
                'etiqueta' => $etiquetasEquipo[$equipoId] ?? ($equipoId !== 0 ? "#{$equipoId}" : __('operaciones.trabajos.campo_equipo_sin_asignar')),
                'hectareas' => $this->aHectareas($sumar($grupo)),
                'trabajos' => $grupo->values(),
            ])
            ->values()
            ->all();

        return view('operaciones::pages.ordenes-trabajo.show', [
            ...$this->autorizacion->cascara($request),
            'ordenTrabajo' => $ordenTrabajo,
            'orden' => $orden,
            'clienteLabel' => $orden !== null ? ($this->clientesPorContrato([(int) $orden->contrato_id])[(int) $orden->contrato_id] ?? null) : null,
            'hectareasTotales' => $this->aHectareas($hectareasTotales),
            'hectareasTerminadas' => $this->aHectareas($hectareasTerminadas),
            'porcentajeTerminado' => $hectareasTotales->isZero()
                ? 0
                : (int) round(((float) (string) $hectareasTerminadas / (float) (string) $hectareasTotales) * 100),
            'cantidadTrabajos' => $trabajos->count(),
            'cantidadTerminados' => $terminados->count(),
            'equipos' => $equipos,
            'etiquetasLote' => $this->etiquetasLote($trabajos->pluck('lote_id')->unique()->values()->all()),
            'vinculos' => $this->vinculos($ordenTrabajo, $request),
            'actividad' => $this->actividad($ordenTrabajo, $equipos),
            'puedeEditarTrabajo' => $this->autorizacion->tienePermiso($request, 'operaciones.trabajo.editar'),
            'puedeEliminarTrabajo' => $this->autorizacion->tienePermiso($request, 'operaciones.trabajo.eliminar'),
        ]);
    }

    /**
     * «Relacionado» de la ficha: la orden de aplicación de la que sale, y las
     * demás Órdenes de Trabajo de esa misma orden. Cada acceso pide el permiso
     * de SU pantalla de destino.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculos(OrdenTrabajo $ordenTrabajo, Request $request): array
    {
        $vinculos = [];

        if ($ordenTrabajo->orden !== null && $this->autorizacion->tienePermiso($request, self::PERMISO_VER_ORDEN)) {
            $vinculos[] = [
                'href' => route('panel.ordenes.show', $ordenTrabajo->orden_id),
                'icon' => 'assignment',
                'title' => __('operaciones.ordenes_trabajo.vinculo_orden'),
                'meta' => __('operaciones.ordenes_trabajo.vinculo_orden_meta', ['id' => $ordenTrabajo->orden_id, 'aplicacion' => $ordenTrabajo->nro_aplicacion]),
                'tone' => 'warning',
            ];
        }

        $total = OrdenTrabajo::query()->where('orden_id', $ordenTrabajo->orden_id)->count();

        $vinculos[] = [
            'href' => route('panel.trabajos.index', ['orden_id' => $ordenTrabajo->orden_id]),
            'icon' => 'work_history',
            'title' => __('operaciones.ordenes_trabajo.vinculo_hermanas'),
            'meta' => trans_choice('operaciones.ordenes_trabajo.vinculo_hermanas_meta', $total, ['cantidad' => $total]),
            'tone' => 'info',
        ];

        return $vinculos;
    }

    /**
     * Actividad reconstruible desde columnas reales: la creación, el reparto a
     * cada equipo y el cierre de cada trabajo.
     *
     * @param  list<array{etiqueta: string, hectareas: string, trabajos: Collection<int, Trabajo>}>  $equipos
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function actividad(OrdenTrabajo $ordenTrabajo, array $equipos): array
    {
        $autor = $ordenTrabajo->created_by !== null
            ? (DB::table('sec_user')->where('id', $ordenTrabajo->created_by)->value('name') ?? "#{$ordenTrabajo->created_by}")
            : null;
        $fecha = $ordenTrabajo->created_at?->format('d/m/Y H:i') ?? '—';
        $meta = $autor !== null ? __('operaciones.ordenes.actividad_meta', ['fecha' => $fecha, 'autor' => $autor]) : $fecha;

        $eventos = [[
            'title' => __('operaciones.ordenes_trabajo.actividad_creada'),
            'meta' => $meta,
            'tone' => 'neutral',
        ]];

        foreach ($equipos as $equipo) {
            $eventos[] = [
                'title' => __('operaciones.ordenes_trabajo.actividad_equipo', ['equipo' => $equipo['etiqueta'], 'hectareas' => $equipo['hectareas']]),
                'meta' => trans_choice('operaciones.ordenes_trabajo.actividad_equipo_meta', $equipo['trabajos']->count(), ['cantidad' => $equipo['trabajos']->count()]),
                'tone' => 'info',
            ];
        }

        foreach ($equipos as $equipo) {
            foreach ($equipo['trabajos'] as $trabajo) {
                if ($trabajo->fin !== null) {
                    $eventos[] = [
                        'title' => __('operaciones.ordenes_trabajo.actividad_trabajo_cerrado', ['id' => $trabajo->id, 'equipo' => $equipo['etiqueta']]),
                        'meta' => $trabajo->fin->format('d/m/Y H:i'),
                        'tone' => 'success',
                    ];
                }
            }
        }

        return $eventos;
    }

    private function aHectareas(BigDecimal $valor): string
    {
        return number_format((float) (string) $valor, 2, ',', '.');
    }

    /**
     * Opciones del select de orden, en el mismo orden que `$datosOrden` (la
     * más reciente primero). Solo figuran las órdenes que todavía tienen
     * hectáreas por repartir.
     *
     * @param  array<int, array{label: string}>  $datosOrden
     * @return Collection<int, string>
     */
    private function opcionesDeOrden(array $datosOrden): Collection
    {
        return collect($datosOrden)->map(fn (array $orden): string => $orden['label']);
    }

    /**
     * Por cada orden vigente CON hectáreas por repartir: sus lotes pendientes
     * y cuánto le queda a cada uno (mismo cálculo que
     * `RepartoCuadrillasController::resumenPorLote()`), si es de insumo
     * líquido (para que la vista pida Ph/litros solo ahí), y cuántos equipos
     * definió la orden (`cantidad_equipos_necesarios`, HU-92): el formulario
     * dibuja ESA cantidad de bloques — no se agregan ni se quitan equipos a
     * mano (pedido del dueño, 19/9/2026).
     *
     * Un lote que ya se repartió entero en otra Orden de Trabajo NO se ofrece,
     * y una orden sin ningún lote pendiente tampoco (pedido del dueño,
     * 21/9/2026: «si ya está asignado no debería aparecer en el listado»).
     *
     * Viaja entero al formulario (`data-ag-ordenes`): al elegir otra orden,
     * `ordenes-trabajo-form.js` arma calda, equipos y lotes sin otro request.
     *
     * @return array<int, array{label: string, contrato: string, cliente: string, logo_url: string|null, propiedades: string, aplicacion: string, insumo: string, por_repartir: string, es_liquido: bool, cantidad_equipos: int, restantes_total: string, litros_ha: string|null, lotes: list<array{lote_id: int, label: string, restantes: string, limpieza: string|null}>}>
     */
    private function datosOrdenParaFormulario(LecturaLotes $lecturaLotes): array
    {
        $ordenes = OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->with('categoriaInsumo')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();

        $contratos = $this->contratosParaFormulario($ordenes->pluck('contrato_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
        $resultado = [];

        foreach ($ordenes as $orden) {
            $ordenLotes = $orden->ordenLotes()->orderBy('lote_id')->get();
            $idsLote = $ordenLotes->pluck('lote_id')->map(fn ($id) => (int) $id)->all();
            $etiquetas = $this->etiquetasLote($idsLote);
            // Estado del terreno de cada lote (dato de Comercial, por contrato):
            // el formulario lo usa para repartir las hectáreas por dificultad.
            $limpiezas = $lecturaLotes->limpiezaPorIds($idsLote);

            $asignadasPorLote = DB::table('ope_trabajos')
                ->where('orden_id', $orden->id)
                ->whereNull('deleted_at')
                ->groupBy('lote_id')
                ->selectRaw('lote_id, sum(hectareas_declaradas) as asignadas')
                ->pluck('asignadas', 'lote_id');

            $lotes = [];
            $restantesTotal = BigDecimal::zero();

            foreach ($ordenLotes as $ordenLote) {
                $restantes = BigDecimal::of((string) $ordenLote->hectareas_solicitadas)
                    ->minus((string) ($asignadasPorLote[$ordenLote->lote_id] ?? '0'));

                if (! $restantes->isPositive()) {
                    continue;
                }

                $restantesTotal = $restantesTotal->plus($restantes);
                $lotes[] = [
                    'lote_id' => (int) $ordenLote->lote_id,
                    'label' => $etiquetas[$ordenLote->lote_id] ?? "#{$ordenLote->lote_id}",
                    'restantes' => (string) $restantes,
                    'limpieza' => $limpiezas[(int) $ordenLote->lote_id] ?? null,
                ];
            }

            if ($lotes === []) {
                continue;
            }

            $contrato = $contratos[(int) $orden->contrato_id] ?? null;
            $categoria = $orden->categoriaInsumo;

            $resultado[$orden->id] = [
                'label' => __('operaciones.ordenes_trabajo.campo_orden_opcion', [
                    'id' => $orden->id,
                    'cliente' => $contrato['cliente'] ?? '—',
                    'aplicacion' => $orden->nro_aplicacion,
                ]),
                // Cuadro «Datos del contrato» del primer bloque, ya en texto.
                'contrato' => __('operaciones.ordenes_trabajo.contrato_numero', ['id' => $orden->contrato_id]),
                'cliente' => $contrato['cliente'] ?? '—',
                'logo_url' => $contrato['logo_url'] ?? null,
                'propiedades' => implode(', ', $contrato['propiedades'] ?? []) ?: '—',
                'aplicacion' => $contrato !== null
                    ? __('operaciones.ordenes.aplicacion_n_de_m', ['nro' => $orden->nro_aplicacion, 'total' => $contrato['aplicaciones_previstas']])
                    : (string) $orden->nro_aplicacion,
                'insumo' => $categoria !== null
                    ? $categoria->nombre.' · '.__('operaciones.tipo_insumo.'.$categoria->tipo_insumo->value)
                    : '—',
                'por_repartir' => $this->aHectareas($restantesTotal).' ha',
                'es_liquido' => $orden->categoriaInsumo?->tipo_insumo === TipoInsumo::Liquido,
                'cantidad_equipos' => max(1, (int) $orden->cantidad_equipos_necesarios),
                'restantes_total' => (string) $restantesTotal,
                'litros_ha' => $orden->litros_ha !== null ? (string) $orden->litros_ha : null,
                'lotes' => $lotes,
            ];
        }

        return $resultado;
    }

    /**
     * Datos del contrato de cada orden para el cuadro «Datos del contrato» del
     * alta —el mismo que muestra el formulario de la orden de aplicación—:
     * cliente, logo, propiedades y aplicaciones pactadas. Lectura directa, en
     * dos consultas para todos los contratos.
     *
     * @param  list<int>  $contratoIds
     * @return array<int, array{cliente: string, logo_url: string|null, propiedades: list<string>, aplicaciones_previstas: int}>
     */
    private function contratosParaFormulario(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return [];
        }

        $propiedades = DB::table('com_contrato_lotes as ccl')
            ->join('com_lotes as l', 'l.id', '=', 'ccl.lote_id')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereIn('ccl.contrato_id', $contratoIds)
            ->whereNull('ccl.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNull('p.deleted_at')
            ->distinct()
            ->orderBy('p.nombre')
            ->get(['ccl.contrato_id', 'p.nombre'])
            ->groupBy('contrato_id');

        $disco = Storage::disk('public');

        return DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereIn('c.id', $contratoIds)
            ->get(['c.id', 'c.aplicaciones_previstas', 'cl.razon_social', 'cl.logo_path'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => [
                    'cliente' => (string) $fila->razon_social,
                    'logo_url' => $fila->logo_path !== null && $disco->exists($fila->logo_path) ? $disco->url($fila->logo_path) : null,
                    'propiedades' => ($propiedades[$fila->id] ?? collect())->pluck('nombre')->map(fn ($nombre) => (string) $nombre)->all(),
                    'aplicaciones_previstas' => (int) $fila->aplicaciones_previstas,
                ],
            ])
            ->all();
    }

    /**
     * Razón social del cliente de cada contrato, para rotular las órdenes —
     * lectura directa, mismo criterio que `etiquetasLote()`.
     *
     * @param  list<int>  $contratoIds
     * @return array<int, string>
     */
    private function clientesPorContrato(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return [];
        }

        return DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereIn('c.id', $contratoIds)
            ->pluck('cl.razon_social', 'c.id')
            ->map(fn ($valor) => (string) $valor)
            ->all();
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
     * @return list<array{equipo_trabajo_id: int, lotes: list<array{lote_id: int, hectareas: string, turno: string, turno_hora_inicio: string|null, turno_hora_fin: string|null}>}>
     */
    private function normalizarEquipos(array $equipos): array
    {
        return array_map(fn (array $equipo): array => [
            'equipo_trabajo_id' => (int) $equipo['equipo_trabajo_id'],
            'lotes' => array_map(fn (array $lote): array => [
                'lote_id' => (int) $lote['lote_id'],
                'hectareas' => (string) $lote['hectareas'],
                'turno' => (string) $lote['turno'],
                'turno_hora_inicio' => ($lote['turno_hora_inicio'] ?? '') === '' ? null : (string) $lote['turno_hora_inicio'],
                'turno_hora_fin' => ($lote['turno_hora_fin'] ?? '') === '' ? null : (string) $lote['turno_hora_fin'],
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
