<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\ActivarOrden;
use App\Dominios\Operaciones\Aplicacion\ActualizarOrden;
use App\Dominios\Operaciones\Aplicacion\CancelarOrden;
use App\Dominios\Operaciones\Aplicacion\CerrarOrden;
use App\Dominios\Operaciones\Aplicacion\CrearOrden;
use App\Dominios\Operaciones\Aplicacion\EliminarOrden;
use App\Dominios\Operaciones\Aplicacion\ListarOrdenesAplicacion;
use App\Dominios\Operaciones\Aplicacion\PausarOrden;
use App\Dominios\Operaciones\Aplicacion\ProximaAplicacionPorContrato;
use App\Dominios\Operaciones\Aplicacion\ReanudarOrden;
use App\Dominios\Operaciones\Aplicacion\ResumenDeOrdenes;
use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\AplicacionesCompletas;
use App\Dominios\Operaciones\Dominio\Excepciones\CierreOrdenNoPermitido;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoConOrdenAbierta;
use App\Dominios\Operaciones\Dominio\Excepciones\ContratoNoAdmiteOrdenes;
use App\Dominios\Operaciones\Dominio\Excepciones\CorreccionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEditable;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoEliminable;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\PoliticaEdicionOrden;
use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Dominio\TipoInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\CategoriaInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\PasosDeOrden;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarOrdenRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearOrdenRequest;
use App\Dominios\Personal\Contratos\DatosRecursoEquipo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/ordenes*` (HU-25 reforma Entrega 1, 18/9/2026):
 * alta y seguimiento de órdenes de aplicación (UNA por contrato, que cubre
 * TODOS sus lotes con aplicación completa). Máquina de estados:
 * emitida → vigente ⇄ pausada; vigente → consumida; vigente|pausada →
 * cancelada. Ver `Aplicacion/MaquinaEstados/MaquinaEstadosOrden`.
 * Mismo molde que `ContratosController` (cambio de estado separado de edición).
 * El estado se cambia desde los pasos (`molecules/step-arrow`) de la ficha de
 * edición y del detalle — `PasosDeOrden`, que abre los modales de
 * `_orden-modales.blade.php` —, y desde el listado.
 *
 * Ocho permisos de grano fino (`operaciones.orden.ver`/`.crear`/`.editar`
 * /`.activar`/`.pausar`/`.cerrar`/`.cancelar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}.
 * Ninguna regla de negocio acá: los casos de uso de `Aplicacion/` hacen el
 * trabajo.
 *
 * Los selects de `contrato_id` y los contactos se arman con consultas
 * directas a las tablas de `Comercial` (`DB::table`, sin importar sus modelos
 * Eloquent — ADR 0003 regla 3). De Personal (equipos y sus recursos) se lee
 * solo por `Personal\Contratos\LecturaEquipoTrabajo`.
 *
 * `create()`: el `<select>` de contrato solo muestra contratos `vigente` sin
 * orden abierta ni aplicaciones completas (filtro por
 * `ProximaAplicacionPorContrato::disponible`). Cada contrato trae sus lotes
 * como lista de solo lectura y los contactos de SU cliente — el formulario
 * nunca recibe los de otros clientes. `edit()`: contrato/lotes fijos, solo
 * edita tipo/categoría/dosis/equipos/contacto/fecha/observaciones (no contrato).
 */
final class OrdenesController
{
    private const PERMISO_VER = 'operaciones.orden.ver';

    private const PERMISO_CREAR = 'operaciones.orden.crear';

    private const PERMISO_EDITAR = 'operaciones.orden.editar';

    private const PERMISO_ACTIVAR = 'operaciones.orden.activar';

    private const PERMISO_PAUSAR = 'operaciones.orden.pausar';

    private const PERMISO_CERRAR = 'operaciones.orden.cerrar';

    private const PERMISO_CANCELAR = 'operaciones.orden.cancelar';

    private const PERMISO_ELIMINAR = 'operaciones.orden.eliminar';

    private const PERMISO_ASIGNAR_EQUIPOS = 'operaciones.orden.asignar_equipos';

    private const PERMISO_VER_TRABAJOS = 'operaciones.trabajo.ver';

    private const PERMISO_CREAR_TRABAJOS = 'operaciones.trabajo.crear';

    private const PERMISO_EDITAR_CONTRATO = 'comercial.contrato.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarOrdenesAplicacion $listarOrdenes, ResumenDeOrdenes $resumenOrdenes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoOrdenAplicacion::tryFrom($estadoQuery) : null;

        $tipoAplicacionQuery = $request->string('tipo_aplicacion')->toString();
        $tipoAplicacion = $tipoAplicacionQuery !== '' ? TipoAplicacion::tryFrom($tipoAplicacionQuery) : null;

        $contratoIdQuery = $request->integer('contrato_id') ?: null;
        $nroAplicacionQuery = $request->integer('nro_aplicacion') ?: null;

        $ordenes = $listarOrdenes->ejecutar(
            estado: $estado,
            nroAplicacion: $nroAplicacionQuery,
            tipoAplicacion: $tipoAplicacion,
            contratoIds: $contratoIdQuery !== null ? [$contratoIdQuery] : ($busqueda !== '' ? $this->contratoIdsPorBusqueda($busqueda) : null),
        );

        $ordenIds = $ordenes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $resumen = $resumenOrdenes->ejecutar($ordenIds);
        $contratoIds = $ordenes->pluck('contrato_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        // Las opciones de los filtros de contrato y de aplicación salen de TODAS las
        // órdenes, no de la página que se ve: no cambian al filtrar.
        $contratoIdsConOrdenes = OrdenAplicacion::query()->distinct()->pluck('contrato_id')->map(fn ($id) => (int) $id)->all();

        return view('operaciones::pages.ordenes.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'resumen' => $resumen,
            'etiquetasContrato' => $this->etiquetasContrato($contratoIds),
            'previstasPorContrato' => $this->previstasPorContrato($contratoIds),
            'opcionesContrato' => $this->opcionesContratoParaFiltro($contratoIdsConOrdenes),
            'opcionesAplicacion' => $this->opcionesAplicacionParaFiltro($contratoIdsConOrdenes),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'tipo_aplicacion' => $tipoAplicacion?->value, 'contrato_id' => $contratoIdQuery, 'nro_aplicacion' => $nroAplicacionQuery],
            'puedeActivar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR),
            'puedePausar' => $this->autorizacion->tienePermiso($request, self::PERMISO_PAUSAR),
            'puedeCerrar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR),
            'puedeCancelar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CANCELAR),
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    /**
     * Opciones del filtro «Contrato» del listado: los contratos que tienen al
     * menos una orden, con el cliente y el número («Cliente — Contrato #35»),
     * ordenados por esa etiqueta.
     *
     * @param  list<int>  $contratoIds
     * @return array<int, string> contrato_id => etiqueta
     */
    private function opcionesContratoParaFiltro(array $contratoIds): array
    {
        return collect($this->etiquetasContrato($contratoIds))
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->all();
    }

    /**
     * Opciones del filtro «Aplicación» del listado, en palabras: «Primera
     * aplicación», «Segunda aplicación»… hasta la mayor cantidad de aplicaciones
     * que tenga un contrato con órdenes (con dos contratos, uno de 3 y otro de 2,
     * son tres). Más allá de la décima cae a «Aplicación 11».
     *
     * @param  list<int>  $contratoIds
     * @return array<int, string> número de aplicación => etiqueta
     */
    private function opcionesAplicacionParaFiltro(array $contratoIds): array
    {
        $maximo = $contratoIds === [] ? 0 : max([0, ...array_values($this->previstasPorContrato($contratoIds))]);
        $opciones = [];

        for ($nro = 1; $nro <= $maximo; $nro++) {
            $opciones[$nro] = $nro <= 10
                ? __("operaciones.ordenes.aplicacion_ordinal.{$nro}")
                : __('operaciones.ordenes.aplicacion_numero', ['nro' => $nro]);
        }

        return $opciones;
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

    public function create(Request $request, ProximaAplicacionPorContrato $proximaAplicacion): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        // Solo contratos `vigente` (ADR 0022) y, de esos, los que admiten una
        // orden nueva: sin aplicación abierta y sin haber agotado las previstas.
        $allData = $this->datosContratoParaFormulario(null, true);

        $datosContrato = [];
        $proximasAplicaciones = $proximaAplicacion->ejecutar(
            array_map(fn (array $datos): int => $datos['aplicaciones_previstas'], $allData),
        );

        foreach ($proximasAplicaciones as $contratoId => $estado) {
            if ($estado['estado'] === ProximaAplicacionPorContrato::DISPONIBLE && isset($allData[$contratoId])) {
                $datosContrato[$contratoId] = [...$allData[$contratoId], 'siguiente_nro' => $estado['siguiente']];
            }
        }

        return view('operaciones::pages.ordenes.create', [
            ...$this->autorizacion->cascara($request),
            'contratosDisponibles' => collect($datosContrato)->map(fn (array $datos): string => $datos['label']),
            'datosContrato' => $datosContrato,
            'categoriasInsumoDisponibles' => $this->categoriasInsumoDisponibles(),
            'contratoIdPreseleccionado' => $request->integer('contrato_id') ?: null,
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
    public function show(Request $request, OrdenAplicacion $orden, ResumenDeOrdenes $resumenOrdenes, LecturaEquipoTrabajo $equipos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $orden->load('categoriaInsumo');

        $lotes = $this->detalleLotesOrden($orden, $equipos);
        $hectareasSolicitadas = array_reduce($lotes, fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['hectareas_solicitadas']), BigDecimal::zero());
        $hectareasAsignadas = array_reduce($lotes, fn (BigDecimal $acumulado, array $lote): BigDecimal => $acumulado->plus($lote['asignadas']), BigDecimal::zero());
        $porcentajeAsignado = $hectareasSolicitadas->isZero()
            ? 0
            : (int) round(((float) (string) $hectareasAsignadas / (float) (string) $hectareasSolicitadas) * 100);

        $resumenContrato = $this->resumenContrato($orden->contrato_id);
        $pasosEstado = PasosDeOrden::armar($orden->estado, $this->permisosDeEstado($request), 'detalle-'.$orden->id);

        return view('operaciones::pages.ordenes.show', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => PasosDeOrden::ayuda($pasosEstado, $orden->estado),
            'contratoLabel' => $this->etiquetasContrato([$orden->contrato_id])[$orden->contrato_id] ?? "#{$orden->contrato_id}",
            'contactoLabel' => $orden->emitida_por_contacto_id !== null
                ? DB::table('com_cliente_contactos')->where('id', $orden->emitida_por_contacto_id)->value('nombre')
                : null,
            'resumenContrato' => $resumenContrato,
            'aplicacionesPrevistas' => $resumenContrato['aplicaciones_previstas'] ?? null,
            'lotes' => $lotes,
            'hectareasSolicitadas' => $this->aHectareas($hectareasSolicitadas),
            'hectareasAsignadas' => $this->aHectareas($hectareasAsignadas),
            'porcentajeAsignado' => $porcentajeAsignado,
            'equiposAsignados' => $this->equiposAsignadosCount($orden),
            'actividad' => $this->actividadOrden($orden),
            'vinculos' => $this->vinculosOrden($orden, $request, $resumenContrato !== null),
            'inconvenientes' => $resumenOrdenes->ejecutar([$orden->id])[$orden->id],
            'puedeEditar' => $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
        ]);
    }

    /**
     * Resumen del contrato de ESTA orden, para el detalle (`show()`) — mismos
     * campos que la sección "Datos del contrato" de `create()`/`edit()`
     * (reforma 18/9/2026), pero para UN solo contrato: versión liviana de
     * `datosContratoParaFormulario()`, que arma TODOS los contratos para el
     * `<select>` buscable — acá no hace falta esa batería completa (ni los
     * lotes/contactos del picker), solo los datos intrínsecos del contrato.
     *
     * `null` si el contrato ya no existe (borrado lógicamente después de
     * emitida la orden) — la vista cae a no mostrar la sección.
     *
     * @return array{cliente: string, logo_url: ?string, propiedades: list<string>, aplicaciones_previstas: int, hectareas_contratadas: string, fecha_inicio: string, fecha_fin: ?string}|null
     */
    private function resumenContrato(int $contratoId): ?array
    {
        $contrato = DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->where('c.id', $contratoId)
            ->first(['c.aplicaciones_previstas', 'c.hectareas_contratadas', 'c.fecha_inicio', 'c.fecha_fin', 'cl.razon_social', 'cl.logo_path']);

        if ($contrato === null) {
            return null;
        }

        $propiedades = DB::table('com_contrato_lotes as ccl')
            ->join('com_lotes as l', 'l.id', '=', 'ccl.lote_id')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->where('ccl.contrato_id', $contratoId)
            ->whereNull('ccl.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNull('p.deleted_at')
            ->distinct()
            ->orderBy('p.nombre')
            ->pluck('p.nombre')
            ->all();

        return [
            'cliente' => $contrato->razon_social,
            'logo_url' => $this->logoUrl($contrato->logo_path),
            'propiedades' => $propiedades,
            'aplicaciones_previstas' => (int) $contrato->aplicaciones_previstas,
            'hectareas_contratadas' => (string) $contrato->hectareas_contratadas,
            'fecha_inicio' => CarbonImmutable::parse($contrato->fecha_inicio)->format('d/m/Y'),
            'fecha_fin' => $contrato->fecha_fin !== null ? CarbonImmutable::parse($contrato->fecha_fin)->format('d/m/Y') : null,
        ];
    }

    /**
     * "Vínculos" de `show()`: accesos a información relacionada que vive en
     * OTRA pantalla, no se duplica acá (mismo espíritu que el resumen
     * relacionado del arquetipo Formulario, §6.3.1, pero como filas sueltas
     * — `molecules/link-row` — en vez de tarjetas completas).
     *
     * Tres, a propósito, cada uno con destino REAL:
     * - Contrato (primero, tono de advertencia): la ficha del contrato de ESTA
     *   orden, `panel.contratos.edit`. Solo si el contrato todavía existe y el
     *   rol puede editar contratos — es la única pantalla de contrato que hay.
     *   Se arma con el nombre de la ruta, no con nada de Comercial (ADR 0003).
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
    private function vinculosOrden(OrdenAplicacion $orden, Request $request, bool $contratoExiste): array
    {
        $vinculos = [];

        if ($contratoExiste && $this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR_CONTRATO)) {
            $vinculos[] = [
                'href' => route('panel.contratos.edit', $orden->contrato_id),
                'icon' => 'description',
                'title' => __('operaciones.ordenes.vinculo_contrato'),
                'meta' => __('operaciones.ordenes.vinculo_contrato_meta', ['id' => $orden->contrato_id]),
                // Tono FIJO de advertencia (19/9/2026, pedido explícito del usuario).
                'tone' => 'warning',
            ];
        }

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
        $contratoId = (int) $datos['contrato_id'];

        try {
            $orden = $crearOrden->ejecutar($contratoId, $this->normalizarDatos($datos));
        } catch (ContratoNoAdmiteOrdenes|
                 ContratoConOrdenAbierta|
                 AplicacionesCompletas $excepcion) {
                     return redirect()
                         ->route('panel.ordenes.create')
                         ->withErrors(['contrato_id' => $excepcion->getMessage()]);
                 }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::store()).
        return redirect()
            ->route('panel.ordenes.edit', $orden)
            ->with('estado', __('operaciones.ordenes.creada'));
    }

    public function edit(Request $request, OrdenAplicacion $orden, ResumenDeOrdenes $resumenOrdenes): View|RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        // Una orden cerrada ya es historia: se ve en el detalle, no se edita.
        if (! PoliticaEdicionOrden::admiteEdicion($orden->estado)) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => OrdenNoEditable::porEstado($orden->estado->value)->getMessage()]);
        }

        $datosContrato = $this->datosContratoParaFormulario([$orden->contrato_id]);
        $pasosEstado = PasosDeOrden::armar($orden->estado, $this->permisosDeEstado($request), 'edicion-'.$orden->id);

        return view('operaciones::pages.ordenes.edit', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'contratosDisponibles' => collect($datosContrato)->map(fn (array $datos): string => $datos['label']),
            'datosContrato' => $datosContrato,
            'categoriasInsumoDisponibles' => $this->categoriasInsumoDisponibles(),
            'pasosEstado' => $pasosEstado,
            'ayudaEstado' => PasosDeOrden::ayuda($pasosEstado, $orden->estado),
            'resumenOrden' => $resumenOrdenes->ejecutar([$orden->id])[$orden->id],
            // Corregir una orden ya publicada pide motivo, y con trabajos el insumo no se toca
            // (`PoliticaEdicionOrden`).
            'exigeMotivo' => PoliticaEdicionOrden::exigeMotivo($orden->estado),
            'insumoBloqueado' => PoliticaEdicionOrden::bloqueaInsumo(Trabajo::query()->where('orden_id', $orden->id)->exists()),
            'relacionado' => $this->relacionadoDeEdicion($orden, $request),
        ]);
    }

    /**
     * Lo que el rol activo puede hacer con el estado de una orden — un permiso
     * por acción, para los pasos de {@see PasosDeOrden} (reanudar usa el de
     * pausar, como `reanudar()`).
     *
     * @return array{activar: bool, pausar: bool, cerrar: bool, cancelar: bool}
     */
    private function permisosDeEstado(Request $request): array
    {
        return [
            'activar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR),
            'pausar' => $this->autorizacion->tienePermiso($request, self::PERMISO_PAUSAR),
            'cerrar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR),
            'cancelar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CANCELAR),
        ];
    }

    /**
     * "Relacionado" del aside de `edit()`: accesos a lo que ya se armó — o falta
     * armar — con ESTA orden, en dos frentes, con el mismo criterio que la
     * sección de `show()`:
     * - Órdenes de trabajo: las que ya tiene (cada una lleva a su detalle) o, si
     *   no tiene ninguna, «Crear orden de trabajo».
     * - Equipos: «Ver equipos asignados» si ya hay alguno o, si no, «Asignar
     *   equipos» (`AsignacionEquiposController`, HU-70/HU-92).
     *
     * Solo se ofrece crear o asignar en una orden `vigente` (es la guarda de
     * `CrearOrdenTrabajo`), y cada acceso pide el permiso de SU pantalla de
     * destino (`operaciones.trabajo.ver`/`.crear`,
     * `operaciones.orden.asignar_equipos`), no `.ver`/`.editar` de la orden. Una
     * orden que no admite ni una cosa ni la otra y no tiene nada armado lo dice
     * en `aviso`, en vez de dejar el aside vacío.
     *
     * @return array{vinculos: list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>, aviso: ?array{titulo: string, detalle: string}}
     */
    private function relacionadoDeEdicion(OrdenAplicacion $orden, Request $request): array
    {
        $vigente = $orden->estado === EstadoOrdenAplicacion::Vigente;
        $tandas = OrdenTrabajo::query()->where('orden_id', $orden->id)->with('trabajos')->orderBy('id')->get();
        $equiposAsignados = $this->equiposAsignadosCount($orden);
        $vinculos = [];

        if ($tandas->isNotEmpty()) {
            if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER_TRABAJOS)) {
                foreach ($tandas as $tanda) {
                    $equipos = $tanda->trabajos->pluck('equipo_trabajo_id')->filter()->unique()->count();
                    $hectareas = $tanda->trabajos->reduce(
                        fn (BigDecimal $acumulado, Trabajo $trabajo): BigDecimal => $acumulado->plus((string) $trabajo->hectareas_declaradas),
                        BigDecimal::zero(),
                    );

                    $vinculos[] = [
                        'href' => route('panel.trabajos.show', $tanda),
                        'icon' => 'work_history',
                        'title' => __('operaciones.ordenes.vinculo_trabajo_item', ['id' => $tanda->id]),
                        'meta' => __('operaciones.ordenes.vinculo_trabajo_item_meta', [
                            'equipos' => trans_choice('operaciones.ordenes.equipos_cantidad', $equipos, ['cantidad' => $equipos]),
                            'hectareas' => $this->aHectareas($hectareas),
                        ]),
                        'tone' => 'info',
                    ];
                }
            }
        } elseif ($vigente && $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR_TRABAJOS)) {
            $vinculos[] = [
                'href' => route('panel.trabajos.create', ['orden_id' => $orden->id]),
                'icon' => 'add_task',
                'title' => __('operaciones.ordenes.vinculo_trabajos_crear'),
                'meta' => __('operaciones.ordenes.vinculo_trabajos_crear_meta'),
                'tone' => 'info',
            ];
        }

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_ASIGNAR_EQUIPOS) && ($equiposAsignados > 0 || $vigente)) {
            $hayEquipos = $equiposAsignados > 0;

            $vinculos[] = [
                // Memento de navegación: la ficha de asignación vuelve a esta edición.
                'href' => route('panel.asignacion-equipos.show', [
                    $orden,
                    'volver_a' => route('panel.ordenes.edit', $orden),
                    'volver_texto' => __('operaciones.ordenes.aside_volver_texto', ['nro' => $orden->nro_aplicacion]),
                ]),
                'icon' => $hayEquipos ? 'groups' : 'group_add',
                'title' => $hayEquipos ? __('operaciones.ordenes.vinculo_ver_equipos') : __('operaciones.ordenes.vinculo_asignar_equipos'),
                'meta' => $hayEquipos
                    ? trans_choice('operaciones.ordenes.equipos_cantidad', $equiposAsignados, ['cantidad' => $equiposAsignados])
                    : __('operaciones.ordenes.vinculo_asignar_equipos_meta'),
                'tone' => 'success',
            ];
        }

        $aviso = null;

        if (! $vigente && $tandas->isEmpty() && $equiposAsignados === 0) {
            $aviso = match ($orden->estado) {
                EstadoOrdenAplicacion::Emitida => [
                    'titulo' => __('operaciones.ordenes.aside_no_vigente_titulo'),
                    'detalle' => __('operaciones.ordenes.aside_no_vigente_emitida'),
                ],
                EstadoOrdenAplicacion::Pausada => [
                    'titulo' => __('operaciones.ordenes.aside_no_vigente_titulo'),
                    'detalle' => __('operaciones.ordenes.aside_no_vigente_pausada'),
                ],
                default => [
                    'titulo' => __('operaciones.ordenes.aside_cerrada_titulo'),
                    'detalle' => __('operaciones.ordenes.aside_cerrada_detalle'),
                ],
            };
        }

        return ['vinculos' => $vinculos, 'aviso' => $aviso];
    }

    private function aHectareas(BigDecimal $valor): string
    {
        return number_format((float) (string) $valor, 2, ',', '.');
    }

    /**
     * Detalle por lote de la orden: lo solicitado (`ope_orden_lotes`), lo ya
     * asignado a algún equipo (`ope_trabajos` de ese par orden↔lote), lo
     * restante y QUÉ equipo lo trabaja y con qué dron — mismo cálculo que ya
     * hace `AsignacionEquiposController::resumenPorLote()` para su propia
     * pantalla, reescrito acá porque `show()` necesita el detalle POR LOTE
     * (tabla "Lotes").
     *
     * Van en orden natural —por propiedad y, dentro de ella, por código: L1,
     * L2, … L10, no L1, L10, L2— y todos los trabajos de la orden se leen de
     * una vez, no una consulta por lote.
     *
     * `estado`: 'asignado' cuando no queda nada restante por repartir de ese
     * lote, 'pendiente' en cualquier otro caso (incluido un reparto parcial)
     * — mismo criterio binario que ya usa el badge de "Lotes" del mockup de
     * referencia, sin inventar un tercer estado "parcial" que ninguna otra
     * pantalla del sistema usa todavía.
     *
     * `equipos`: un renglón por equipo que trabaja el lote (puede haber más de
     * uno si se repartió) con el dron que ese equipo tenía asignado el día que
     * empezó el trabajo. Vacío mientras el lote no tenga orden de trabajo: la
     * vista pinta un guion. `dron` es `null` si el equipo no tenía ninguno.
     *
     * @return list<array{lote_id: int, label: string, hectareas_solicitadas: string, asignadas: string, restantes: string, estado: string, equipos: list<array{equipo: string, dron: ?string}>}>
     */
    private function detalleLotesOrden(OrdenAplicacion $orden, LecturaEquipoTrabajo $equipos): array
    {
        $ordenLotes = $orden->ordenLotes()->get();
        $datosLotes = $this->datosDeLotes($ordenLotes->pluck('lote_id')->map(fn ($id) => (int) $id)->all());

        $trabajosPorLote = Trabajo::query()
            ->where('orden_id', $orden->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'lote_id', 'equipo_trabajo_id', 'hectareas_declaradas', 'inicio'])
            ->groupBy('lote_id');

        $equiposPorLote = $this->equiposYDronesPorLote($trabajosPorLote, $equipos);

        return $ordenLotes
            ->sort(fn ($a, $b): int => $this->compararLotes($datosLotes[$a->lote_id] ?? null, $datosLotes[$b->lote_id] ?? null))
            ->map(function ($ordenLote) use ($datosLotes, $trabajosPorLote, $equiposPorLote): array {
                $solicitadas = BigDecimal::of((string) $ordenLote->hectareas_solicitadas);
                $asignadas = ($trabajosPorLote->get($ordenLote->lote_id) ?? collect())->reduce(
                    fn (BigDecimal $acumulado, Trabajo $trabajo): BigDecimal => $acumulado->plus((string) $trabajo->hectareas_declaradas),
                    BigDecimal::zero(),
                );
                $restantes = $solicitadas->minus($asignadas);

                return [
                    'lote_id' => $ordenLote->lote_id,
                    'label' => $datosLotes[$ordenLote->lote_id]['label'] ?? "#{$ordenLote->lote_id}",
                    'hectareas_solicitadas' => (string) $solicitadas,
                    'asignadas' => (string) $asignadas,
                    'restantes' => (string) $restantes,
                    'estado' => $restantes->isLessThanOrEqualTo(BigDecimal::zero()) ? 'asignado' : 'pendiente',
                    'equipos' => $equiposPorLote[$ordenLote->lote_id] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Equipo y dron de cada lote, a partir de los trabajos de la orden ya
     * agrupados por lote. Los equipos se leen por
     * `LecturaEquipoTrabajo::porIds()` (aunque ya no estén vigentes: un trabajo
     * asignado sigue nombrando a su equipo) y el dron es el que ese equipo tenía
     * asignado (`recursosAFecha()`, recurso de tipo `dron`) el día que empezó el
     * trabajo — una lectura por par equipo/fecha, no por trabajo. El
     * identificador legible del dron sale de `ope_drones`, tabla de este módulo.
     *
     * @param  iterable<int|string, iterable<int, Trabajo>>  $trabajosPorLote  lote_id => trabajos de la orden en ese lote.
     * @return array<int, list<array{equipo: string, dron: ?string}>> lote_id => un renglón por equipo.
     */
    private function equiposYDronesPorLote(iterable $trabajosPorLote, LecturaEquipoTrabajo $equipos): array
    {
        /** @var list<Trabajo> $asignados los trabajos que ya tienen equipo */
        $asignados = [];

        foreach ($trabajosPorLote as $trabajosDelLote) {
            foreach ($trabajosDelLote as $trabajo) {
                if ($trabajo->equipo_trabajo_id !== null) {
                    $asignados[] = $trabajo;
                }
            }
        }

        if ($asignados === []) {
            return [];
        }

        $fechaDe = fn (Trabajo $trabajo): string => ($trabajo->inicio ?? CarbonImmutable::now())->toDateString();

        $datosEquipo = $equipos->porIds(array_values(array_unique(array_map(
            fn (Trabajo $trabajo): int => (int) $trabajo->equipo_trabajo_id,
            $asignados,
        ))));

        /** @var array<string, list<int>> $dronesPorEquipoYFecha equipo|fecha => ids de dron */
        $dronesPorEquipoYFecha = [];

        foreach ($asignados as $trabajo) {
            $fecha = $fechaDe($trabajo);

            $dronesPorEquipoYFecha["{$trabajo->equipo_trabajo_id}|{$fecha}"] ??= collect($equipos->recursosAFecha((int) $trabajo->equipo_trabajo_id, $fecha))
                ->filter(fn (DatosRecursoEquipo $recurso): bool => $recurso->recursoTipo === 'dron')
                ->map(fn (DatosRecursoEquipo $recurso): int => $recurso->recursoId)
                ->values()
                ->all();
        }

        $identificadores = Dron::query()
            ->withTrashed()
            ->whereIn('id', array_unique(array_merge(...array_values($dronesPorEquipoYFecha))))
            ->pluck('identificador', 'id');

        $resultado = [];

        foreach ($trabajosPorLote as $loteId => $trabajosDelLote) {
            $renglones = [];

            foreach ($trabajosDelLote as $trabajo) {
                $equipoId = $trabajo->equipo_trabajo_id;

                if ($equipoId === null || isset($renglones[$equipoId])) {
                    continue;
                }

                $drones = collect($dronesPorEquipoYFecha["{$equipoId}|{$fechaDe($trabajo)}"] ?? [])
                    ->map(fn (int $dronId): string => $identificadores[$dronId] ?? "#{$dronId}")
                    ->implode(', ');

                $renglones[$equipoId] = [
                    'equipo' => isset($datosEquipo[$equipoId]) ? $datosEquipo[$equipoId]->codigo : "#{$equipoId}",
                    'dron' => $drones !== '' ? $drones : null,
                ];
            }

            $resultado[(int) $loteId] = array_values($renglones);
        }

        return $resultado;
    }

    /**
     * Orden natural de dos lotes: por propiedad y, dentro de ella, por código
     * (`strnatcasecmp`: L2 antes que L10). Un lote que ya no existe queda al
     * final.
     *
     * @param  array{propiedad: string, codigo: string, label: string}|null  $a
     * @param  array{propiedad: string, codigo: string, label: string}|null  $b
     */
    private function compararLotes(?array $a, ?array $b): int
    {
        return match (true) {
            $a === null && $b === null => 0,
            $a === null => 1,
            $b === null => -1,
            default => strnatcasecmp($a['propiedad'], $b['propiedad']) ?: strnatcasecmp($a['codigo'], $b['codigo']),
        };
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
     * columnas reales. Máximo 6 tipos:
     *
     * 1. Emitida — siempre, `created_at`/`created_by` de la orden.
     * 2. Activada — solo si el estado ya avanzó de `emitida`; usa
     *    `updated_at`/`updated_by` como proxy (una orden vigente ya no
     *    admite edición).
     * 3. Pausadas — uno por pausa registrada en `pausada_at`.
     * 4. Reanudadas — uno por reanudación en `reanudada_at`.
     * 5. Cerrada — solo si estado es `consumida`.
     * 6. Cancelada — solo si estado es `cancelada`.
     * 7. Equipo asignado — uno por equipo distinto entre los `Trabajo`.
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

        if ($orden->pausada_at !== null && $orden->motivo_pausa !== null) {
            $eventos[] = [
                'title' => __('operaciones.ordenes.actividad_pausada', ['motivo' => $orden->motivo_pausa]),
                'meta' => $this->metaActividad($orden->pausada_at, null),
                'tone' => 'warning',
            ];
        }

        if ($orden->reanudada_at !== null) {
            $eventos[] = [
                'title' => __('operaciones.ordenes.actividad_reanudada'),
                'meta' => $this->metaActividad($orden->reanudada_at, null),
                'tone' => 'success',
            ];
        }

        if ($orden->estado === EstadoOrdenAplicacion::Consumida && $orden->cerrada_at !== null) {
            $eventos[] = [
                'title' => __('operaciones.ordenes.actividad_cerrada'),
                'meta' => $this->metaActividad($orden->cerrada_at, null),
                'tone' => 'info',
            ];
        }

        if ($orden->estado === EstadoOrdenAplicacion::Cancelada && $orden->cancelada_at !== null) {
            $causa = ($orden->causa_cancelacion !== null ? $orden->causa_cancelacion->value : '—');
            $eventos[] = [
                'title' => __('operaciones.ordenes.actividad_cancelada', [
                    'causa' => __("operaciones.ordenes.causa_{$causa}"),
                    'motivo' => $orden->motivo_cancelacion ?? '—',
                ]),
                'meta' => $this->metaActividad($orden->cancelada_at, null),
                'tone' => 'danger',
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
            $actualizarOrden->ejecutar($orden, $this->normalizarDatos($datos), $datos['motivo_correccion'] ?? null);
        } catch (OrdenNoEditable $excepcion) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        } catch (CorreccionOrdenNoPermitida|MotivoRequerido $excepcion) {
            return redirect()
                ->route('panel.ordenes.edit', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()])
                ->withInput();
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

        // Vuelve al detalle, como pausar/reanudar/cerrar/cancelar: se activa desde
        // los pasos de la ficha de edición o del detalle, y la orden ya no es
        // editable, así que ahí no hay a dónde volver.
        try {
            $activarOrden->ejecutar($orden);
        } catch (TransicionOrdenNoPermitida $excepcion) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.show', $orden)
            ->with('estado', __('operaciones.ordenes.activada'));
    }

    public function pausar(Request $request, OrdenAplicacion $orden, PausarOrden $pausarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_PAUSAR), 403);

        $request->validate([
            'motivo_pausa' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $pausarOrden->ejecutar($orden, $request->string('motivo_pausa')->toString());
        } catch (TransicionOrdenNoPermitida $excepcion) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.show', $orden)
            ->with('estado', __('operaciones.ordenes.pausada'));
    }

    public function reanudar(Request $request, OrdenAplicacion $orden, ReanudarOrden $reanudarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_PAUSAR), 403);

        try {
            $reanudarOrden->ejecutar($orden);
        } catch (TransicionOrdenNoPermitida $excepcion) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.show', $orden)
            ->with('estado', __('operaciones.ordenes.reanudada'));
    }

    public function cerrar(Request $request, OrdenAplicacion $orden, CerrarOrden $cerrarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR), 403);

        try {
            $cerrarOrden->ejecutar($orden);
        } catch (TransicionOrdenNoPermitida|CierreOrdenNoPermitido $excepcion) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.show', $orden)
            ->with('estado', __('operaciones.ordenes.cerrada'));
    }

    public function cancelar(Request $request, OrdenAplicacion $orden, CancelarOrden $cancelarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CANCELAR), 403);

        $request->validate([
            'causa_cancelacion' => ['required', Rule::enum(CausaCancelacionOrden::class)],
            'motivo_cancelacion' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $causa = CausaCancelacionOrden::from($request->string('causa_cancelacion')->toString());
            $cancelarOrden->ejecutar($orden, $causa, $request->string('motivo_cancelacion')->toString());
        } catch (TransicionOrdenNoPermitida $excepcion) {
            return redirect()
                ->route('panel.ordenes.show', $orden)
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.show', $orden)
            ->with('estado', __('operaciones.ordenes.cancelada'));
    }

    public function destroy(Request $request, OrdenAplicacion $orden, EliminarOrden $eliminarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        try {
            $eliminarOrden->ejecutar($orden);
        } catch (OrdenNoEliminable $excepcion) {
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
        $tipoInsumo = DB::table('ope_categorias_insumo')->where('id', $categoriaInsumoId)->value('tipo_insumo');

        return [
            'cantidad_equipos_necesarios' => (int) $datos['cantidad_equipos_necesarios'],
            'tipo_aplicacion' => TipoAplicacion::from((string) $datos['tipo_aplicacion']),
            'categoria_insumo_id' => $categoriaInsumoId,
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
     *         'contactos' => list<array{id: int, nombre: string, tipo: string, label: string}>,  // com_cliente_contactos del cliente DUEÑO del contrato y SOLO de ese — el formulario nunca recibe los de otros clientes; `label` ya traducido (ADR 0013); la vista decide autoseleccionar si hay uno solo
     *         'lotes' => list<array{lote_id: int, codigo: string, propiedad: string, hectareas: string, desnivel: ?string, desnivel_label: ?string, limpieza: ?string, limpieza_label: ?string}>,  // en orden natural (propiedad y luego código: L1, L2, … L10); SOLO los lotes de `com_contrato_lotes` de ESTE contrato — desnivel/limpieza YA traducidos server-side (ADR 0013), mismo criterio que `ContratosController::propiedadesYLotesPorCliente()`
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
     * `$soloVigentes` (alta, ADR 0022): solo contratos `vigente`, los únicos que admiten
     * órdenes. `$soloContratoIds` (edición): restringe a esos contratos — en edición la
     * orden ya tiene el suyo, no hace falta serializar todos los del sistema.
     *
     * @param  list<int>|null  $soloContratoIds
     * @return array<int, array{label: string, cliente: string, logo_url: ?string, propiedades: list<string>, aplicaciones_previstas: int, hectareas_contratadas: string, fecha_inicio: string, fecha_fin: ?string, contactos: list<array{id: int, nombre: string, tipo: string, label: string}>, lotes: list<array{lote_id: int, codigo: string, propiedad: string, hectareas: string, desnivel: ?string, desnivel_label: ?string, limpieza: ?string, limpieza_label: ?string}>, nro_aplicacion_sugerido: int|null, contrato_edit_url: string}>
     */
    private function datosContratoParaFormulario(?array $soloContratoIds = null, bool $soloVigentes = false): array
    {
        $contratos = DB::table('com_contratos as c')
            ->join('com_clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->whereNull('c.deleted_at')
            ->whereNull('cl.deleted_at')
            ->when($soloVigentes, fn ($consulta) => $consulta->where('c.estado', 'vigente'))
            ->when($soloContratoIds !== null, fn ($consulta) => $consulta->whereIn('c.id', $soloContratoIds))
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
            ->get(['ccl.contrato_id', 'l.id as lote_id', 'l.codigo', 'l.hectareas', 'l.desnivel', 'l.limpieza', 'p.nombre as propiedad_nombre'])
            // Orden natural, por propiedad y luego por código (L1, L2, … L10):
            // el `ORDER BY` de la base es alfabético y dejaría L10 antes que L2.
            ->sort(fn (object $a, object $b): int => strnatcasecmp($a->propiedad_nombre, $b->propiedad_nombre) ?: strnatcasecmp($a->codigo, $b->codigo))
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
                        'label' => __('operaciones.ordenes.campo_contacto_opcion', [
                            'nombre' => $contacto->nombre,
                            'tipo' => __("comercial.clientes.contacto_tipo_opcion.{$contacto->tipo}"),
                        ]),
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
                // Botón "Editar contrato" del estado vacío "este contrato no
                // tiene lotes" (tarea "contrato-lotes-conflicto", 18/9/2026)
                // — `route()` con el nombre del módulo Comercial, no un
                // modelo Eloquent cruzando la frontera (ADR 0003 regla 2: una
                // URL nombrada no es un modelo ajeno).
                'contrato_edit_url' => route('panel.contratos.edit', $contratoId),
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
     * `aplicaciones_previstas` de cada contrato, para mostrar "N de M" en el
     * índice (ADR 0022). Lectura plana de una columna del contrato — mismo
     * criterio que `resumenContrato()`.
     *
     * @param  list<int>  $contratoIds
     * @return array<int, int> contrato_id => aplicaciones_previstas
     */
    private function previstasPorContrato(array $contratoIds): array
    {
        if ($contratoIds === []) {
            return [];
        }

        return DB::table('com_contratos')
            ->whereIn('id', $contratoIds)
            ->pluck('aplicaciones_previstas', 'id')
            ->map(fn (mixed $previstas): int => (int) $previstas)
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
     * Propiedad, código y etiqueta legible de cada lote pedido, para rotular y
     * ordenar la tabla "Lotes" del detalle — mismo criterio de lectura directa
     * que `etiquetasContrato()`.
     *
     * @param  list<int>  $ids
     * @return array<int, array{propiedad: string, codigo: string, label: string}>
     */
    private function datosDeLotes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('com_lotes as l')
            ->join('com_propiedades as p', 'p.id', '=', 'l.propiedad_id')
            ->whereIn('l.id', $ids)
            ->get(['l.id', 'p.nombre', 'l.codigo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => [
                    'propiedad' => (string) $fila->nombre,
                    'codigo' => (string) $fila->codigo,
                    'label' => __('operaciones.ordenes.campo_lote_opcion', [
                        'campo' => $fila->nombre,
                        'codigo' => $fila->codigo,
                    ]),
                ],
            ])
            ->all();
    }
}
