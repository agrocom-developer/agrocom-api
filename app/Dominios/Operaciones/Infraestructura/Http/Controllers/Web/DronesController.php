<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Mantenimiento\Contratos\LecturaMantenimientoPorDron;
use App\Dominios\Operaciones\Aplicacion\ActualizarDron;
use App\Dominios\Operaciones\Aplicacion\CrearDron;
use App\Dominios\Operaciones\Aplicacion\EliminarDron;
use App\Dominios\Operaciones\Aplicacion\ListarDrones;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\Excepciones\DronDuplicado;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarDronRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearDronRequest;
use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/drones*` (HU-27, tarea 36): alta y
 * mantenimiento de la flota de drones con su modelo y capacidad de carga.
 * Mismo molde que `ClientesController`/`CamposController` (HU-22/HU-24),
 * pero sin sub-entidad: un dron no tiene contactos ni lotes.
 *
 * Cuatro permisos de grano fino
 * (`operaciones.dron.ver`/`.crear`/`.editar`/`.eliminar`), verificados DENTRO
 * del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — mismo
 * criterio que el resto del panel. Ninguna regla de negocio acá: los casos de
 * uso de `Aplicacion/` hacen el trabajo.
 */
final class DronesController
{
    private const PERMISO_VER = 'operaciones.dron.ver';

    private const PERMISO_CREAR = 'operaciones.dron.crear';

    private const PERMISO_EDITAR = 'operaciones.dron.editar';

    private const PERMISO_ELIMINAR = 'operaciones.dron.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarDrones $listarDrones): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();

        return view('operaciones::pages.drones.index', [
            ...$this->autorizacion->cascara($request),
            'drones' => $listarDrones->ejecutar($busqueda !== '' ? $busqueda : null),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('operaciones::pages.drones.create', [
            ...$this->autorizacion->cascara($request),
        ]);
    }

    public function store(CrearDronRequest $request, CrearDron $crearDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $dron = $crearDron->ejecutar(
                (string) $datos['identificador'],
                $this->cadenaONull($datos['modelo'] ?? null),
                $this->cadenaONull($datos['capacidad_l'] ?? null),
                $this->cadenaONull($datos['capacidad_kg'] ?? null),
            );
        } catch (DronDuplicado $excepcion) {
            return redirect()
                ->route('panel.drones.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::store()).
        return redirect()
            ->route('panel.drones.edit', $dron)
            ->with('estado', __('operaciones.drones.creado'));
    }

    public function edit(
        Request $request,
        Dron $dron,
        LecturaMantenimientoPorDron $lecturaMantenimiento,
        LecturaCuadrillasPorRecurso $lecturaCuadrillas,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('operaciones::pages.drones.edit', [
            ...$this->autorizacion->cascara($request),
            'dron' => $dron,
            'resumenRelacionado' => $this->resumenRelacionado($dron, $request, $lecturaMantenimiento, $lecturaCuadrillas),
        ]);
    }

    public function update(ActualizarDronRequest $request, Dron $dron, ActualizarDron $actualizarDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarDron->ejecutar(
                $dron,
                (string) $datos['identificador'],
                $this->cadenaONull($datos['modelo'] ?? null),
                $this->cadenaONull($datos['capacidad_l'] ?? null),
                $this->cadenaONull($datos['capacidad_kg'] ?? null),
            );
        } catch (DronDuplicado $excepcion) {
            return redirect()
                ->route('panel.drones.edit', $dron)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado,
        // 16/9/2026 — mismo criterio que ClientesController::update()).
        return redirect()
            ->route('panel.drones.edit', $dron)
            ->with('estado', __('operaciones.drones.actualizado'));
    }

    public function destroy(Request $request, Dron $dron, EliminarDron $eliminarDron): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarDron->ejecutar($dron);

        return redirect()
            ->route('panel.drones.index')
            ->with('estado', __('operaciones.drones.eliminado'));
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): un dron recién creado no puede tener todavía ficha de
     * inventario, órdenes de mantenimiento, cuadrillas ni sesiones. Cuatro
     * tarjetas, cada una gateada por el permiso de LO QUE MUESTRA contra el ROL
     * ACTIVO (invariante 10), no por `operaciones.dron.*`: ver las órdenes de
     * mantenimiento de un dron es ver órdenes de mantenimiento. Una categoría
     * sin `.ver` NI `.crear` se omite del todo; con `.crear` pero sin `.ver` se
     * ofrece el atajo sin revelar cifras. Cuadrillas y sesiones no tienen atajo
     * de alta: el dron se asigna a una cuadrilla desde la ficha de la
     * cuadrilla, y las sesiones llegan por la app de campo.
     *
     * Las sesiones son del mismo módulo (Eloquent directo). Ficha, órdenes y
     * cuadrillas son de Mantenimiento y Personal: llegan por sus contratos de
     * lectura (ADR 0003, regla 2), nunca por sus tablas. No hay tarjeta de
     * baterías: `man_baterias` no guarda a qué dron pertenece.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        Dron $dron,
        Request $request,
        LecturaMantenimientoPorDron $lecturaMantenimiento,
        LecturaCuadrillasPorRecurso $lecturaCuadrillas,
    ): array {
        $resumen = [];

        // Memento de navegación: los atajos de alta apilan ESTA ficha como
        // origen, así el "Volver" de la pantalla de destino regresa acá y no
        // al listado de su propio módulo. Ver RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.drones.edit', $dron), 'volver_texto' => $dron->identificador];

        $puedeVerFicha = $this->autorizacion->tienePermiso($request, 'mantenimiento.ficha_dron.ver');
        $puedeCrearFicha = $this->autorizacion->tienePermiso($request, 'mantenimiento.ficha_dron.crear');
        $puedeVerOrdenes = $this->autorizacion->tienePermiso($request, 'mantenimiento.orden.ver');
        $puedeAbrirOrden = $this->autorizacion->tienePermiso($request, 'mantenimiento.orden.crear');

        // Un solo viaje a Mantenimiento para las dos primeras tarjetas.
        $mantenimiento = $puedeVerFicha || $puedeVerOrdenes
            ? $lecturaMantenimiento->deDron($dron->id, $dron->identificador)
            : null;

        // 1) Ficha de inventario (Mantenimiento, por contrato). El alta de la ficha
        // recibe el identificador de este dron y lo precarga.
        if ($puedeVerFicha || $puedeCrearFicha) {
            $fichaId = $puedeVerFicha ? $mantenimiento?->fichaId : null;
            $acciones = [];

            if ($fichaId !== null && $this->autorizacion->tienePermiso($request, 'mantenimiento.ficha_dron.editar')) {
                $acciones[] = [
                    'label' => __('operaciones.drones.aside_ficha_accion_ver'),
                    'href' => route('panel.fichas-dron.edit', $fichaId),
                    'icono' => 'arrow_forward',
                ];
            }

            if ($fichaId === null && $puedeCrearFicha) {
                $acciones[] = [
                    'label' => __('operaciones.drones.aside_ficha_accion_crear'),
                    'href' => route('panel.fichas-dron.create', [...$origenNavegacion, 'identificador_dron' => $dron->identificador]),
                    'icono' => 'add',
                ];
            }

            $sinDato = __('operaciones.drones.aside_ficha_sin_dato');

            $resumen[] = [
                'titulo' => __('operaciones.drones.aside_ficha_titulo'),
                'icono' => 'inventory_2',
                'tieneDatos' => $fichaId !== null,
                'items' => [
                    ['label' => __('operaciones.drones.aside_ficha_serie'), 'value' => $mantenimiento->numeroSerie ?? $sinDato, 'mono' => true],
                    ['label' => __('operaciones.drones.aside_ficha_software'), 'value' => $mantenimiento->versionSoftware ?? $sinDato, 'mono' => true],
                ],
                'vacioTitulo' => __('operaciones.drones.aside_ficha_vacio_titulo'),
                'vacioDetalle' => __('operaciones.drones.aside_ficha_vacio_detalle', ['identificador' => $dron->identificador]),
                'acciones' => $acciones,
            ];
        }

        // 2) Órdenes de mantenimiento (Mantenimiento, por contrato). El listado
        // de órdenes no filtra por equipo, así que no hay «Ver órdenes» acá:
        // llevaría a las de todos los drones.
        if ($puedeVerOrdenes || $puedeAbrirOrden) {
            $abiertas = $puedeVerOrdenes ? ($mantenimiento->ordenesAbiertas ?? 0) : 0;
            $total = $puedeVerOrdenes ? ($mantenimiento->ordenesTotal ?? 0) : 0;

            $resumen[] = [
                'titulo' => __('operaciones.drones.aside_ordenes_titulo'),
                'icono' => 'build',
                'tieneDatos' => $total > 0,
                'items' => [
                    [
                        'label' => __('operaciones.drones.aside_ordenes_abiertas'),
                        'value' => (string) $abiertas,
                        'mono' => true,
                        'variant' => $abiertas > 0 ? 'warning' : 'neutral',
                    ],
                    ['label' => __('operaciones.drones.aside_ordenes_total'), 'value' => (string) $total, 'mono' => true],
                ],
                'vacioTitulo' => __('operaciones.drones.aside_ordenes_vacio_titulo'),
                'vacioDetalle' => __('operaciones.drones.aside_ordenes_vacio_detalle'),
                'acciones' => $puedeAbrirOrden ? [[
                    'label' => __('operaciones.drones.aside_ordenes_accion_abrir'),
                    'href' => route('panel.ordenes-mantenimiento.create', $origenNavegacion),
                    'icono' => 'add',
                ]] : [],
            ];
        }

        // 3) Cuadrillas que lo tienen asignado (Personal, por contrato).
        // «Vigente» es lo mismo que en el listado de cuadrillas.
        if ($this->autorizacion->tienePermiso($request, 'personal.equipo_trabajo.ver')) {
            $cuadrillas = $lecturaCuadrillas->deRecurso(LecturaCuadrillasPorRecurso::TIPO_DRON, $dron->id, now()->toDateString());

            $items = [
                [
                    'label' => __('operaciones.drones.aside_cuadrillas_vigentes'),
                    'value' => (string) $cuadrillas->vigentes,
                    'mono' => true,
                    'variant' => $cuadrillas->vigentes > 0 ? 'success' : 'neutral',
                ],
                ['label' => __('operaciones.drones.aside_cuadrillas_historial'), 'value' => (string) $cuadrillas->historial, 'mono' => true],
            ];

            if ($cuadrillas->codigosVigentes !== []) {
                $items[] = [
                    'label' => __('operaciones.drones.aside_cuadrillas_actual'),
                    'value' => implode(', ', $cuadrillas->codigosVigentes),
                    'mono' => true,
                ];
            }

            $resumen[] = [
                'titulo' => __('operaciones.drones.aside_cuadrillas_titulo'),
                'icono' => 'groups',
                'tieneDatos' => $cuadrillas->historial > 0,
                'items' => $items,
                'vacioTitulo' => __('operaciones.drones.aside_cuadrillas_vacio_titulo'),
                'vacioDetalle' => __('operaciones.drones.aside_cuadrillas_vacio_detalle'),
                'acciones' => $cuadrillas->historial > 0 ? [[
                    'label' => __('operaciones.drones.aside_cuadrillas_accion_ver'),
                    'href' => route('panel.cuadrillas.index'),
                    'icono' => 'list',
                ]] : [],
            ];
        }

        // 4) Sesiones de vuelo (mismo módulo). Los permisos de
        // `operaciones.trabajo.*` cubren «trabajos y sesiones».
        if ($this->autorizacion->tienePermiso($request, 'operaciones.trabajo.ver')) {
            $sesiones = Sesion::query()->where('dron_id', $dron->id);
            $totalSesiones = (clone $sesiones)->count();
            $validadas = (clone $sesiones)->where('estado', EstadoSesion::Validado)->count();

            $resumen[] = [
                'titulo' => __('operaciones.drones.aside_sesiones_titulo'),
                'icono' => 'flight',
                'tieneDatos' => $totalSesiones > 0,
                'items' => [
                    ['label' => __('operaciones.drones.aside_sesiones_total'), 'value' => (string) $totalSesiones, 'mono' => true],
                    [
                        'label' => __('operaciones.drones.aside_sesiones_validadas'),
                        'value' => (string) $validadas,
                        'mono' => true,
                        'variant' => $validadas > 0 ? 'success' : 'neutral',
                    ],
                ],
                'vacioTitulo' => __('operaciones.drones.aside_sesiones_vacio_titulo'),
                'vacioDetalle' => __('operaciones.drones.aside_sesiones_vacio_detalle'),
                'acciones' => [],
            ];
        }

        return $resumen;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
