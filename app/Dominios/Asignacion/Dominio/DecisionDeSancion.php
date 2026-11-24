<?php

namespace App\Dominios\Asignacion\Dominio;

/** Una sanción que la política decide aplicar. `diasDeVigencia` nulo: dura hasta que un administrador la levante. */
final readonly class DecisionDeSancion
{
    public function __construct(
        public TipoSancion $tipo,
        public CausaSancion $causa,
        public ?int $diasDeVigencia,
    ) {}
}
