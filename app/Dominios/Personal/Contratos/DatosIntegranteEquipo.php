<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Forma de dato primitiva de un integrante de equipo vigente en una fecha
 * dada (ADR 0003, regla 2) — quién era el piloto o el auxiliar ese día, no
 * quién lo es hoy.
 */
final readonly class DatosIntegranteEquipo
{
    public function __construct(
        public int $id,
        public int $personaId,
        public string $nombrePersona,
        public string $rolEquipo,
        public string $desde,
        public ?string $hasta,
    ) {}
}
