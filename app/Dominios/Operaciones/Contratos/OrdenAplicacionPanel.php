<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una orden de aplicación con lo justo para mostrar su estado junto a las
 * haciendas que cubre y las cuadrillas que la trabajan (ADR 0003, regla 2;
 * tarea 138).
 *
 * `loteIds` son los lotes que la orden copió del contrato al emitirse
 * (`ope_orden_lotes`): de ahí `Comercial` saca las haciendas, sin que este
 * módulo lea `com_lotes`. `equipoTrabajoIds` son las cuadrillas con algún
 * trabajo dentro de la orden — vacío mientras nadie se la asignó —; sus
 * nombres y su equipamiento los resuelven `Personal` y `Mantenimiento`.
 *
 * `abierta` distingue las órdenes que todavía se ejecutan (emitida, vigente,
 * pausada) de las que ya terminaron: solo las primeras tienen un equipamiento
 * que mirar hoy. `tono` es el que pinta el badge del listado de órdenes.
 */
final readonly class OrdenAplicacionPanel
{
    /**
     * @param  list<int>  $loteIds
     * @param  list<int>  $equipoTrabajoIds
     */
    public function __construct(
        public int $id,
        public int $nroAplicacion,
        public string $estado,
        public string $tono,
        public bool $abierta,
        public string $fechaEmision,
        public int $equiposNecesarios,
        public array $loteIds,
        public array $equipoTrabajoIds,
    ) {}
}
