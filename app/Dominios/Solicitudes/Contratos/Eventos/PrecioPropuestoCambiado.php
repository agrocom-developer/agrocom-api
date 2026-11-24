<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Hecho de dominio: el cliente cambió el precio por hectárea propuesto de una solicitud en el mercado. DTO de primitivos. */
final readonly class PrecioPropuestoCambiado implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public ?string $precioAnterior,
        public string $precioNuevo,
    ) {}
}
