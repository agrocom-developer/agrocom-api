<?php

namespace App\Dominios\Flota\Contratos;

/** Disponibilidad declarada de un equipo (ver {@see LecturaDisponibilidad}). */
final readonly class DatosDisponibilidad
{
    public function __construct(
        public int $equipoId,
        public bool $enLinea,
        public ?string $lat,
        public ?string $lng,
        public string $actualizadoEn,
    ) {}
}
