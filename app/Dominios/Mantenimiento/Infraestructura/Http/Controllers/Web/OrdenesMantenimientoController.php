<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Inventario\Contratos\DatosConsumoOrden;
use App\Dominios\Inventario\Contratos\LecturaConsumosPorOrden;
use App\Dominios\Mantenimiento\Aplicacion\ListarOrdenesMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\MaquinaEstados\MaquinaEstadosOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\Excepciones\RepuestosInsuficientes;
use App\Dominios\Mantenimiento\Dominio\Excepciones\TransicionOrdenMantenimientoNoPermitida;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Http\PasosDeOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CerrarOrdenMantenimientoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearOrdenMantenimientoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\ResumenRelacionadoDeOrden;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * `GET/POST /panel/ordenes-mantenimiento*` (HU-37, tarea 53): apertura y
 * cierre de órdenes de mantenimiento, con la máquina de estados que consume
 * repuestos e imputa el gasto. Sin `update`/`destroy` de negocio libre: una
 * orden abierta NO tiene edición de sus datos descriptivos en este alcance
 * (decisión de esta tarea) — `edit()` es la pantalla de detalle desde la que
 * se dispara el cierre, no un formulario de edición; el único cambio de
 * `estado` posible pasa por `cerrar()`.
 *
 * Tres permisos de grano fino
 * (`mantenimiento.orden.ver`/`.crear`/`.cerrar`), verificados DENTRO del
 * controlador contra el ROL ACTIVO vía {@see AutorizacionPanelWeb} — mismo
 * criterio que el resto del panel. Ninguna regla de negocio acá: la máquina
 * de estados y los contratos de `Inventario`/`Finanzas` hacen el trabajo.
 *
 * `RepuestosInsuficientes`/`TransicionOrdenMantenimientoNoPermitida` se
 * traducen a {@see ValidationException}: Laravel la resuelve sola como
 * redirect + errores de sesión para un POST de formulario normal, o como
 * 422 JSON si el request espera JSON (`Accept: application/json`) — mismo
 * mecanismo que usa Laravel para un `FormRequest` fallido, sin reinventarlo
 * acá para una excepción de dominio.
 *
 * Los selects de equipo/repuestos/bases se arman con `DB::table(...)` (ADR
 * 0003 regla 3, mismo criterio que `VehiculosController`/`StockController`),
 * sin importar los modelos Eloquent de `Operaciones`/`Inventario`/`Personal`.
 *
 * `index()` suma en la tarea 116 la búsqueda libre y las cifras de la franja
 * de KPI: las dos salen del mismo caso de uso y con el mismo filtro que la
 * tabla, así lo que se ve arriba cuenta las filas que se ven abajo.
 *
 * `edit()` arma además lo que la ficha necesita para pintarse (tarea 116):
 * los pasos de la máquina de estados ({@see PasosDeOrdenMantenimiento}), los
 * repuestos que el cierre consumió —por el contrato de lectura de Inventario,
 * {@see LecturaConsumosPorOrden}, nunca `MovimientoStock::query()` directo— y
 * el resumen relacionado del aside ({@see ResumenRelacionadoDeOrden}). El
 * precio final real de la orden cerrada (HU-88, tarea 103) vive ahora en ese
 * resumen, siempre por el contrato de lectura de Finanzas.
 */
final class OrdenesMantenimientoController
{
    private const PERMISO_VER = 'mantenimiento.orden.ver';

    private const PERMISO_CREAR = 'mantenimiento.orden.crear';

    private const PERMISO_CERRAR = 'mantenimiento.orden.cerrar';

    /** Nombre de página de la tabla de repuestos consumidos de la ficha. */
    private const PAGINA_REPUESTOS = 'repuestos';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaConsumosPorOrden $lecturaConsumos,
        private readonly ResumenRelacionadoDeOrden $resumenRelacionadoDeOrden,
    ) {}

    public function index(Request $request, ListarOrdenesMantenimiento $listarOrdenesMantenimiento): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estadoQuery = TextoDeFiltro::de($request, 'estado');
        $estado = $estadoQuery !== '' ? EstadoOrdenMantenimiento::tryFrom($estadoQuery) : null;
        $equipoTipoQuery = TextoDeFiltro::de($request, 'equipo_tipo');
        $equipoTipo = in_array($equipoTipoQuery, ['dron', 'vehiculo'], true) ? $equipoTipoQuery : null;
        $busqueda = trim(TextoDeFiltro::de($request, 'q'));
        $equipoIdsCoincidentes = $busqueda === '' ? [] : $this->equipoIdsCoincidentes($busqueda);

        $ordenes = $listarOrdenesMantenimiento->ejecutar(
            estado: $estado?->value,
            equipoTipo: $equipoTipo,
            q: $busqueda,
            equipoIdsCoincidentes: $equipoIdsCoincidentes,
        );

        return view('mantenimiento::pages.ordenes.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'etiquetasEquipo' => $this->etiquetasEquipo($ordenes->getCollection()),
            'resumen' => $listarOrdenesMantenimiento->resumen(
                estado: $estado?->value,
                equipoTipo: $equipoTipo,
                q: $busqueda,
                equipoIdsCoincidentes: $equipoIdsCoincidentes,
            ),
            'filtros' => ['estado' => $estado?->value, 'equipo_tipo' => $equipoTipo, 'q' => $busqueda],
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedeCerrar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('mantenimiento::pages.ordenes.create', [
            ...$this->autorizacion->cascara($request),
            'dronesDisponibles' => $this->dronesDisponibles(),
            'vehiculosDisponibles' => $this->vehiculosDisponibles(),
        ]);
    }

    public function store(CrearOrdenMantenimientoRequest $request, MaquinaEstadosOrdenMantenimiento $maquinaEstados): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $datos = $request->validated();

        $orden = $maquinaEstados->abrir([
            'equipo_tipo' => $datos['equipo_tipo'],
            'equipo_id' => (int) $datos['equipo_id'],
            'tipo' => $datos['tipo'],
            'descripcion' => $datos['descripcion'],
        ]);

        return redirect()
            ->route('panel.ordenes-mantenimiento.edit', $orden)
            ->with('estado', __('mantenimiento.ordenes.creada'));
    }

    public function edit(Request $request, OrdenMantenimiento $orden): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $puedeCerrar = $this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR);
        $pasos = PasosDeOrdenMantenimiento::armar($orden->estado, $puedeCerrar);
        $estaCerrada = $orden->estado === EstadoOrdenMantenimiento::Cerrada;
        $bases = $this->basesDisponibles();

        return view('mantenimiento::pages.ordenes.edit', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'etiquetaEquipo' => $this->etiquetaEquipo($orden->equipo_tipo, $orden->equipo_id),
            'repuestosDisponibles' => $this->repuestosDisponibles(),
            'basesDisponibles' => $bases,
            'nombresBase' => $bases->all(),
            'stockPorRepuesto' => $this->stockPorRepuesto(),
            // Una orden abierta todavía no consumió nada: lo que se ve es el
            // selector del cierre, no una lista de consumos.
            'consumos' => $estaCerrada ? $this->consumosPaginados($request, $orden) : null,
            'puedeCerrar' => $puedeCerrar,
            'pasosEstado' => $pasos,
            'ayudaEstado' => PasosDeOrdenMantenimiento::ayuda($pasos),
            // Plan §3.5: mientras la orden no llegó al estado donde hay algo
            // que resumir, el aside muestra una sola sección informativa en
            // lugar de tarjetas vacías — ver `_formulario.blade.php`.
            'resumenRelacionado' => $estaCerrada ? $this->resumenRelacionadoDeOrden->tarjetas($request, $orden) : null,
        ]);
    }

    public function cerrar(CerrarOrdenMantenimientoRequest $request, OrdenMantenimiento $orden, MaquinaEstadosOrdenMantenimiento $maquinaEstados): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR), 403);

        /** @var list<array{repuesto_id: mixed, base_id: mixed, cantidad: mixed}> $repuestos */
        $repuestos = $request->validated('repuestos');

        // El formulario nuevo (HU-57, tarea 80) manda `repuestos` keyed por
        // repuesto_id, no 0..n-1 (así el selector por casillas no necesita
        // reindexar nada en el cliente) — se reindexa acá antes de tocar la
        // máquina de estados, que sigue esperando `list<array{...}>`.
        $lineas = collect($repuestos)
            ->map(fn (array $linea): array => [
                'repuesto_id' => (int) $linea['repuesto_id'],
                'base_id' => (int) $linea['base_id'],
                'cantidad' => (string) $linea['cantidad'],
            ])
            ->values()
            ->all();

        try {
            $maquinaEstados->cerrar($orden, $lineas, (string) $request->validated('descripcion_final'));
        } catch (RepuestosInsuficientes|TransicionOrdenMantenimientoNoPermitida $excepcion) {
            throw ValidationException::withMessages(['repuestos' => $excepcion->getMessage()]);
        }

        // Vuelve a la ficha, no al listado (§6.3.4 de la guía de pantalla): el
        // cierre se pide desde los pasos de la propia ficha, y ahí queda lo
        // que el cierre acaba de producir — los repuestos consumidos y el
        // gasto del resumen relacionado.
        return redirect()
            ->route('panel.ordenes-mantenimiento.edit', $orden)
            ->with('estado', __('mantenimiento.ordenes.cerrada'));
    }

    /**
     * Los repuestos que el cierre descontó, paginados para la tabla de
     * detalle de la ficha. Llegan como lista por el contrato de Inventario
     * (una orden consume pocas líneas, no hace falta paginar en la base), y
     * el paginador se arma acá con su propio nombre de página para no chocar
     * con ningún otro listado de la pantalla.
     *
     * @return LengthAwarePaginator<int, DatosConsumoOrden>
     */
    private function consumosPaginados(Request $request, OrdenMantenimiento $orden): LengthAwarePaginator
    {
        $lineas = collect($this->lecturaConsumos->deOrden($orden->id));
        $porPagina = 10;
        $pagina = LengthAwarePaginator::resolveCurrentPage(self::PAGINA_REPUESTOS);

        return new LengthAwarePaginator(
            $lineas->forPage($pagina, $porPagina)->values(),
            $lineas->count(),
            $porPagina,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => self::PAGINA_REPUESTOS],
        );
    }

    /** @return Collection<int, string> */
    private function dronesDisponibles(): Collection
    {
        return DB::table('ope_drones')
            ->whereNull('deleted_at')
            ->orderBy('identificador')
            ->pluck('identificador', 'id');
    }

    /** @return Collection<int, string> */
    private function vehiculosDisponibles(): Collection
    {
        return DB::table('man_vehiculos')
            ->whereNull('deleted_at')
            ->orderBy('identificador')
            ->pluck('identificador', 'id');
    }

    /** @return Collection<int, string> */
    private function repuestosDisponibles(): Collection
    {
        return DB::table('inv_repuestos')
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion'])
            ->mapWithKeys(fn (object $repuesto): array => [$repuesto->id => $this->etiquetaRepuesto($repuesto)]);
    }

    /**
     * Método aparte (en vez de interpolar en línea dentro del
     * `mapWithKeys` de arriba) para que Larastan tipe el resultado como
     * `string` liso — mismo motivo que `StockController::etiquetaRepuesto`.
     */
    private function etiquetaRepuesto(object $repuesto): string
    {
        return "{$repuesto->codigo} — {$repuesto->descripcion}";
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
     * Disponibilidad por repuesto y base, solo para pintarla en el selector
     * por casillas del cierre (HU-57, tarea 80) — presentación, no la guarda
     * real: esa sigue viviendo en `EscrituraConsumoStock` dentro de la
     * transacción de `MaquinaEstadosOrdenMantenimiento::cerrar()`. Lectura
     * directa de `inv_stock` (ADR 0003 regla 3, mismo criterio que
     * `repuestosDisponibles()`/`basesDisponibles()` arriba).
     *
     * @return array<int, array<int, string>> repuesto_id => [base_id => cantidad]
     */
    private function stockPorRepuesto(): array
    {
        return DB::table('inv_stock')
            ->select('repuesto_id', 'base_id', 'cantidad')
            ->get()
            ->groupBy('repuesto_id')
            ->map(fn (Collection $filas): array => $filas->pluck('cantidad', 'base_id')->map(fn ($cantidad): string => (string) $cantidad)->all())
            ->all();
    }

    private function etiquetaEquipo(string $equipoTipo, int $equipoId): string
    {
        $tabla = $equipoTipo === 'dron' ? 'ope_drones' : 'man_vehiculos';

        $identificador = DB::table($tabla)->where('id', $equipoId)->value('identificador');

        return $identificador !== null ? (string) $identificador : "#{$equipoId}";
    }

    /**
     * Ids de equipo cuyo identificador coincide con la búsqueda libre del
     * listado, por tipo. El identificador del dron vive en `ope_drones` y el
     * del vehículo en `man_vehiculos`: la orden solo guarda el id, así que
     * la coincidencia se resuelve acá y el caso de uso recibe ids, nunca un
     * join a una tabla de otro prefijo (ADR 0003 regla 3, mismo criterio que
     * `etiquetasEquipo()`).
     *
     * @return array<string, list<int>> claves `dron`/`vehiculo`.
     */
    private function equipoIdsCoincidentes(string $busqueda): array
    {
        $patron = '%'.mb_strtolower($busqueda).'%';
        $ids = [];

        foreach (['dron' => 'ope_drones', 'vehiculo' => 'man_vehiculos'] as $tipo => $tabla) {
            $ids[$tipo] = DB::table($tabla)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(identificador) LIKE ?', [$patron])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $ids;
    }

    /**
     * Etiquetas legibles para la columna "Equipo" del listado, agrupadas por
     * `equipo_tipo` para resolver cada tabla plana en un único `whereIn`
     * (mismo criterio de lectura directa que `etiquetasBase()` en
     * `VehiculosController`, extendido acá a dos tablas posibles).
     *
     * @param  Collection<int, OrdenMantenimiento>  $ordenes
     * @return array<string, array<int, string>> claves `dron`/`vehiculo`.
     */
    private function etiquetasEquipo(Collection $ordenes): array
    {
        $idsPorTipo = $ordenes->groupBy('equipo_tipo')->map(fn (Collection $grupo) => $grupo->pluck('equipo_id')->unique()->values()->all());

        $etiquetas = [];

        foreach (['dron' => 'ope_drones', 'vehiculo' => 'man_vehiculos'] as $tipo => $tabla) {
            $ids = $idsPorTipo->get($tipo, []);

            $etiquetas[$tipo] = $ids === []
                ? []
                : DB::table($tabla)->whereIn('id', $ids)->pluck('identificador', 'id')->map(fn ($valor) => (string) $valor)->all();
        }

        return $etiquetas;
    }
}
