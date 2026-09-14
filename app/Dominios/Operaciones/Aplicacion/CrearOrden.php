<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una orden de aplicación desde el panel (HU-25, tarea 38; ampliada a
 * N lotes por HU-92, tarea 107): adaptador sobre
 * {@see MaquinaEstadosOrden::crear()} — toda orden nueva nace `emitida`
 * (invariante 7) — más la carga de sus lotes (`ope_orden_lotes`), ambas
 * escrituras en la MISMA transacción: una orden sin al menos un lote no
 * tiene sentido de negocio (espec §4.3 ampliada).
 */
final class CrearOrden
{
    public function __construct(private readonly MaquinaEstadosOrden $maquinaEstados) {}

    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija la máquina de estados.
     * @param  list<array{lote_id: int, hectareas_solicitadas: string}>  $lotes
     */
    public function ejecutar(array $atributos, array $lotes): OrdenAplicacion
    {
        return DB::transaction(function () use ($atributos, $lotes): OrdenAplicacion {
            $orden = $this->maquinaEstados->crear($atributos);

            foreach ($lotes as $lote) {
                $orden->ordenLotes()->create($lote);
            }

            return $orden;
        });
    }
}
