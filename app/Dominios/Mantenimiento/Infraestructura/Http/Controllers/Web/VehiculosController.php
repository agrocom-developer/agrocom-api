<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Contratos\LecturaCombustiblePorRecurso;
use App\Dominios\Mantenimiento\Aplicacion\ActualizarVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\ContarOrdenesDeEquipo;
use App\Dominios\Mantenimiento\Aplicacion\CrearVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\EliminarVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\ListarVehiculos;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\Excepciones\VehiculoDuplicado;
use App\Dominios\Mantenimiento\Dominio\TipoCombustibleVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\ActualizarVehiculoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearVehiculoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\ResumenRelacionadoDeEquipo;
use App\Dominios\Operaciones\Contratos\LecturaEstadiasPorVehiculo;
use App\Dominios\Personal\Contratos\LecturaCuadrillasPorRecurso;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST/PUT/DELETE /panel/vehiculos*` (HU-40, tarea 50): alta y
 * mantenimiento de la flota de vehículos, con su asignación a base y estado.
 * Mismo molde que `DronesController` (Operaciones), sin sub-entidad.
 *
 * Cuatro permisos de grano fino
 * (`mantenimiento.vehiculo.ver`/`.crear`/`.editar`/`.eliminar`), verificados
 * DENTRO del controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}
 * — mismo criterio que el resto del panel. Ninguna regla de negocio acá: los
 * casos de uso de `Aplicacion/` hacen el trabajo.
 *
 * El select de `base_id` se arma con `DB::table('per_bases')` (ADR 0003
 * regla 3, mismo criterio que los selects de `OrdenesController`), sin
 * importar el modelo Eloquent `PerBase` de `Personal` — `Vehiculo` y
 * `PerBase` son de módulos distintos.
 *
 * `combustible` (HU-84, tarea 99) se traduce a `TipoCombustibleVehiculo`
 * solo si viene informado — mismo criterio que `sentido` en
 * `StockController::registrarAjuste()` para un enum opcional. `tipo`
 * (HU-90, tarea 105) sigue el mismo criterio con `TipoVehiculo`.
 */
final class VehiculosController
{
    private const PERMISO_VER = 'mantenimiento.vehiculo.ver';

    private const PERMISO_CREAR = 'mantenimiento.vehiculo.crear';

    private const PERMISO_EDITAR = 'mantenimiento.vehiculo.editar';

    private const PERMISO_ELIMINAR = 'mantenimiento.vehiculo.eliminar';

    /**
     * Tono de cada estado, definido UNA vez (§6.3.4 de la guía de pantalla):
     * lo lee el badge del listado. Eje gris↔verde, y rojo para la baja
     * definitiva: `activo` es el estado sano; `taller` (en reparación) y
     * `pausa` (baja temporal de servicio, HU-84) no son un problema en sí. Los
     * tonos son los que la pantalla ya tenía —`pausa` toma el de las otras
     * bajas temporales—: no se reeligen acá.
     *
     * @var array<string, string>
     */
    public const array TONO_POR_ESTADO = [
        'activo' => 'success',
        'taller' => 'neutral',
        'pausa' => 'neutral',
        'de_baja' => 'danger',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarVehiculos $listarVehiculos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = $request->string('q')->toString();
        $baseQuery = $request->string('base_id')->toString();
        $baseId = $baseQuery !== '' ? (int) $baseQuery : null;
        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoVehiculo::tryFrom($estadoQuery) : null;

        $vehiculos = $listarVehiculos->ejecutar(
            busqueda: $busqueda !== '' ? $busqueda : null,
            baseId: $baseId,
            estado: $estado?->value,
        );

        return view('mantenimiento::pages.vehiculos.index', [
            ...$this->autorizacion->cascara($request),
            'vehiculos' => $vehiculos,
            'etiquetasBase' => $this->etiquetasBase($vehiculos->pluck('base_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'basesDisponibles' => $this->basesDisponibles(),
            'tonoPorEstado' => self::TONO_POR_ESTADO,
            'estadosFiltro' => EstadoVehiculo::cases(),
            'filtros' => ['q' => $busqueda, 'base_id' => $baseId, 'estado' => $estado?->value],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.vehiculos.create', [
            ...$this->autorizacion->cascara($request),
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoVehiculo::cases(),
            'combustibles' => TipoCombustibleVehiculo::cases(),
            'tipos' => TipoVehiculo::cases(),
        ]);
    }

    public function store(CrearVehiculoRequest $request, CrearVehiculo $crearVehiculo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        try {
            $vehiculo = $crearVehiculo->ejecutar(
                (string) $datos['identificador'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoVehiculo::from((string) $datos['estado']),
                $this->cadenaONull($datos['marca'] ?? null),
                $this->cadenaONull($datos['modelo'] ?? null),
                $this->enteroONull($datos['anio'] ?? null),
                isset($datos['combustible']) && $datos['combustible'] !== '' ? TipoCombustibleVehiculo::from((string) $datos['combustible']) : null,
                (bool) ($datos['es_4x4'] ?? false),
                $this->cadenaONull($datos['kilometraje_inicial'] ?? null),
                $this->cadenaONull($datos['kilometraje_actual'] ?? null),
                isset($datos['tipo']) && $datos['tipo'] !== '' ? TipoVehiculo::from((string) $datos['tipo']) : null,
            );
        } catch (VehiculoDuplicado $excepcion) {
            return redirect()
                ->route('panel.vehiculos.create')
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.vehiculos.edit', $vehiculo)
            ->with('estado', __('mantenimiento.vehiculos.creado'));
    }

    public function edit(
        Request $request,
        Vehiculo $vehiculo,
        ResumenRelacionadoDeEquipo $tarjetas,
        ContarOrdenesDeEquipo $contarOrdenes,
        LecturaEstadiasPorVehiculo $lecturaEstadias,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('mantenimiento::pages.vehiculos.edit', [
            ...$this->autorizacion->cascara($request),
            'vehiculo' => $vehiculo,
            'basesDisponibles' => $this->basesDisponibles(),
            'estados' => EstadoVehiculo::cases(),
            'combustibles' => TipoCombustibleVehiculo::cases(),
            'tipos' => TipoVehiculo::cases(),
            'resumenRelacionado' => $this->resumenRelacionado($vehiculo, $request, $tarjetas, $contarOrdenes, $lecturaEstadias),
        ]);
    }

    public function update(ActualizarVehiculoRequest $request, Vehiculo $vehiculo, ActualizarVehiculo $actualizarVehiculo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();

        try {
            $actualizarVehiculo->ejecutar(
                $vehiculo,
                (string) $datos['identificador'],
                $this->enteroONull($datos['base_id'] ?? null),
                EstadoVehiculo::from((string) $datos['estado']),
                $this->cadenaONull($datos['marca'] ?? null),
                $this->cadenaONull($datos['modelo'] ?? null),
                $this->enteroONull($datos['anio'] ?? null),
                isset($datos['combustible']) && $datos['combustible'] !== '' ? TipoCombustibleVehiculo::from((string) $datos['combustible']) : null,
                (bool) ($datos['es_4x4'] ?? false),
                $this->cadenaONull($datos['kilometraje_inicial'] ?? null),
                $this->cadenaONull($datos['kilometraje_actual'] ?? null),
                isset($datos['tipo']) && $datos['tipo'] !== '' ? TipoVehiculo::from((string) $datos['tipo']) : null,
            );
        } catch (VehiculoDuplicado $excepcion) {
            return redirect()
                ->route('panel.vehiculos.edit', $vehiculo)
                ->withInput()
                ->withErrors(['identificador' => $excepcion->getMessage()]);
        }

        // Se queda en la propia ficha de edición (no vuelve al listado, 16/9/2026 — mismo criterio que ClientesController::store()/update()).
        return redirect()
            ->route('panel.vehiculos.edit', $vehiculo)
            ->with('estado', __('mantenimiento.vehiculos.actualizado'));
    }

    public function destroy(Request $request, Vehiculo $vehiculo, EliminarVehiculo $eliminarVehiculo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR), 403);

        $eliminarVehiculo->ejecutar($vehiculo);

        return redirect()
            ->route('panel.vehiculos.index')
            ->with('estado', __('mantenimiento.vehiculos.eliminado'));
    }

    /**
     * Resumen relacionado del aside de `edit()` (solo edición, §6.3.1 de la
     * guía de pantalla): un vehículo recién creado no puede tener todavía
     * órdenes, cuadrillas, estadías ni combustible. Cuatro tarjetas, cada una
     * gateada por el permiso de LO QUE MUESTRA contra el ROL ACTIVO
     * (invariante 10), no por `mantenimiento.vehiculo.*`. Una categoría sin
     * `.ver` NI `.crear` se omite del todo; con `.crear` pero sin `.ver` se
     * ofrece el atajo sin revelar cifras.
     *
     * Las órdenes son del mismo módulo (`ContarOrdenesDeEquipo`); cuadrillas,
     * estadías y combustible llegan por el `Contratos/` de Personal, Operaciones
     * y Finanzas (ADR 0003, regla 2). Ninguno de los tres destinos de alta
     * acepta el vehículo precargado, así que los atajos llevan al formulario y
     * el vacío dice qué elegir.
     *
     * @return list<array{titulo: string, icono: string, tieneDatos: bool, items: list<array<string, mixed>>, vacioTitulo: string, vacioDetalle: string, acciones: list<array{label: string, href: string, icono?: string}>}>
     */
    private function resumenRelacionado(
        Vehiculo $vehiculo,
        Request $request,
        ResumenRelacionadoDeEquipo $tarjetas,
        ContarOrdenesDeEquipo $contarOrdenes,
        LecturaEstadiasPorVehiculo $lecturaEstadias,
    ): array {
        $resumen = [];

        // Memento de navegación: los atajos de alta apilan ESTA ficha como
        // origen, así el "Volver" de la pantalla de destino regresa acá y no
        // al listado de su propio módulo. Ver RecordarOrigenNavegacion.
        $origenNavegacion = ['volver_a' => route('panel.vehiculos.edit', $vehiculo), 'volver_texto' => $vehiculo->identificador];

        // 1) Órdenes de mantenimiento (mismo módulo). El listado de órdenes no
        // filtra por equipo, así que no hay «Ver órdenes»: llevaría a las de
        // todos los equipos.
        $puedeVerOrdenes = $this->autorizacion->tienePermiso($request, 'mantenimiento.orden.ver');
        $puedeAbrirOrden = $this->autorizacion->tienePermiso($request, 'mantenimiento.orden.crear');

        if ($puedeVerOrdenes || $puedeAbrirOrden) {
            $ordenes = $puedeVerOrdenes ? $contarOrdenes->ejecutar(ContarOrdenesDeEquipo::TIPO_VEHICULO, $vehiculo->id) : ['abiertas' => 0, 'total' => 0];

            $resumen[] = [
                'titulo' => __('mantenimiento.vehiculos.aside_ordenes_titulo'),
                'icono' => 'build',
                'tieneDatos' => $ordenes['total'] > 0,
                'items' => [
                    [
                        'label' => __('mantenimiento.vehiculos.aside_ordenes_abiertas'),
                        'value' => (string) $ordenes['abiertas'],
                        'mono' => true,
                        'variant' => $ordenes['abiertas'] > 0 ? 'warning' : 'neutral',
                    ],
                    ['label' => __('mantenimiento.vehiculos.aside_ordenes_total'), 'value' => (string) $ordenes['total'], 'mono' => true],
                ],
                'vacioTitulo' => __('mantenimiento.vehiculos.aside_ordenes_vacio_titulo'),
                'vacioDetalle' => __('mantenimiento.vehiculos.aside_ordenes_vacio_detalle'),
                'acciones' => $puedeAbrirOrden ? [[
                    'label' => __('mantenimiento.vehiculos.aside_ordenes_accion_abrir'),
                    'href' => route('panel.ordenes-mantenimiento.create', $origenNavegacion),
                    'icono' => 'add',
                ]] : [],
            ];
        }

        // 2) Cuadrillas que lo tienen asignado (Personal, por contrato).
        $cuadrillas = $tarjetas->cuadrillas(
            $request,
            LecturaCuadrillasPorRecurso::TIPO_VEHICULO,
            $vehiculo->id,
            __('mantenimiento.vehiculos.aside_cuadrillas_vacio_detalle'),
        );

        if ($cuadrillas !== null) {
            $resumen[] = $cuadrillas;
        }

        // 3) Estadías en hacienda donde se usó (Operaciones, por contrato).
        $puedeVerEstadias = $this->autorizacion->tienePermiso($request, 'operaciones.estadia.ver');
        $puedeRegistrarEstadia = $this->autorizacion->tienePermiso($request, 'operaciones.estadia.crear');

        if ($puedeVerEstadias || $puedeRegistrarEstadia) {
            $estadias = $puedeVerEstadias ? $lecturaEstadias->deVehiculo($vehiculo->id) : null;
            $totalEstadias = $estadias->total ?? 0;
            $enCurso = $estadias->enCurso ?? 0;

            $acciones = [];

            if ($totalEstadias > 0) {
                $acciones[] = ['label' => __('mantenimiento.vehiculos.aside_estadias_accion_ver'), 'href' => route('panel.estadias.index'), 'icono' => 'list'];
            }

            if ($puedeRegistrarEstadia) {
                $acciones[] = [
                    'label' => __('mantenimiento.vehiculos.aside_estadias_accion_registrar'),
                    'href' => route('panel.estadias.create', $origenNavegacion),
                    'icono' => 'add',
                ];
            }

            $resumen[] = [
                'titulo' => __('mantenimiento.vehiculos.aside_estadias_titulo'),
                'icono' => 'holiday_village',
                'tieneDatos' => $totalEstadias > 0,
                'items' => [
                    [
                        'label' => __('mantenimiento.vehiculos.aside_estadias_en_curso'),
                        'value' => (string) $enCurso,
                        'mono' => true,
                        'variant' => $enCurso > 0 ? 'success' : 'neutral',
                    ],
                    ['label' => __('mantenimiento.vehiculos.aside_estadias_total'), 'value' => (string) $totalEstadias, 'mono' => true],
                ],
                'vacioTitulo' => __('mantenimiento.vehiculos.aside_estadias_vacio_titulo'),
                'vacioDetalle' => __('mantenimiento.vehiculos.aside_estadias_vacio_detalle'),
                'acciones' => $acciones,
            ];
        }

        // 4) Combustible cargado (Finanzas, por contrato).
        $combustible = $tarjetas->combustible(
            $request,
            LecturaCombustiblePorRecurso::TIPO_VEHICULO,
            $vehiculo->id,
            $origenNavegacion,
            __('mantenimiento.vehiculos.aside_combustible_vacio_detalle'),
        );

        if ($combustible !== null) {
            $resumen[] = $combustible;
        }

        return $resumen;
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function cadenaONull(mixed $valor): ?string
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
     * borrada lógicamente después de asignada a un vehículo queda fuera del
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
