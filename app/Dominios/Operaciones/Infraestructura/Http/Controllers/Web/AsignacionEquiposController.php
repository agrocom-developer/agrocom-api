<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Controllers\Web;

use App\Dominios\Operaciones\Aplicacion\AsignarEquiposOrden;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\EquipoTrabajoNoVigente;
use App\Dominios\Operaciones\Dominio\Excepciones\HectareasAsignadasSuperanLote;
use App\Dominios\Operaciones\Dominio\Excepciones\LoteNoPerteneceAOrden;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenNoVigenteParaAsignacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenLote;
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
 * `GET/POST /panel/asignacion-equipos*` (HU-70, tarea 85; rediseñado por
 * HU-92, tarea 107 para N lotes/N equipos): "dónde asignarle el trabajo al
 * piloto" — reparto de los lotes de una orden vigente entre equipos de
 * trabajo, cada par equipo↔lote abriendo un `Trabajo` propio desde el panel
 * (ver `Aplicacion/AsignarEquiposOrden`).
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
 * Ninguna regla de negocio acá: las guardas (orden vigente, equipo vigente,
 * lote de la orden, tope de hectáreas por lote) las aplica
 * `AsignarEquiposOrden`. `LecturaLotes` (Comercial) ya no hace falta acá: el
 * tope de hectáreas por lote sale de `ope_orden_lotes`, dato propio de
 * Operaciones (ADR 0003 regla 1).
 */
final class AsignacionEquiposController
{
    private const PERMISO = 'operaciones.orden.asignar_equipos';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /**
     * Órdenes vigentes con su resumen de reparto TOTAL (suma de todos sus
     * lotes: hectáreas solicitadas, ya asignadas, restantes) — la lista de
     * partida para elegir a cuál repartirle equipos. El detalle por lote
     * vive en la ficha (`mostrar()`).
     */
    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $ordenes = OrdenAplicacion::query()
            ->where('estado', EstadoOrdenAplicacion::Vigente)
            ->orderByDesc('fecha_emision')
            ->get();

        $resumenes = $ordenes->mapWithKeys(function (OrdenAplicacion $orden): array {
            return [$orden->id => $this->resumenTotal($orden)];
        });

        return view('operaciones::pages.asignacion-equipos.index', [
            ...$this->autorizacion->cascara($request),
            'ordenes' => $ordenes,
            'resumenes' => $resumenes,
            'etiquetasContrato' => $this->etiquetasContrato($ordenes->pluck('contrato_id')->unique()->values()->all()),
            'etiquetasLote' => $this->etiquetasLote($this->loteIdsDeOrdenes($ordenes)),
            'loteIdsPorOrden' => $this->loteIdsPorOrden($ordenes),
        ]);
    }

    public function mostrar(Request $request, OrdenAplicacion $orden, LecturaEquipoTrabajo $equipos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $trabajosAsignados = Trabajo::query()
            ->where('orden_id', $orden->id)
            ->whereNotNull('equipo_trabajo_id')
            ->orderBy('created_at')
            ->get();

        $resumenPorLote = $this->resumenPorLote($orden);
        $loteIds = array_column($resumenPorLote, 'lote_id');

        return view('operaciones::pages.asignacion-equipos.show', [
            ...$this->autorizacion->cascara($request),
            'orden' => $orden,
            'contratoLabel' => $this->etiquetasContrato([$orden->contrato_id])[$orden->contrato_id] ?? "#{$orden->contrato_id}",
            'resumenTotal' => $this->totalizar($resumenPorLote),
            'resumenPorLote' => $resumenPorLote,
            'trabajosAsignados' => $trabajosAsignados,
            'etiquetasEquipo' => $this->etiquetasEquipo($trabajosAsignados->pluck('equipo_trabajo_id')->unique()->values()->all()),
            'etiquetasLote' => $this->etiquetasLote($loteIds),
            'equiposDisponibles' => $this->equiposDisponibles($equipos),
            'equiposIniciales' => old('equipos', $this->equipoInicialPorDefecto($resumenPorLote)),
        ]);
    }

    public function asignar(AsignarEquipoOrdenRequest $request, OrdenAplicacion $orden, AsignarEquiposOrden $asignarEquiposOrden): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();

        $asignaciones = array_map(fn (array $equipo): array => [
            'equipo_trabajo_id' => (int) $equipo['equipo_trabajo_id'],
            'lotes' => array_map(fn (array $lote): array => [
                'lote_id' => (int) $lote['lote_id'],
                'hectareas' => (string) $lote['hectareas'],
            ], $equipo['lotes']),
        ], $datos['equipos']);

        try {
            $asignarEquiposOrden->ejecutar($orden, $asignaciones);
        } catch (OrdenNoVigenteParaAsignacion|EquipoTrabajoNoVigente|LoteNoPerteneceAOrden|HectareasAsignadasSuperanLote $excepcion) {
            return redirect()
                ->route('panel.asignacion-equipos.show', $orden)
                ->withErrors(['equipos' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.asignacion-equipos.show', $orden)
            ->with('estado', __('operaciones.asignacion_equipos.asignado'));
    }

    /**
     * Una fila de equipo por defecto, con TODOS los lotes de restantes > 0
     * pre-cargados con su hectáreas restantes — cubre directo el caso de un
     * solo equipo (CA de la tarea 107: "con 1 equipo, se le asigna el total
     * de hectáreas de todos los lotes de la orden"). Si no queda ningún lote
     * con restantes > 0 (orden ya totalmente repartida), la fila arranca con
     * un lote vacío, mismo criterio que `campos/_formulario.blade.php`.
     *
     * @param  list<array{lote_id: int, hectareas_solicitadas: string, asignadas: string, restantes: string}>  $resumenPorLote
     * @return list<array{lotes: list<array{lote_id: int, hectareas: string}>}>
     */
    private function equipoInicialPorDefecto(array $resumenPorLote): array
    {
        $lotesConRestantes = array_values(array_filter(
            $resumenPorLote,
            static fn (array $fila): bool => BigDecimal::of($fila['restantes'])->isGreaterThan(BigDecimal::zero()),
        ));

        $lotes = $lotesConRestantes === []
            ? [[]]
            : array_map(
                static fn (array $fila): array => ['lote_id' => $fila['lote_id'], 'hectareas' => $fila['restantes']],
                $lotesConRestantes,
            );

        return [['lotes' => $lotes]];
    }

    /** @return array{hectareas_lote: string, asignadas: string, restantes: string} */
    private function resumenTotal(OrdenAplicacion $orden): array
    {
        return $this->totalizar($this->resumenPorLote($orden));
    }

    /**
     * @param  list<array{lote_id: int, hectareas_solicitadas: string, asignadas: string, restantes: string}>  $resumenPorLote
     * @return array{hectareas_lote: string, asignadas: string, restantes: string}
     */
    private function totalizar(array $resumenPorLote): array
    {
        $hectareasLote = array_reduce(
            $resumenPorLote,
            static fn (BigDecimal $acumulado, array $fila): BigDecimal => $acumulado->plus($fila['hectareas_solicitadas']),
            BigDecimal::zero(),
        );
        $asignadas = array_reduce(
            $resumenPorLote,
            static fn (BigDecimal $acumulado, array $fila): BigDecimal => $acumulado->plus($fila['asignadas']),
            BigDecimal::zero(),
        );

        return [
            'hectareas_lote' => (string) $hectareasLote,
            'asignadas' => (string) $asignadas,
            'restantes' => (string) $hectareasLote->minus($asignadas),
        ];
    }

    /**
     * Resumen por lote de la orden: lo que pidió (`ope_orden_lotes`), lo ya
     * asignado (`ope_trabajos` de ese par orden↔lote) y lo restante.
     *
     * @return list<array{lote_id: int, hectareas_solicitadas: string, asignadas: string, restantes: string}>
     */
    private function resumenPorLote(OrdenAplicacion $orden): array
    {
        return $orden->ordenLotes()->orderBy('lote_id')->get()
            ->map(function (OrdenLote $ordenLote) use ($orden): array {
                $asignadas = BigDecimal::of((string) Trabajo::query()
                    ->where('orden_id', $orden->id)
                    ->where('lote_id', $ordenLote->lote_id)
                    ->sum('hectareas_declaradas'));
                $solicitadas = BigDecimal::of((string) $ordenLote->hectareas_solicitadas);

                return [
                    'lote_id' => $ordenLote->lote_id,
                    'hectareas_solicitadas' => (string) $solicitadas,
                    'asignadas' => (string) $asignadas,
                    'restantes' => (string) $solicitadas->minus($asignadas),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, OrdenAplicacion>  $ordenes
     * @return list<int>
     */
    private function loteIdsDeOrdenes(Collection $ordenes): array
    {
        if ($ordenes->isEmpty()) {
            return [];
        }

        return DB::table('ope_orden_lotes')
            ->whereIn('orden_id', $ordenes->pluck('id')->all())
            ->whereNull('deleted_at')
            ->pluck('lote_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Lotes de CADA orden (columna "Lote" del listado, HU-92: ya no es un
     * valor único por orden).
     *
     * @param  Collection<int, OrdenAplicacion>  $ordenes
     * @return array<int, list<int>>
     */
    private function loteIdsPorOrden(Collection $ordenes): array
    {
        if ($ordenes->isEmpty()) {
            return [];
        }

        return DB::table('ope_orden_lotes')
            ->whereIn('orden_id', $ordenes->pluck('id')->all())
            ->whereNull('deleted_at')
            ->orderBy('lote_id')
            ->get(['orden_id', 'lote_id'])
            ->groupBy('orden_id')
            ->map(fn (Collection $filas): array => $filas->pluck('lote_id')->map(fn ($id) => (int) $id)->all())
            ->all();
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
