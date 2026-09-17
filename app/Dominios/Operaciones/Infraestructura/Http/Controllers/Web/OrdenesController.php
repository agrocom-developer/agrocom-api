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

        $hectareasSolicitadas = BigDecimal::of((string) $orden->ordenLotes()->sum('hectareas_solicitadas'));
        $hectareasAsignadas = BigDecimal::of((string) Trabajo::query()->where('orden_id', $orden->id)->sum('hectareas_declaradas'));
        $equiposAsignados = Trabajo::query()->where('orden_id', $orden->id)->whereNotNull('equipo_trabajo_id')->distinct()->count('equipo_trabajo_id');

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
