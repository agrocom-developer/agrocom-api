<?php

namespace App\Dominios\Asignacion\Aplicacion;

/** Un proveedor elegible para una ronda, con lo que se guarda en su oferta. */
final readonly class Candidato
{
    public function __construct(
        public int $proveedorId,
        public string $tipo,
        public ?string $distanciaKm,
        public ?string $puntuacion,
        public bool $conPrioridad,
    ) {}
}
