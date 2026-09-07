<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Forma primitiva de un anticipo para el tablero del piloto/auxiliar (ADR
 * 0003, regla 2). `monto` como string decimal (invariante 6): se resta del
 * devengado para mostrar el saldo, y esa resta tiene que cuadrar exacto con
 * la que hace la planilla.
 */
final readonly class AnticipoPanel
{
    public function __construct(
        public int $id,
        public string $fecha,
        public string $monto,
        public ?string $motivo,
    ) {}
}
