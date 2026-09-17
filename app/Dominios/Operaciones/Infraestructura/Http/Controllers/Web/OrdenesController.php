<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ActivarOrden;
use App\Dominios\Operaciones\Aplicacion\ActualizarOrden;
use App\Dominios\Operaciones\Aplicacion\CrearOrden;
use App\Dominios\Operaciones\Aplicacion\EliminarOrden;
use App\Dominios\Operaciones\Aplicacion\ListarOrdenesAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEditable;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenVigenteDuplicadaEnLote;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenVigenteNoEliminable;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\CategoriaInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarOrdenRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearOrdenRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/ordenes*` (HU-25, tarea 38): alta y
 * seguimiento de órdenes de aplicación, con una máquina de estados propia
 * (`emitida → vigente`, ver `Aplicacion/MaquinaEstados/MaquinaEstadosOrden`).
 * Mismo molde que `ContratosController` (cambio de estado separado de la
 * edición), sin sub-entidad.
 *
 * Cinco permisos de grano fino
 * (`operaciones.orden.ver`/`.crear`/`.editar`/`.activar`/`.eliminar`),
 * verificados DENTRO del controlador contra el ROL ACTIVO vía
 * {@see AutorizacionPanelWeb}. Ninguna regla de negocio acá: los casos de
 * uso de `Aplicacion/` hacen el trabajo, incluida la restricción de que solo
 * una orden `emitida` admite edición o baja.
 *
 * Los selects de `contrato_id`/`lote_id`/`emitida_por_contacto_id` se arman
 * con consultas directas a las tablas de `Comercial` (`DB::table`, sin
 * importar sus modelos Eloquent — ADR 0003 regla 3, mismo criterio que el
 * `exists:` de los Requests), no por su `Contratos/` (ese contrato de
 * lectura hoy solo expone lotes para `CalcularCoberturaTrabajo`, no listados
 * para un `<select>` del panel).
 */
final class OrdenesController
{
    private const PERMISO_VER = 'operaciones.orden.ver';

    private const PERMISO_CREAR = 'operaciones.orden.crear';

    private const PERMISO_EDITAR = 'operaciones.orden.editar';

    private const PERMISO_ACTIVAR = 'operaciones.orden.activar';

    private const PERMISO_ELIMINAR = 'operaciones.orden.eliminar';

    private const PERMISO_ASIGNAR_EQUIPOS = 'operaciones.orden.asignar_equipos';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarOrdenesAplicacion $listarOrdenes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoOrdenAplicacion::tryFrom($estadoQuery) : null;

        $tipoAplicacionQuery = $request->string('tipo_aplicacion')->toString();
        $tipoAplicacion = $tipoAplicacionQuery !== '' ? TipoAplicacion::tryFrom($tipoAplicacionQuery) : null;

        $ordenes = $listarOrdenes->ejecutar(
            estado: $estado,
            tipoAplicacion: $tipoAplicacion,
            contratoIds: $busqueda !== '' ? $this->contratoIdsPorBusqueda($busqueda) : null,
        );

        $loteIdsPorOrden = $this->loteIdsPorOrden($ordenes->pluck('id')->map(fn ($id) => (int) $id)->all());
        $todosLosLoteIds = collect($loteIdsPorOrden)->flatten()->unique()->values()->all();

        // Vista lista/grilla (homogeneización 17/9/2026): solo cambia cómo se
        // pinta la MISMA colección paginada — nunca una consulta distinta.
        $vistaQuery = $request->string('vista')->toString();
        $vista = in_array($vistaQuery, ['lista', 'grilla'], true) ? $vistaQuery : 'lista';

        return view('operaciones::pages.ordenes.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'etiquetasContrato' => $this->etiquetasContrato($ordenes->pluck('contrato_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'etiquetasLote' => $this->etiquetasLote($todosLosLoteIds),
            'loteIdsPorOrden' => $loteIdsPorOrden,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'tipo_aplicacion' => $tipoAplicacion?->value],
            'vista' => $vista,
            'puedeActivar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR),
        ]);
    }

    /**
     * Contratos cuyo cliente coincide con el buscador `q` del listado (mismo
     * criterio de acentos/mayúsculas que `Compartido\Infraestructura\Busqueda\BusquedaTexto`,
     * reescrito acá porque esa clase opera sobre un `Eloquent\Builder` y esta
     * lectura es `DB::table` cruzando a Comercial — ADR 0003 regla 3, mismo
     * criterio que el resto de los selects de este controlador). Un buscador
     * sin coincidencias devuelve `[]`, que `ListarOrdenesAplicacion` traduce
     * a "ningún resultado", nunca a "sin filtro".
     *
     * @return list<int>
     */
    private function contratoIdsPorBusqueda(string $busqueda): array
    {
        $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda).'%';

        $consulta = DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereNull('c.deleted_at')
            ->whereNull('cl.deleted_at');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $consulta->whereRaw('unaccent(cl.razon_social) ILIKE unaccent(?)', [$patron]);
        } else {
            $consulta->whereRaw('LOWER(cl.razon_social) LIKE ?', [$patron]);
        }

        return $consulta->pluck('c.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Lotes de CADA orden (HU-92, tarea 107: ya no es un `lote_id` único por
     * orden) — una sola consulta para todo el listado, evita N+1.
     *
     * @param  list<int>  $ordenIds
     * @return array<int, list<int>>
     */
    private function loteIdsPorOrden(array $ordenIds): array
    {
        if ($ordenIds === []) {
            return [];
        }

        return DB::table('ope_orden_lotes')
            ->whereIn('orden_id', $ordenIds)
            ->whereNull('deleted_at')
            ->orderBy('lote_id')
            ->get(['orden_id', 'lote_id'])
            ->groupBy('orden_id')
            ->map(fn (Collection $filas): array => $filas->pluck('lote_id')->map(fn ($id) => (int) $id)->all())
            ->all();
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('operaciones::pages.ordenes.create', [
            ...$this->autorizacion->cascara($request),
            'contratosDisponibles' => $this->contratosDisponibles(),
            'lotesDisponibles' => $this->lotesDisponibles(),
            'contactosDisponibles' => $this->contactosDisponibles(),
            'categoriasInsumoDisponibles' => $this->categoriasInsumoDisponibles(),
            'mapaContratoCliente' => $this->mapaContratoCliente(),
            'mapaLoteCliente' => $this->mapaLoteCliente(),
        ]);
    }

    /**
     * Detalle de solo lectura (homogeneización 17/9/2026): mientras una orden
     * es `emitida`, `edit()` cumple este rol; apenas se activa, `edit()` deja
     * de ofrecerse (`Aplicacion/ActualizarOrden` exige `emitida`) y hasta
     * ahora no quedaba ningún lugar del panel para volver a ver sus datos
     * completos — solo la fila resumida del listado. Mismo permiso que
     * `index()`/`edit()` (`PERMISO_VER`), sin permiso nuevo — mismo criterio
     * que las 5 pantallas `.show` ya homogeneizadas del panel (Trabajos,
     * Devengos, Planillas, Rendiciones, EquiposTrabajo).
     */
    public function show(Request $request, OrdenAplicacion $orden): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $orden->load('categoriaInsumo');

        $lotes = $this->detalleLotesOrden($orden);
        $hectareasSolicitadas = array_reduce($lotes, fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['hectareas_solicitadas']), BigDecimal::zero());
        $hectareasAsignadas = array_reduce($lotes, fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['asignadas']), BigDecimal::zero());
        $porcentajeAsignado = $hectareasSolicitadas->isZero()
            ? 0
            : (int) round(((float) (string) $hectareasAsignadas / (float) (string) $hectareasSolicitadas) * 100);

        $aplicacionesPrevistas = DB::table('com_contratos')->where('id', $orden->contrato_id)->value('aplicaciones_previstas');

        return view('operaciones::pages.ordenes.show', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'contratoLabel' => $this->etiquetasContrato([$orden->contrato_id])[$orden->contrato_id] ?? "#{$orden->contrato_id}",
            'contactoLabel' => $orden->emitida_por_contacto_id !== null
                ? DB::table('com_cliente_contactos')->where('id', $orden->emitida_por_contacto_id)->value('nombre')
                : null,
            'aplicacionesPrevistas' => $aplicacionesPrevistas !== null ? (int) $aplicacionesPrevistas : null,
            'lotes' => $lotes,
            'hectareasSolicitadas' => $this->aHectareas($hectareasSolicitadas),
            'hectareasAsignadas' => $this->aHectareas($hectareasAsignadas),
            'porcentajeAsignado' => $porcentajeAsignado,
            'equiposAsignados' => $this->equiposAsignadosCount($orden),
            'actividad' => $this->actividadOrden($orden),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeActivar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function store(CrearOrdenRequest $request, CrearOrden $crearOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $orden = $crearOrden->ejecutar($this->normalizarDatos($datos), $this->normalizarLotes($datos));

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::store()).
        return redirect()
            ->route('panel.ordenes.edit', $orden)
            ->with('estado', __('operaciones.ordenes.creada'));
    }

    public function edit(Request $request, OrdenAplicacion $orden): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('operaciones::pages.ordenes.edit', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'lotesOrden' => $orden->ordenLotes()->orderBy('lote_id')->get(),
            'contratosDisponibles' => $this->contratosDisponibles(),
            'lotesDisponibles' => $this->lotesDisponibles(),
            'contactosDisponibles' => $this->contactosDisponibles(),
            'categoriasInsumoDisponibles' => $this->categoriasInsumoDisponibles(),
            'mapaContratoCliente' => $this->mapaContratoCliente(),
            'mapaLoteCliente' => $this->mapaLoteCliente(),
            'resumenRelacionado' => $this->resumenRelacionado($orden, $request),
        ]);
    }

    /**
     * Resumen del aside de `edit()` (homogeneización con Comercial, 17/9/2026
     * — mismo criterio que `ClientesController::resumenRelacionado()`, §6.3.1
     * de docs/diseno/guia_pantalla_panel.md): UNA categoría relacionada,
     * "Asignación de equipos" (`AsignacionEquiposController`, HU-70/HU-92).
     * Gatea por el permiso DEL MÓDULO RELACIONADO
     * (`operaciones.orden.asignar_equipos`), no por `.ver`/`.editar` de la
     * orden — sin él, la categoría se omite del todo.
     *
     * Tres estados posibles, todos con datos REALES (no estático: el
     * contrato de lectura ya existe, `AsignacionEquiposController`):
     * - Orden no `vigente` todavía: no admite reparto, sin acción (repartir
     *   antes de activar rompería la guarda de `AsignarEquiposOrden`).
     * - Orden `vigente` sin nada asignado: `empty-state` con acceso directo a
     *   la ficha de reparto (memento de navegación, cruza a la misma pantalla
     *   `asignacion-equipos` — mismo criterio que
     *   `ContratosController::resumenContrato()` cruzando a `panel.ordenes.create`).
     * - Con reparto en curso o completo: `summary-card` con hectáreas
     *   solicitadas/asignadas/restantes y cantidad de equipos.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array{label: string, value: string, mono?: bool, variant?: string}>, vacioTitulo: string, vacioDetalle: string, mostrarAccion: bool, accion: array{label: string, href: string}}>
     */
    private function resumenRelacionado(OrdenAplicacion $orden, Request $request): array
    {
        if (! $this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR_EQUIPOS)) {
            return [];
        }

        $volverA = [
            'volver_a' => route('panel.ordenes.edit', $orden),
            'volver_texto' => __('operaciones.ordenes.aside_volver_texto', ['nro' => $orden->nro_aplicacion]),
        ];

        if ($orden->estado !== EstadoOrdenAplicacion::Vigente) {
            return [[
                'titulo' => __('operaciones.ordenes.aside_titulo'),
                'icono' => 'groups',
                'tieneDatos' => false,
                'items' => [],
                'vacioTitulo' => __('operaciones.ordenes.aside_no_vigente_titulo'),
                'vacioDetalle' => __('operaciones.ordenes.aside_no_vigente_detalle'),
                'mostrarAccion' => false,
                'accion' => ['label' => '', 'href' => ''],
            ]];
        }

        $lotes = $this->detalleLotesOrden($orden);
        $hectareasSolicitadas = array_reduce($lotes, fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['hectareas_solicitadas']), BigDecimal::zero());
        $hectareasAsignadas = array_reduce($lotes, fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['asignadas']), BigDecimal::zero());
        $equiposAsignados = $this->equiposAsignadosCount($orden);

        if ($equiposAsignados === 0) {
            return [[
                'titulo' => __('operaciones.ordenes.aside_titulo'),
                'icono' => 'groups',
                'tieneDatos' => false,
                'items' => [],
                'vacioTitulo' => __('operaciones.ordenes.aside_vacio_titulo'),
                'vacioDetalle' => __('operaciones.ordenes.aside_vacio_detalle'),
                'mostrarAccion' => true,
                'accion' => [
                    'label' => __('operaciones.ordenes.aside_repartir_accion'),
                    'href' => route('panel.asignacion-equipos.show', [$orden, ...$volverA]),
                ],
            ]];
        }

        $restantes = $hectareasSolicitadas->minus($hectareasAsignadas);

        return [[
            'titulo' => __('operaciones.ordenes.aside_titulo'),
            'icono' => 'groups',
            'tieneDatos' => true,
            'items' => [
                ['label' => __('operaciones.ordenes.aside_hectareas_solicitadas'), 'value' => $this->aHectareas($hectareasSolicitadas), 'mono' => true],
                ['label' => __('operaciones.ordenes.aside_asignadas'), 'value' => $this->aHectareas($hectareasAsignadas), 'mono' => true],
                [
                    'label' => __('operaciones.ordenes.aside_restantes'),
                    'value' => $this->aHectareas($restantes),
                    'mono' => true,
                    'variant' => $restantes->isZero() ? 'success' : 'neutral',
                ],
                ['label' => __('operaciones.ordenes.aside_equipos_asignados'), 'value' => (string) $equiposAsignados, 'mono' => true],
            ],
            'vacioTitulo' => '',
            'vacioDetalle' => '',
            'mostrarAccion' => true,
            'accion' => [
                'label' => __('operaciones.ordenes.aside_ver_asignacion'),
                'href' => route('panel.asignacion-equipos.show', [$orden, ...$volverA]),
            ],
        ]];
    }

    private function aHectareas(BigDecimal $valor): string
    {
        return number_format((float) (string) $valor, 2, ',', '.');
    }

    /**
     * Detalle por lote de la orden: lo solicitado (`ope_orden_lotes`), lo ya
     * asignado a algún equipo (`ope_trabajos` de ese par orden↔lote) y lo
     * restante — mismo cálculo que ya hace `AsignacionEquiposController::resumenPorLote()`
     * para su propia pantalla, reescrito acá porque `show()` necesita el
     * detalle POR LOTE (tabla "Lotes") y `resumenRelacionado()` antes
     * repetía las mismas consultas solo para sumar el TOTAL — ahora suma
     * sobre esta lista.
     *
     * `estado`: 'asignado' cuando no queda nada restante por repartir de ese
     * lote, 'pendiente' en cualquier otro caso (incluido un reparto parcial)
     * — mismo criterio binario que ya usa el badge de "Lotes" del mockup de
     * referencia, sin inventar un tercer estado "parcial" que ninguna otra
     * pantalla del sistema usa todavía.
     *
     * @return list<array{lote_id: int, label: string, hectareas_solicitadas: string, asignadas: string, restantes: string, estado: string}>
     */
    private function detalleLotesOrden(OrdenAplicacion $orden): array
    {
        $ordenLotes = $orden->ordenLotes()->orderBy('lote_id')->get();
        $etiquetas = $this->etiquetasLote($ordenLotes->pluck('lote_id')->map(fn ($id) => (int) $id)->all());

        return $ordenLotes->map(function ($ordenLote) use ($orden, $etiquetas): array {
            $solicitadas = BigDecimal::of((string) $ordenLote->hectareas_solicitadas);
            $asignadas = BigDecimal::of((string) Trabajo::query()
                ->where('orden_id', $orden->id)
                ->where('lote_id', $ordenLote->lote_id)
                ->sum('hectareas_declaradas'));
            $restantes = $solicitadas->minus($asignadas);

            return [
                'lote_id' => $ordenLote->lote_id,
                'label' => $etiquetas[$ordenLote->lote_id] ?? "#{$ordenLote->lote_id}",
                'hectareas_solicitadas' => (string) $solicitadas,
                'asignadas' => (string) $asignadas,
                'restantes' => (string) $restantes,
                'estado' => $restantes->isLessThanOrEqualTo(BigDecimal::zero()) ? 'asignado' : 'pendiente',
            ];
        })->all();
    }

    private function equiposAsignadosCount(OrdenAplicacion $orden): int
    {
        return Trabajo::query()
            ->where('orden_id', $orden->id)
            ->whereNotNull('equipo_trabajo_id')
            ->distinct()
            ->count('equipo_trabajo_id');
    }

    /**
     * Nombre visible de un autor (`created_by`/`updated_by`, FK plana a
     * `sec_user` — ningún modelo de dominio tiene relación Eloquent hacia
     * `Seguridad`). Mismo patrón que `PlanillasController::show()`:
     * `sec_user.name` es texto libre propio del usuario (lo setea
     * `AsignarRolesUsuario`), no se deriva de `per_personas`. No existe hoy
     * ningún trait/caso de uso compartido para esta resolución — cada
     * pantalla que lo necesita repite este mismo `DB::table`.
     *
     * `null` si no hay autor registrado (fila de auditoría vieja, o el write
     * corrió sin usuario autenticado); `"#id"` si el usuario ya no existe.
     */
    private function nombreAutor(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return DB::table('sec_user')->where('id', $userId)->value('name') ?? "#{$userId}";
    }

    /**
     * Actividad de la orden para `show()`: solo eventos RECONSTRUIBLES desde
     * columnas reales (nunca inventados — no hay bitácora antes/después
     * todavía, ver `docs/decisiones/0007-...`). Máximo 3 tipos:
     *
     * 1. Emitida — siempre, `created_at`/`created_by` de la orden.
     * 2. Activada — solo si el estado ya avanzó de `emitida`; usa
     *    `updated_at`/`updated_by` de la orden como proxy válido del
     *    instante de activación: una orden `vigente` ya no admite edición
     *    (`Aplicacion/ActualizarOrden`), así que nada vuelve a tocar esas
     *    columnas después de `ActivarOrden::ejecutar()`.
     * 3. Equipo asignado — uno por `equipo_trabajo_id` distinto entre los
     *    `Trabajo` de esta orden, con la fecha/autor MÍNIMOS del grupo
     *    (el instante en que ESE equipo entró al reparto) y la suma de
     *    hectáreas que le tocaron.
     *
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function actividadOrden(OrdenAplicacion $orden): array
    {
        $eventos = [[
            'title' => __('operaciones.ordenes.actividad_emitida'),
            'meta' => $this->metaActividad($orden->created_at, $orden->created_by),
            'tone' => 'neutral',
        ]];

        if ($orden->estado !== EstadoOrdenAplicacion::Emitida) {
            $eventos[] = [
                'title' => __('operaciones.ordenes.actividad_activada'),
                'meta' => $this->metaActividad($orden->updated_at, $orden->updated_by),
                'tone' => 'success',
            ];
        }

        $trabajosPorEquipo = Trabajo::query()
            ->where('orden_id', $orden->id)
            ->whereNotNull('equipo_trabajo_id')
            ->orderBy('created_at')
            ->get(['equipo_trabajo_id', 'created_at', 'created_by', 'hectareas_declaradas'])
            ->groupBy('equipo_trabajo_id');

        if ($trabajosPorEquipo->isNotEmpty()) {
            $etiquetasEquipo = DB::table('per_equipos_trabajo')
                ->whereIn('id', $trabajosPorEquipo->keys()->all())
                ->pluck('codigo', 'id');

            foreach ($trabajosPorEquipo as $equipoId => $trabajos) {
                $primero = $trabajos->first();
                if ($primero === null) {
                    continue;
                }

                $hectareas = array_reduce(
                    $trabajos->all(),
                    fn (BigDecimal $acumulado, Trabajo $trabajo): BigDecimal => $acumulado->plus((string) $trabajo->hectareas_declaradas),
                    BigDecimal::zero(),
                );

                $eventos[] = [
                    'title' => __('operaciones.ordenes.actividad_equipo_asignado', [
                        'equipo' => $etiquetasEquipo[$equipoId] ?? "#{$equipoId}",
                        'hectareas' => $this->aHectareas($hectareas),
                    ]),
                    'meta' => $this->metaActividad($primero->created_at, $primero->created_by),
                    'tone' => 'info',
                ];
            }
        }

        return $eventos;
    }

    private function metaActividad(?\DateTimeInterface $fecha, ?int $autorId): string
    {
        $fechaTexto = $fecha?->format('d/m/Y H:i') ?? '—';
        $autor = $this->nombreAutor($autorId);

        return $autor !== null
            ? __('operaciones.ordenes.actividad_meta', ['fecha' => $fechaTexto, 'autor' => $autor])
            : $fechaTexto;
    }

    public function update(ActualizarOrdenRequest $request, OrdenAplicacion $orden, ActualizarOrden $actualizarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarOrden->ejecutar($orden, $this->normalizarDatos($datos), $this->normalizarLotes($datos));
        } catch (OrdenNoEditable $excepcion) {
            return redirect()
                ->route('panel.ordenes.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::update()).
        return redirect()
            ->route('panel.ordenes.edit', $orden)
            ->with('estado', __('operaciones.ordenes.actualizada'));
    }

    public function activar(Request $request, OrdenAplicacion $orden, ActivarOrden $activarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR), 403);

        try {
            $activarOrden->ejecutar($orden);
        } catch (TransicionOrdenNoPermitida|OrdenVigenteDuplicadaEnLote $excepcion) {
            return redirect()
                ->route('panel.ordenes.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.index')
            ->with('estado', __('operaciones.ordenes.activada'));
    }

    public function destroy(Request $request, OrdenAplicacion $orden, EliminarOrden $eliminarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarOrden->ejecutar($orden);
        } catch (OrdenVigenteNoEliminable $excepcion) {
            return redirect()
                ->route('panel.ordenes.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.index')
            ->with('estado', __('operaciones.ordenes.eliminada'));
    }

    /**
     * @param  array<string, mixed>  $datos  validados
     * @return array<string, mixed>
     */
    private function normalizarDatos(array $datos): array
    {
        $categoriaInsumoId = (int) $datos['categoria_insumo_id'];
        // `DB::table` (no `CategoriaInsumo::query()`): un `->value()` sobre un
        // Eloquent Builder resuelve por `first()` y devuelve el enum YA
        // CASTEADO, no el string crudo — comparar eso contra `->value` nunca
        // da true. Mismo criterio (y misma trampa evitada) que
        // `CrearOrdenRequest::validarCampoSegunCategoriaInsumo()`.
        $tipoInsumo = DB::table('ope_categorias_insumo')->where('id', $categoriaInsumoId)->value('tipo_insumo');

        return [
            'contrato_id' => (int) $datos['contrato_id'],
            'nro_aplicacion' => (int) $datos['nro_aplicacion'],
            'cantidad_equipos_necesarios' => (int) $datos['cantidad_equipos_necesarios'],
            'tipo_aplicacion' => TipoAplicacion::from((string) $datos['tipo_aplicacion']),
            'categoria_insumo_id' => $categoriaInsumoId,
            // Cuál de los dos guarda un valor depende del tipo_insumo de la
            // categoría, no de lo que haya venido en el POST (invariante
            // 5-ish: la fuente de verdad es la categoría elegida, nunca un
            // campo oculto que el navegador no mandó a tiempo) — el que no
            // corresponde siempre queda NULL, aunque el request lo mande.
            'kilos_por_vuelo' => $tipoInsumo === TipoInsumo::Solido->value ? $this->cadenaONull($datos['kilos_por_vuelo'] ?? null) : null,
            'litros_ha' => $tipoInsumo === TipoInsumo::Liquido->value ? $this->cadenaONull($datos['litros_ha'] ?? null) : null,
            'humedad_min_pct' => $this->cadenaONull($datos['humedad_min_pct'] ?? null),
            'humedad_max_pct' => $this->cadenaONull($datos['humedad_max_pct'] ?? null),
            'viento_max_kmh' => $this->cadenaONull($datos['viento_max_kmh'] ?? null),
            'temperatura_max_c' => $this->cadenaONull($datos['temperatura_max_c'] ?? null),
            'velocidad_max_kmh' => $this->cadenaONull($datos['velocidad_max_kmh'] ?? null),
            'altura_vuelo_m' => $this->cadenaONull($datos['altura_vuelo_m'] ?? null),
            'velocidad_vuelo_kmh' => $this->cadenaONull($datos['velocidad_vuelo_kmh'] ?? null),
            'ancho_pasada_m' => $this->cadenaONull($datos['ancho_pasada_m'] ?? null),
            'observaciones' => $this->cadenaONull($datos['observaciones'] ?? null),
            'emitida_por_contacto_id' => isset($datos['emitida_por_contacto_id']) && $datos['emitida_por_contacto_id'] !== ''
                ? (int) $datos['emitida_por_contacto_id']
                : null,
            'fecha_emision' => (string) $datos['fecha_emision'],
        ];
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /**
     * @param  array<string, mixed>  $datos  validados
     * @return list<array{lote_id: int, hectareas_solicitadas: string}>
     */
    private function normalizarLotes(array $datos): array
    {
        return array_map(
            static fn (array $lote): array => [
                'lote_id' => (int) $lote['lote_id'],
                'hectareas_solicitadas' => (string) $lote['hectareas_solicitadas'],
            ],
            array_values($datos['lotes']),
        );
    }

    /** @return Collection<int, string> */
    private function contratosDisponibles(): Collection
    {
        return DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereNull('c.deleted_at')
            ->whereNull('cl.deleted_at')
            ->orderByDesc('c.fecha_inicio')
            ->get(['c.id', 'cl.razon_social'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_contrato_opcion', [
                    'id' => $fila->id,
                    'cliente' => $fila->razon_social,
                ]),
            ]);
    }

    /**
     * Categorías de insumo (HU-79, tarea 110) — catálogo PROPIO de
     * Operaciones (no de Comercial): a diferencia de `lotesDisponibles()` y
     * el resto de abajo, se lee por el modelo Eloquent del módulo, no por
     * `DB::table` (ADR 0003 regla 3 solo exige lectura directa cruzando
     * MÓDULOS). La vista arma el `<select>` y el mapa id→tipo_insumo a partir
     * de esta colección de modelos.
     *
     * @return Collection<int, CategoriaInsumo>
     */
    private function categoriasInsumoDisponibles(): Collection
    {
        return CategoriaInsumo::query()->orderBy('nombre')->get(['id', 'nombre', 'tipo_insumo']);
    }

    /** @return Collection<int, string> */
    private function lotesDisponibles(): Collection
    {
        return DB::table('com_lotes as l')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereNull('l.deleted_at')
            ->whereNull('p.deleted_at')
            ->orderBy('p.nombre')
            ->orderBy('l.codigo')
            ->get(['l.id', 'p.nombre', 'l.codigo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_lote_opcion', [
                    'campo' => $fila->nombre,
                    'codigo' => $fila->codigo,
                ]),
            ]);
    }

    /**
     * De qué cliente es cada contrato — consistencia de negocio (el contrato
     * es el QUIÉN, la orden es el CÓMO): el formulario del panel usa esto
     * para filtrar, en JS, el universo de `lotesDisponibles()` al cliente del
     * contrato elegido (nunca al revés — un lote no sabe de contratos). El
     * servidor exige lo mismo en `withValidator()`; esto es solo el dato para
     * la presentación.
     *
     * @return array<int, int> contrato_id => cliente_id
     */
    private function mapaContratoCliente(): array
    {
        return DB::table('com_contratos')
            ->whereNull('deleted_at')
            ->pluck('cliente_id', 'id')
            ->all();
    }

    /**
     * De qué cliente es cada lote (vía `propiedad_id` → `cliente_id`) —
     * mismo criterio que {@see mapaContratoCliente()}.
     *
     * @return array<int, int> lote_id => cliente_id
     */
    private function mapaLoteCliente(): array
    {
        return DB::table('com_lotes as l')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereNull('l.deleted_at')
            ->whereNull('p.deleted_at')
            ->pluck('p.cliente_id', 'l.id')
            ->all();
    }

    /**
     * Etiquetas legibles para la columna "Contrato" del listado (mismo
     * criterio de lectura directa por `DB::table` que los selects del
     * formulario — ADR 0003 regla 3). Un contrato borrado lógicamente
     * después de emitida la orden queda fuera del mapa a propósito: la vista
     * cae al `#id` crudo, no hace falta un JOIN con `deleted_at` nulo
     * cuando lo único que se pinta es una etiqueta histórica.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function etiquetasContrato(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereIn('c.id', $ids)
            ->get(['c.id', 'cl.razon_social'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_contrato_opcion', [
                    'id' => $fila->id,
                    'cliente' => $fila->razon_social,
                ]),
            ])
            ->all();
    }

    /**
     * Etiquetas legibles para la columna "Lote" del listado, mismo criterio
     * que `etiquetasContrato()`.
     *
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

    /** @return Collection<int, string> */
    private function contactosDisponibles(): Collection
    {
        return DB::table('com_cliente_contactos as cc')
            ->join('com_clientes as cl', 'cl.id', '=', 'cc.cliente_id')
            ->whereNull('cc.deleted_at')
            ->whereNull('cl.deleted_at')
            ->orderBy('cc.nombre')
            ->get(['cc.id', 'cc.nombre', 'cl.razon_social'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_contacto_opcion', [
                    'nombre' => $fila->nombre,
                    'cliente' => $fila->razon_social,
                ]),
            ]);
    }
}
