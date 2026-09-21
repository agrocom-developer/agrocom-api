<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\PoliticaCierreOrden;
use Illuminate\Support\Facades\DB;

/**
 * Datos derivados de cada orden de aplicación para el panel (pedido del dueño,
 * 18/9/2026, ADR 0022): lo que muestra el índice (cantidad de lotes, órdenes de
 * trabajo realizadas) y el badge de inconvenientes del índice y del detalle.
 * Suma `trabajos` y `trabajos_abiertos` (equipo×lote, total y sin terminar) y las
 * hectáreas solicitadas y asignadas: lo que {@see PoliticaCierreOrden} necesita
 * para decir si la orden ya se puede cerrar.
 * Solo lectura, en pocas consultas agrupadas — nunca una por fila.
 *
 * Los "inconvenientes" NO son un dato nuevo: son las incidencias
 * (`ope_incidencias`, las registra el piloto o el auxiliar desde la app) y las
 * pausas (`ope_pausas`, con causa atribuible) de las sesiones de los trabajos
 * de la orden — el detalle vive en las órdenes de trabajo. La orden solo avisa
 * "hubo un inconveniente" para que el operador mire y decida (pausar, cancelar
 * o seguir, hablando con el dueño). Este resumen nunca cambia un estado.
 *
 * Todas las tablas son de `Operaciones`: no cruza módulos.
 */
final class ResumenDeOrdenes
{
    /**
     * @param  list<int>  $ordenIds
     * @return array<int, array{lotes: int, ordenes_trabajo: int, trabajos: int, trabajos_abiertos: int, hectareas_solicitadas: string, hectareas_asignadas: string, incidencias: int, pausas: int, minutos_pausa: int}>
     */
    public function ejecutar(array $ordenIds): array
    {
        $resumen = [];

        foreach ($ordenIds as $id) {
            $resumen[$id] = ['lotes' => 0, 'ordenes_trabajo' => 0, 'trabajos' => 0, 'trabajos_abiertos' => 0, 'hectareas_solicitadas' => '0', 'hectareas_asignadas' => '0', 'incidencias' => 0, 'pausas' => 0, 'minutos_pausa' => 0];
        }

        if ($ordenIds === []) {
            return $resumen;
        }

        $lotes = DB::table('ope_orden_lotes')
            ->whereIn('orden_id', $ordenIds)
            ->whereNull('deleted_at')
            ->groupBy('orden_id')
            ->selectRaw('orden_id, count(*) as n')
            ->get();

        foreach ($lotes as $fila) {
            $resumen[(int) $fila->orden_id]['lotes'] = (int) $fila->n;
        }

        $ordenesTrabajo = DB::table('ope_ordenes_trabajo')
            ->whereIn('orden_id', $ordenIds)
            ->whereNull('deleted_at')
            ->groupBy('orden_id')
            ->selectRaw('orden_id, count(*) as n')
            ->get();

        foreach ($ordenesTrabajo as $fila) {
            $resumen[(int) $fila->orden_id]['ordenes_trabajo'] = (int) $fila->n;
        }

        $trabajos = DB::table('ope_trabajos')
            ->whereIn('orden_id', $ordenIds)
            ->whereNull('deleted_at')
            ->groupBy('orden_id')
            ->selectRaw("orden_id, count(*) as n, sum(case when estado = 'abierto' then 1 else 0 end) as abiertos, coalesce(sum(hectareas_declaradas), 0) as hectareas")
            ->get();

        foreach ($trabajos as $fila) {
            $resumen[(int) $fila->orden_id]['trabajos'] = (int) $fila->n;
            $resumen[(int) $fila->orden_id]['trabajos_abiertos'] = (int) $fila->abiertos;
            $resumen[(int) $fila->orden_id]['hectareas_asignadas'] = (string) $fila->hectareas;
        }

        $solicitadas = DB::table('ope_orden_lotes')
            ->whereIn('orden_id', $ordenIds)
            ->whereNull('deleted_at')
            ->groupBy('orden_id')
            ->selectRaw('orden_id, coalesce(sum(hectareas_solicitadas), 0) as hectareas')
            ->get();

        foreach ($solicitadas as $fila) {
            $resumen[(int) $fila->orden_id]['hectareas_solicitadas'] = (string) $fila->hectareas;
        }

        $incidencias = DB::table('ope_incidencias as i')
            ->join('ope_sesiones as s', 's.id', '=', 'i.sesion_id')
            ->join('ope_trabajos as t', 't.id', '=', 's.trabajo_id')
            ->whereIn('t.orden_id', $ordenIds)
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.orden_id')
            ->selectRaw('t.orden_id as orden_id, count(i.id) as n')
            ->get();

        foreach ($incidencias as $fila) {
            $resumen[(int) $fila->orden_id]['incidencias'] = (int) $fila->n;
        }

        $pausas = DB::table('ope_pausas as p')
            ->join('ope_sesiones as s', 's.id', '=', 'p.sesion_id')
            ->join('ope_trabajos as t', 't.id', '=', 's.trabajo_id')
            ->whereIn('t.orden_id', $ordenIds)
            ->whereNull('p.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.orden_id')
            ->selectRaw('t.orden_id as orden_id, count(p.id) as n, sum(p.duracion_minutos) as minutos')
            ->get();

        foreach ($pausas as $fila) {
            $resumen[(int) $fila->orden_id]['pausas'] = (int) $fila->n;
            $resumen[(int) $fila->orden_id]['minutos_pausa'] = (int) $fila->minutos;
        }

        return $resumen;
    }
}
