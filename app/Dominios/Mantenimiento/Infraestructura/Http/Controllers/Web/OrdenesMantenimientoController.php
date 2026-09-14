<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Contratos\EscrituraGastoMantenimiento;
use App\Dominios\Finanzas\Contratos\LecturaGastoMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\ListarOrdenesMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\MaquinaEstados\MaquinaEstadosOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\Excepciones\RepuestosInsuficientes;
use App\Dominios\Mantenimiento\Dominio\Excepciones\TransicionOrdenMantenimientoNoPermitida;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CerrarOrdenMantenimientoRequest;
use App\Dominios\Mantenimiento\Infraestructura\Http\Requests\CrearOrdenMantenimientoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * `edit()` también pasa el precio final real de una orden cerrada (HU-88,
 * tarea 103), leído vía {@see LecturaGastoMantenimiento} — nunca
 * `Gasto::query()` directo (ADR 0003 regla 2, mismo criterio que
 * `MaquinaEstadosOrdenMantenimiento` con {@see EscrituraGastoMantenimiento}
 * en sentido contrario).
 */
final class OrdenesMantenimientoController
{
    private const PERMISO_VER = 'mantenimiento.orden.ver';

    private const PERMISO_CREAR = 'mantenimiento.orden.crear';

    private const PERMISO_CERRAR = 'mantenimiento.orden.cerrar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaGastoMantenimiento $lecturaGastoMantenimiento,
    ) {}

    public function index(Request $request, ListarOrdenesMantenimiento $listarOrdenesMantenimiento): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoOrdenMantenimiento::tryFrom($estadoQuery) : null;
        $equipoTipoQuery = $request->string('equipo_tipo')->toString();
        $equipoTipo = in_array($equipoTipoQuery, ['dron', 'vehiculo'], true) ? $equipoTipoQuery : null;

        $ordenes = $listarOrdenesMantenimiento->ejecutar(
            estado: $estado?->value,
            equipoTipo: $equipoTipo,
        );

        return view('mantenimiento::pages.ordenes.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'etiquetasEquipo' => $this->etiquetasEquipo($ordenes->getCollection()),
            'filtros' => ['estado' => $estado?->value, 'equipo_tipo' => $equipoTipo],
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
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

        $maquinaEstados->abrir([
            'equipo_tipo' => $datos['equipo_tipo'],
            'equipo_id' => (int) $datos['equipo_id'],
            'tipo' => $datos['tipo'],
            'descripcion' => $datos['descripcion'],
        ]);

        return redirect()
            ->route('panel.ordenes-mantenimiento.index')
            ->with('estado', __('mantenimiento.ordenes.creada'));
    }

    public function edit(Request $request, OrdenMantenimiento $orden): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        return view('mantenimiento::pages.ordenes.edit', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'etiquetaEquipo' => $this->etiquetaEquipo($orden->equipo_tipo, $orden->equipo_id),
            'repuestosDisponibles' => $this->repuestosDisponibles(),
            'basesDisponibles' => $this->basesDisponibles(),
            'stockPorRepuesto' => $this->stockPorRepuesto(),
            'puedeCerrar' => $this->autorizacion->tienePermiso($request, self::PERMISO_CERRAR),
            'montoGasto' => $orden->gasto_id !== null ? $this->lecturaGastoMantenimiento->montoDe($orden->gasto_id) : null,
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

        return redirect()
            ->route('panel.ordenes-mantenimiento.index')
            ->with('estado', __('mantenimiento.ordenes.cerrada'));
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
