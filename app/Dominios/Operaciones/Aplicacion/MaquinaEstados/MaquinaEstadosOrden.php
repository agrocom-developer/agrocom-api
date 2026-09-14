<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\Excepciones\OrdenVigenteDuplicadaEnLote;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionOrdenNoPermitida;
use App\Dominios\Operaciones\Dominio\MaquinaEstados\TransicionesOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea/muta el `estado` de `ope_ordenes_aplicacion`
 * (invariante 7 de CLAUDE.md), mismo criterio que `MaquinaEstadosContrato`.
 *
 * La guarda de "una única orden vigente por lote" (HU-92, tarea 107) YA NO
 * puede vivir en un índice parcial de Postgres: desde que una orden cubre N
 * lotes (`ope_orden_lotes`), la regla cruza esa tabla (`lote_id`) con
 * `ope_ordenes_aplicacion.estado` — un índice parcial no puede condicionar
 * sobre una tabla ajena (ver docblock de la migración
 * `create_ope_orden_lotes_table`). `activar()` la verifica explícito, DENTRO
 * de la transacción que también aplica el cambio de estado.
 */
final class MaquinaEstadosOrden
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): OrdenAplicacion
    {
        return OrdenAplicacion::create([...$atributos, 'estado' => EstadoOrdenAplicacion::Emitida]);
    }

    /**
     * `emitida → vigente` (HU-25, tarea 38; guarda rediseñada HU-92, tarea
     * 107).
     *
     * @throws TransicionOrdenNoPermitida si `$orden` no está `emitida`.
     * @throws OrdenVigenteDuplicadaEnLote si alguno de los lotes de la orden ya tiene otra orden vigente.
     */
    public function activar(OrdenAplicacion $orden): OrdenAplicacion
    {
        $desde = $orden->estado;
        $hasta = EstadoOrdenAplicacion::Vigente;

        if (! TransicionesOrden::permitida($desde, $hasta)) {
            throw TransicionOrdenNoPermitida::entre($desde, $hasta);
        }

        return DB::transaction(function () use ($orden, $hasta): OrdenAplicacion {
            $this->verificarSinOrdenVigenteQueComparteLote($orden);

            $orden->estado = $hasta;
            $orden->save();

            return $orden->refresh();
        });
    }

    /**
     * Bloquea (`FOR UPDATE`, sin efecto en SQLite — motor de los tests) TODAS
     * las filas de `ope_orden_lotes` de los lotes de `$orden`, sin importar
     * de qué orden sean: sin este lock, dos activaciones concurrentes de
     * órdenes DISTINTAS que comparten un lote leerían ambas "ninguna vigente
     * todavía" (ninguna cambió su estado aún) y las dos pasarían la guarda —
     * antes, el índice único parcial cerraba esa carrera a nivel de base;
     * acá lo hace este lock, serializando la segunda transacción hasta que
     * la primera confirme (o revierta) su cambio de estado.
     *
     * @throws OrdenVigenteDuplicadaEnLote si algún lote de `$orden` ya está cubierto por otra orden vigente.
     */
    private function verificarSinOrdenVigenteQueComparteLote(OrdenAplicacion $orden): void
    {
        $lotesIds = DB::table('ope_orden_lotes')
            ->where('orden_id', $orden->id)
            ->whereNull('deleted_at')
            ->pluck('lote_id');

        if ($lotesIds->isEmpty()) {
            return;
        }

        $loteVigenteEnOtraOrden = DB::table('ope_orden_lotes as ol')
            ->join('ope_ordenes_aplicacion as o', 'o.id', '=', 'ol.orden_id')
            ->whereIn('ol.lote_id', $lotesIds)
            ->whereNull('ol.deleted_at')
            ->whereNull('o.deleted_at')
            ->lockForUpdate()
            ->where('o.id', '!=', $orden->id)
            ->where('o.estado', EstadoOrdenAplicacion::Vigente->value)
            ->value('ol.lote_id');

        if ($loteVigenteEnOtraOrden !== null) {
            throw OrdenVigenteDuplicadaEnLote::porLote((int) $loteVigenteEnOtraOrden);
        }
    }
}
