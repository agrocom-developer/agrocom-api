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
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
 *
 * `create()`/`edit()` (reforma 18/9/2026, Entrega 1): el `<select>` plano de
 * contrato viaja junto a un blob más rico por contrato
 * (`datosContratoParaFormulario()`) — cliente, propiedad(es), contactos y
 * SOLO los lotes de ese contrato, para la sección "Datos del contrato" del
 * formulario. `create()` además precarga, por cada contrato, el
 * `nro_aplicacion` sugerido (`sugerirNroAplicacion()`) — `edit()` no, la
 * orden ya tiene el suyo real. Los 8 campos de límites climáticos/parámetros
 * de vuelo YA NO se piden acá (se movieron a `Trabajo`, cargados por equipo
 * en `AsignarEquipoOrdenRequest` — ver su docblock).
 */
final class OrdenesController
{
    private const PERMISO_VER = 'operaciones.orden.ver';

    private const PERMISO_CREAR = 'operaciones.orden.crear';

    private const PERMISO_EDITAR = 'operaciones.orden.editar';

    private const PERMISO_ACTIVAR = 'operaciones.orden.activar';

    private const PERMISO_ELIMINAR = 'operaciones.orden.eliminar';

    private const PERMISO_ASIGNAR_EQUIPOS = 'operaciones.orden.asignar_equipos';

    private const PERMISO_VER_TRABAJOS = 'operaciones.trabajo.ver';

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

        return view('operaciones::pages.ordenes.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'etiquetasContrato' => $this->etiquetasContrato($ordenes->pluck('contrato_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'etiquetasLote' => $this->etiquetasLote($todosLosLoteIds),
            'loteIdsPorOrden' => $loteIdsPorOrden,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'tipo_aplicacion' => $tipoAplicacion?->value],
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

        $datosContrato = $this->datosContratoParaFormulario();

        // Sugerencia de `nro_aplicacion` SOLO acá (ver docblock de
        // `sugerirNroAplicacion()`) — se completa por fuera del blob base
        // porque no tiene sentido pagarla en `edit()`, donde la orden ya
        // trae su propio valor real.
        foreach (array_keys($datosContrato) as $contratoId) {
            $datosContrato[$contratoId]['nro_aplicacion_sugerido'] = $this->sugerirNroAplicacion($contratoId);
        }

        return view('operaciones::pages.ordenes.create', [
            ...$this->autorizacion->cascara($request),
            'contratosDisponibles' => collect($datosContrato)->map(fn (array $datos): string => $datos['label']),
            'datosContrato' => $datosContrato,
            'contactosDisponibles' => $this->contactosDisponibles(),
            'categoriasInsumoDisponibles' => $this->categoriasInsumoDisponibles(),
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
            'vinculos' => $this->vinculosOrden($orden, $request),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeActivar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    /**
     * "Vínculos" de `show()`: accesos a información relacionada que vive en
     * OTRA pantalla, no se duplica acá (mismo espíritu que el resumen
     * relacionado del arquetipo Formulario, §6.3.1, pero como filas sueltas
     * — `molecules/link-row` — en vez de tarjetas completas).
     *
     * Solo dos, a propósito, ambos con filtro REAL del lado del destino:
     * - Órdenes de trabajo: `panel.trabajos.index` acepta `orden_id`
     *   (`TrabajosController::index()`/`Aplicacion/ListarTrabajos`).
     * - Asignación de equipos: `panel.asignacion-equipos.show` es la ficha
     *   propia de ESTA orden.
     *
     * Deliberadamente NO hay un tercer vínculo a "Seguimiento de vuelos"
     * (`panel.sesiones.validacion.index`): esa pantalla es la cola de
     * VALIDACIÓN pendiente (`ValidacionSesionesController::index()`, sesiones
     * `cerrado` sin validar), no un historial de vuelos de la orden — un
     * enlace filtrado ahí se vería vacío la mayor parte del tiempo (en
     * cuanto se validan, salen de la cola) y prometería un seguimiento que
     * esa pantalla no da todavía (mismo criterio ya documentado para el
     * título de esa pantalla, no se repite acá). Tampoco hay vínculo a
     * Finanzas: `Gastos` solo filtra por `trabajo_id` (`ListarGastos`), no
     * por `orden_id`, y una orden puede tener varios trabajos — un enlace
     * a un solo trabajo sería arbitrario.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosOrden(OrdenAplicacion $orden, Request $request): array
    {
        $vinculos = [];

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER_TRABAJOS)) {
            $totalTrabajos = Trabajo::query()->where('orden_id', $orden->id)->count();

            $vinculos[] = [
                'href' => route('panel.trabajos.index', ['orden_id' => $orden->id]),
                'icon' => 'work_history',
                'title' => __('operaciones.ordenes.vinculo_trabajos'),
                'meta' => __('operaciones.ordenes.vinculo_trabajos_meta', ['cantidad' => $totalTrabajos]),
                // Tono FIJO, no condicionado a `$totalTrabajos > 0` (18/9/2026,
                // pedido explícito del usuario: cada vínculo de esta tarjeta
                // lleva su propio color, no gris hasta que haya datos — a
                // diferencia de los KPI de más arriba en show.blade.php,
                // que sí arrancan neutros a propósito).
                'tone' => 'info',
            ];
        }

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR_EQUIPOS)) {
            $equiposAsignados = $this->equiposAsignadosCount($orden);

            $vinculos[] = [
                'href' => route('panel.asignacion-equipos.show', $orden),
                'icon' => 'groups',
                'title' => __('operaciones.ordenes.vinculo_asignacion'),
                'meta' => __('operaciones.ordenes.vinculo_asignacion_meta', ['cantidad' => $equiposAsignados]),
                // Tono FIJO — mismo motivo que el vínculo de trabajos de arriba.
                'tone' => 'success',
            ];
        }

        return $vinculos;
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

        $datosContrato = $this->datosContratoParaFormulario();

        return view('operaciones::pages.ordenes.edit', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'lotesOrden' => $orden->ordenLotes()->orderBy('lote_id')->get(),
            'contratosDisponibles' => collect($datosContrato)->map(fn (array $datos): string => $datos['label']),
            'datosContrato' => $datosContrato,
            'contactosDisponibles' => $this->contactosDisponibles(),
            'categoriasInsumoDisponibles' => $this->categoriasInsumoDisponibles(),
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

    /**
     * Categorías de insumo (HU-79, tarea 110) — catálogo PROPIO de
     * Operaciones (no de Comercial): a diferencia del resto de este
     * controlador, se lee por el modelo Eloquent del módulo, no por
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

    /**
     * Reemplaza, desde la reforma 18/9/2026 (Entrega 1), a los antiguos
     * `contratosDisponibles()`/`lotesDisponibles()`/`mapaContratoCliente()`/
     * `mapaLoteCliente()`: hasta entonces el formulario ofrecía el UNIVERSO
     * completo de lotes del sistema en un `<select>` aparte y filtraba en JS
     * con un mapa contrato→cliente/lote→cliente. Acá cada contrato trae de
     * entrada SOLO sus propios datos — ya no hace falta filtrar a ciegas
     * contra todos los lotes del sistema.
     *
     * Shape devuelto, indexado por `contrato_id` (para quien arme la vista):
     *
     *     [
     *       $contratoId => [
     *         'label' => string,              // ya enriquecido para el <select> buscable: cliente + propiedad(es) + "Contrato #id"
     *         'cliente' => string,            // razón social
     *         'logo_url' => ?string,          // URL pública del logo del cliente (com_clientes.logo_path vía disco `public`), null sin logo
     *         'propiedades' => list<string>,  // nombres de propiedad(es) que cubre el contrato — puede ser más de una (com_contrato_lotes cruza propiedades)
     *         'aplicaciones_previstas' => int,
     *         'hectareas_contratadas' => string,  // DECIMAL como string (invariante 6)
     *         'fecha_inicio' => string,           // ya formateada "d/m/Y" (ADR 0013)
     *         'fecha_fin' => ?string,              // ídem, null si el contrato no tiene fecha de fin
     *         'contactos' => list<array{id: int, nombre: string, tipo: string}>,  // com_cliente_contactos del cliente DUEÑO del contrato; la vista decide autoseleccionar si hay uno solo
     *         'lotes' => list<array{lote_id: int, codigo: string, propiedad: string, hectareas: string, desnivel: ?string, desnivel_label: ?string, limpieza: ?string, limpieza_label: ?string}>,  // SOLO los lotes de `com_contrato_lotes` de ESTE contrato — desnivel/limpieza YA traducidos server-side (ADR 0013), mismo criterio que `ContratosController::propiedadesYLotesPorCliente()`
     *         'nro_aplicacion_sugerido' => int|null,  // NULL acá siempre — solo `create()` lo completa (ver `sugerirNroAplicacion()`); `edit()` no lo toca, la orden ya tiene su valor real
     *       ],
     *       ...
     *     ]
     *
     * `label` reutiliza la clave de traducción existente
     * `operaciones.ordenes.campo_contrato_opcion` (":cliente — Contrato
     * #:id") pasándole en `:cliente` el nombre YA concatenado con la(s)
     * propiedad(es) entre paréntesis — no se agrega una clave `:propiedad`
     * nueva a `lang/es/operaciones.php` desde acá (fuera de alcance de este
     * cambio de backend); el combobox buscable (`x-atoms.select` con
     * `searchable`) ya puede filtrar por cliente, propiedad o número de
     * contrato con este único string.
     *
     * Tres consultas en total (contratos+cliente, lotes del contrato +
     * propiedad, contactos del cliente), agrupadas en PHP — evita N+1 por
     * contrato. Lectura directa por `DB::table` en las tablas de `Comercial`
     * (ADR 0003 regla 3, mismo criterio que el resto del controlador).
     *
     * @return array<int, array{label: string, cliente: string, logo_url: ?string, propiedades: list<string>, aplicaciones_previstas: int, hectareas_contratadas: string, fecha_inicio: string, fecha_fin: ?string, contactos: list<array{id: int, nombre: string, tipo: string}>, lotes: list<array{lote_id: int, codigo: string, propiedad: string, hectareas: string, desnivel: ?string, desnivel_label: ?string, limpieza: ?string, limpieza_label: ?string}>, nro_aplicacion_sugerido: int|null}>
     */
    private function datosContratoParaFormulario(): array
    {
        $contratos = DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereNull('c.deleted_at')
            ->whereNull('cl.deleted_at')
            ->orderByDesc('c.fecha_inicio')
            ->get(['c.id', 'c.cliente_id', 'c.aplicaciones_previstas', 'c.hectareas_contratadas', 'c.fecha_inicio', 'c.fecha_fin', 'cl.razon_social', 'cl.logo_path']);

        if ($contratos->isEmpty()) {
            return [];
        }

        $contratoIds = $contratos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $clienteIds = $contratos->pluck('cliente_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $lotesPorContrato = DB::table('com_contrato_lotes as ccl')
            ->join('com_lotes as l', 'l.id', '=', 'ccl.lote_id')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereIn('ccl.contrato_id', $contratoIds)
            ->whereNull('ccl.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNull('p.deleted_at')
            ->orderBy('p.nombre')
            ->orderBy('l.codigo')
            ->get(['ccl.contrato_id', 'l.id as lote_id', 'l.codigo', 'l.hectareas', 'l.desnivel', 'l.limpieza', 'p.nombre as propiedad_nombre'])
            ->groupBy('contrato_id');

        $contactosPorCliente = DB::table('com_cliente_contactos')
            ->whereIn('cliente_id', $clienteIds)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'cliente_id', 'tipo', 'nombre'])
            ->groupBy('cliente_id');

        $resultado = [];

        foreach ($contratos as $contrato) {
            $contratoId = (int) $contrato->id;
            $clienteId = (int) $contrato->cliente_id;

            $lotesDelContrato = $lotesPorContrato->get($contratoId) ?? collect();
            $propiedades = $lotesDelContrato->pluck('propiedad_nombre')->unique()->values()->all();

            $resultado[$contratoId] = [
                'label' => __('operaciones.ordenes.campo_contrato_opcion', [
                    'id' => $contratoId,
                    'cliente' => $propiedades === []
                        ? $contrato->razon_social
                        : "{$contrato->razon_social} (".implode(', ', $propiedades).')',
                ]),
                'cliente' => $contrato->razon_social,
                'logo_url' => $this->logoUrl($contrato->logo_path),
                'propiedades' => $propiedades,
                'aplicaciones_previstas' => (int) $contrato->aplicaciones_previstas,
                'hectareas_contratadas' => (string) $contrato->hectareas_contratadas,
                // `DB::table` (no Eloquent): fecha_inicio/fecha_fin llegan
                // como string crudo de Postgres ("Y-m-d"), no Carbon — se
                // formatean acá, no en la vista (ADR 0013, mismo criterio
                // que desnivel_label/limpieza_label de los lotes).
                'fecha_inicio' => CarbonImmutable::parse($contrato->fecha_inicio)->format('d/m/Y'),
                'fecha_fin' => $contrato->fecha_fin !== null ? CarbonImmutable::parse($contrato->fecha_fin)->format('d/m/Y') : null,
                'contactos' => ($contactosPorCliente->get($clienteId) ?? collect())
                    ->map(fn (object $contacto): array => [
                        'id' => (int) $contacto->id,
                        'nombre' => $contacto->nombre,
                        'tipo' => $contacto->tipo,
                    ])
                    ->values()
                    ->all(),
                'lotes' => $lotesDelContrato->map(fn (object $lote): array => [
                    'lote_id' => (int) $lote->lote_id,
                    'codigo' => $lote->codigo,
                    'propiedad' => $lote->propiedad_nombre,
                    'hectareas' => (string) $lote->hectareas,
                    'desnivel' => $lote->desnivel,
                    'desnivel_label' => $lote->desnivel !== null ? __("comercial.lotes.lote_desnivel_{$lote->desnivel}") : null,
                    'limpieza' => $lote->limpieza,
                    'limpieza_label' => $lote->limpieza !== null ? __("comercial.lotes.lote_limpieza_{$lote->limpieza}") : null,
                ])->values()->all(),
                'nro_aplicacion_sugerido' => null,
            ];
        }

        return $resultado;
    }

    /**
     * URL pública del logo del cliente (`com_clientes.logo_path`, ruta
     * relativa del disco `public`) — mismo criterio de resolución que
     * `Comercial\ContratosController::logoArchivo()`/`ClientesController`
     * (ADR 0019): `null` sin logo guardado o si el archivo ya no existe en
     * disco, la vista ya sabe mostrar el ícono de reemplazo.
     */
    private function logoUrl(?string $logoPath): ?string
    {
        if ($logoPath === null) {
            return null;
        }

        $disco = Storage::disk('public');

        return $disco->exists($logoPath) ? $disco->url($logoPath) : null;
    }

    /**
     * Sugerencia de `nro_aplicacion` para el formulario de ALTA (`create()`
     * únicamente — en `edit()` la orden ya tiene su valor real, no hay nada
     * que sugerir). Caso real que motiva el algoritmo: un contrato grande
     * (3000ha en lotes de 40-80ha) reparte una misma "aplicación" en varias
     * órdenes con el MISMO número porque sus hectáreas no entran en una sola
     * orden — mientras esa ronda no cubra todos los lotes del contrato, el
     * sugerido sigue ofreciendo ese mismo número; recién cuando la ronda
     * queda completa sugiere el siguiente.
     *
     * Algoritmo: busca, entre las órdenes no eliminadas de este contrato con
     * sus lotes (`ope_orden_lotes` no eliminados), el `nro_aplicacion` más
     * alto ya usado. Sin ninguna orden previa, sugiere 1. Si hay, junta el
     * conjunto de `lote_id` ya cubiertos por TODAS las órdenes con ESE mismo
     * número más alto; si ese conjunto no incluye todos los `lote_id` de
     * `com_contrato_lotes` del contrato, sugiere ese mismo número (ronda
     * incompleta); si los cubre todos, sugiere `número + 1`.
     *
     * Deliberadamente simple (dos consultas, sin índices ni caché
     * especiales): es solo un valor de arranque editable en el formulario,
     * no una regla de negocio que el servidor haga cumplir —
     * `CrearOrdenRequest` no exige que `nro_aplicacion` coincida con esta
     * sugerencia.
     */
    private function sugerirNroAplicacion(int $contratoId): int
    {
        $nroMasAlto = DB::table('ope_ordenes_aplicacion as o')
            ->join('ope_orden_lotes as ol', 'ol.orden_id', '=', 'o.id')
            ->where('o.contrato_id', $contratoId)
            ->whereNull('o.deleted_at')
            ->whereNull('ol.deleted_at')
            ->max('o.nro_aplicacion');

        if ($nroMasAlto === null) {
            return 1;
        }

        $loteIdsCubiertos = DB::table('ope_ordenes_aplicacion as o')
            ->join('ope_orden_lotes as ol', 'ol.orden_id', '=', 'o.id')
            ->where('o.contrato_id', $contratoId)
            ->where('o.nro_aplicacion', $nroMasAlto)
            ->whereNull('o.deleted_at')
            ->whereNull('ol.deleted_at')
            ->pluck('ol.lote_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $loteIdsDelContrato = DB::table('com_contrato_lotes')
            ->where('contrato_id', $contratoId)
            ->whereNull('deleted_at')
            ->pluck('lote_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rondaCompleta = array_diff($loteIdsDelContrato, $loteIdsCubiertos) === [];

        return $rondaCompleta ? (int) $nroMasAlto + 1 : (int) $nroMasAlto;
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

    /**
     * Universo COMPLETO de contactos para poblar el `<select>` nativo (todas
     * las opciones existen siempre en el DOM) — `ordenes-form.js` oculta en
     * el cliente las que no son del cliente del contrato elegido (mismo
     * criterio que el resto de los selects dependientes de este formulario).
     * Label `:nombre — :tipo` (reforma 18/9/2026): antes repetía el cliente,
     * pero el select ya queda scopeado a UN cliente — el tipo (Dueño,
     * Agrónomo, etc.) es el dato que distingue entre varios contactos del
     * mismo cliente, ver `comercial.clientes.contacto_tipo_opcion`.
     *
     * @return Collection<int, string>
     */
    private function contactosDisponibles(): Collection
    {
        return DB::table('com_cliente_contactos as cc')
            ->join('com_clientes as cl', 'cl.id', '=', 'cc.cliente_id')
            ->whereNull('cc.deleted_at')
            ->whereNull('cl.deleted_at')
            ->orderBy('cc.nombre')
            ->get(['cc.id', 'cc.nombre', 'cc.tipo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_contacto_opcion', [
                    'nombre' => $fila->nombre,
                    'tipo' => __("comercial.clientes.contacto_tipo_opcion.{$fila->tipo}"),
                ]),
            ]);
    }
}
