<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Contratos\LecturaLotes;
use App\Dominios\Operaciones\Aplicacion\AsignarEquiposOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Operaciones\Infraestructura\Http\Requests\AsignarEquipoOrdenRequest;
use App\Dominios\Personal\Contratos\DatosEquipoTrabajo;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/asignacion-equipos*` (HU-70, tarea 85): "dónde asignarle
 * el trabajo al piloto" — reparto de las hectáreas de una orden vigente entre
 * equipos de trabajo, cada reparto abriendo un `Trabajo` propio desde el
 * panel (ver `Aplicacion/AsignarEquiposOrden`).
 *
 * Ficha PROPIA, no la de `OrdenesController` (que hoy ni siquiera tiene
 * `show`, solo `index`/`create`/`edit`): el dueño de esta HU en el reclamo de
 * negocio es el jefe de campo (`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`,
 * fila HU-70), que no administra órdenes (no tiene `operaciones.orden.ver`,
 * ver `SeguridadSeeder::PERMISOS_JEFE_CAMPO`) — solo necesita ver las
 * vigentes y repartirlas. Un único permiso de grano fino
 * (`operaciones.orden.asignar_equipos`), verificado DENTRO del controlador
 * contra el ROL ACTIVO vía {@see AutorizacionPanelWeb}, gatea las tres
 * acciones — no hace falta separar ver de asignar: quien puede repartir
 * equipos puede ver el estado del reparto.
 *
 * Ninguna regla de negocio acá: las tres guardas (orden vigente, equipo
 * vigente, tope de hectáreas del lote) las aplica `AsignarEquiposOrden`.
 */
final class AsignacionEquiposController
{
    private const PERMISO = 'operaciones.orden.asignar_equipos';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /**
     * Órdenes vigentes con su resumen de reparto (hectáreas del lote, ya
     * asignadas, restantes) — la lista de partida para elegir a cuál
     * repartirle equipos. Una orden sin lote legible (caso teórico, ver
     * docblock de `AsignarEquiposOrden`) muestra "0.00" en vez de romper la
     * pantalla.
     */
    public function index(Request $request, LecturaLotes $lotes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $ordenes = OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->orderByDesc('fecha_emision')
            ->get();

        $resumenes = $ordenes->mapWithKeys(function (OrdenAplicacion $orden) use ($lotes): array {
            return [$orden->id => $this->resumenReparto($orden, $lotes)];
        });

        return view('operaciones::pages.asignacion-equipos.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'resumenes' => $resumenes,
            'etiquetasContrato' => $this->etiquetasContrato($ordenes->pluck('contrato_id')->unique()->values()->all()),
            'etiquetasLote' => $this->etiquetasLote($ordenes->pluck('lote_id')->unique()->values()->all()),
        ]);
    }

    public function mostrar(Request $request, OrdenAplicacion $orden, LecturaEquipoTrabajo $equipos, LecturaLotes $lotes): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $trabajosAsignados = Trabajo::query()
            ->where('orden_id', $orden->id)
            ->whereNotNull('equipo_trabajo_id')
            ->orderBy('created_at')
            ->get();

        return view('operaciones::pages.asignacion-equipos.show', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'contratoLabel' => $this->etiquetasContrato([$orden->contrato_id])[$orden->contrato_id] ?? "#{$orden->contrato_id}",
            'loteLabel' => $this->etiquetasLote([$orden->lote_id])[$orden->lote_id] ?? "#{$orden->lote_id}",
            'resumen' => $this->resumenReparto($orden, $lotes),
            'trabajosAsignados' => $trabajosAsignados,
            'etiquetasEquipo' => $this->etiquetasEquipo($trabajosAsignados->pluck('equipo_trabajo_id')->unique()->values()->all()),
            'equiposDisponibles' => $this->equiposDisponibles($equipos),
        ]);
    }

    public function asignar(AsignarEquipoOrdenRequest $request, OrdenAplicacion $orden, AsignarEquiposOrden $asignarEquiposOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();

        try {
            $asignarEquiposOrden->ejecutar($orden, [[
                'equipo_trabajo_id' => (int) $datos['equipo_trabajo_id'],
                'hectareas' => (string) $datos['hectareas'],
            ]]);
        } catch (OrdenNoVigenteParaAsignacion|EquipoTrabajoNoVigente|HectareasAsignadasSuperanLote $excepcion) {
            return redirect()
                ->route('panel.asignacion-equipos.show', $orden)
                ->withErrors(['equipo_trabajo_id' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.asignacion-equipos.show', $orden)
            ->with('estado', __('operaciones.asignacion_equipos.asignado'));
    }

    /** @return array{hectareas_lote: string, asignadas: string, restantes: string} */
    private function resumenReparto(OrdenAplicacion $orden, LecturaLotes $lotes): array
    {
        $hectareasLote = BigDecimal::of($lotes->obtenerPorId($orden->lote_id)->hectareas ?? '0');
        $asignadas = BigDecimal::of((string) Trabajo::query()->where('orden_id', $orden->id)->sum('hectareas_declaradas'));
        $restantes = $hectareasLote->minus($asignadas);

        return [
            'hectareas_lote' => (string) $hectareasLote,
            'asignadas' => (string) $asignadas,
            'restantes' => (string) $restantes,
        ];
    }

    /** @return Collection<int, string> */
    private function equiposDisponibles(LecturaEquipoTrabajo $equipos): Collection
    {
        return collect($equipos->vigentesAFecha(now()->toDateString()))
            ->mapWithKeys(fn (DatosEquipoTrabajo $equipo): array => [
                $equipo->id => $equipo->nombre !== null
                    ? "{$equipo->codigo} — {$equipo->nombre}"
                    : $equipo->codigo,
            ]);
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
     * Mismo criterio que `OrdenesController::etiquetasContrato()`: lectura
     * directa por `DB::table` (ADR 0003 regla 3), sin filtrar por
     * `deleted_at` — una etiqueta histórica no deja de mostrarse porque el
     * contrato se dio de baja después de emitida la orden.
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
}
