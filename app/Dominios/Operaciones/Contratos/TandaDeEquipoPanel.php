<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Una Orden de Trabajo (tanda) vista desde una de las cuadrillas que la
 * ejecutan (ADR 0003, regla 2; tarea 138): lo que ESA cuadrilla tiene dentro
 * de la tanda, no lo de toda la tanda — una misma tanda puede cubrir varias
 * cuadrillas y aparece una vez por cada una.
 *
 * `equipoTrabajoId` referencia `per_equipos_trabajo` (otro módulo, FK plana):
 * el nombre lo resuelve quien llama por `Personal\Contratos\LecturaEquipoTrabajo`.
 * `loteIds` son los lotes de esta cuadrilla en la tanda, sin repetir; su nombre
 * lo resuelve `Comercial`. `estadoOrden`/`tonoOrden` son los de la orden de
 * aplicación a la que pertenece la tanda — el mismo estado y el mismo color que
 * en su listado.
 *
 * `hectareasDeclaradas` es string decimal (invariante 6): la suma de los
 * trabajos de esta cuadrilla en la tanda, acumulada con `BigDecimal`.
 */
final readonly class TandaDeEquipoPanel
{
    /** @param  list<int>  $loteIds */
    public function __construct(
        public int $equipoTrabajoId,
        public int $ordenTrabajoId,
        public int $ordenId,
        public int $nroAplicacion,
        public string $estadoOrden,
        public string $tonoOrden,
        public array $loteIds,
        public int $trabajosAbiertos,
        public int $trabajosTotal,
        public string $hectareasDeclaradas,
    ) {}
}
