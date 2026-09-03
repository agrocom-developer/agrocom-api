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
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\ActualizarOrdenRequest;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\CrearOrdenRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
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

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ListarOrdenesAplicacion $listarOrdenes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estadoQuery = $request->string('estado')->toString();
        $estado = $estadoQuery !== '' ? EstadoOrdenAplicacion::tryFrom($estadoQuery) : null;

        $ordenes = $listarOrdenes->ejecutar(estado: $estado);

        return view('operaciones::pages.ordenes.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'etiquetasContrato' => $this->etiquetasContrato($ordenes->pluck('contrato_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'etiquetasLote' => $this->etiquetasLote($ordenes->pluck('lote_id')->map(fn ($id) => (int) $id)->unique()->values()->all()),
            'filtros' => ['estado' => $estado?->value],
            'puedeActivar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ACTIVAR),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        return view('operaciones::pages.ordenes.create', [
            ...$this->autorizacion->cascara($request),
            'contratosDisponibles' => $this->contratosDisponibles(),
            'lotesDisponibles' => $this->lotesDisponibles(),
            'contactosDisponibles' => $this->contactosDisponibles(),
        ]);
    }

    public function store(CrearOrdenRequest $request, CrearOrden $crearOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_CREAR), 403);

        $crearOrden->ejecutar($this->normalizarDatos($request->validated()));

        return redirect()
            ->route('panel.ordenes.index')
            ->with('estado', __('operaciones.ordenes.creada'));
    }

    public function edit(Request $request, OrdenAplicacion $orden): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        return view('operaciones::pages.ordenes.edit', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'contratosDisponibles' => $this->contratosDisponibles(),
            'lotesDisponibles' => $this->lotesDisponibles(),
            'contactosDisponibles' => $this->contactosDisponibles(),
        ]);
    }

    public function update(ActualizarOrdenRequest $request, OrdenAplicacion $orden, ActualizarOrden $actualizarOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        try {
            $actualizarOrden->ejecutar($orden, $this->normalizarDatos($request->validated()));
        } catch (OrdenNoEditable $excepcion) {
            return redirect()
                ->route('panel.ordenes.index')
                ->withErrors(['estado' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.ordenes.index')
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
        return [
            'contrato_id' => (int) $datos['contrato_id'],
            'lote_id' => (int) $datos['lote_id'],
            'nro_aplicacion' => (int) $datos['nro_aplicacion'],
            'litros_ha' => (string) $datos['litros_ha'],
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

    /** @return Collection<int, string> */
    private function lotesDisponibles(): Collection
    {
        return DB::table('com_lotes as l')
            ->join('com_campos as c', 'c.id', '=', 'l.campo_id')
            ->whereNull('l.deleted_at')
            ->whereNull('c.deleted_at')
            ->orderBy('c.nombre')
            ->orderBy('l.codigo')
            ->get(['l.id', 'c.nombre', 'l.codigo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => __('operaciones.ordenes.campo_lote_opcion', [
                    'campo' => $fila->nombre,
                    'codigo' => $fila->codigo,
                ]),
            ]);
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
            ->join('com_campos as c', 'c.id', '=', 'l.campo_id')
            ->whereIn('l.id', $ids)
            ->get(['l.id', 'c.nombre', 'l.codigo'])
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
