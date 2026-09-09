<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Forma de dato primitiva de un recurso (dron/vehículo/generador) asignado a
 * un equipo, vigente en una fecha dada (ADR 0003, regla 2). `recursoTipo` +
 * `recursoId` viajan crudos, igual que en `per_equipo_recursos`: resolver el
 * identificador legible (`DRN-01`, la placa, etc.) es responsabilidad de
 * quien consume el dato y conoce la tabla de destino, no de este contrato.
 */
final readonly class DatosRecursoEquipo
{
    public function __construct(
        public int $id,
        public string $recursoTipo,
        public int $recursoId,
        public string $desde,
        public ?string $hasta,
    ) {}
}
