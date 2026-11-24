<?php

namespace App\Dominios\Asignacion\Dominio;

/** Una ronda del plan: a quién se ofrece y hasta qué radio. `radioKm` es nulo en la ronda de propios. */
final readonly class RondaPlanificada
{
    public function __construct(
        public int $ronda,
        public GrupoOferta $grupo,
        public ?string $radioKm,
        public bool $esUltima,
    ) {}
}
