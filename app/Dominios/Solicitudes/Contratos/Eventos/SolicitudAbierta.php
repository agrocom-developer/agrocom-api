<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: una solicitud quedó `abierta` (o se renovó) y es visible para
 * los pilotos. La negociación avisa a los pilotos cuyas preferencias coinciden con
 * las hectáreas. DTO de primitivos.
 */
final readonly class SolicitudAbierta implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public int $clienteUsuarioId,
        public string $hectareas,
        public string $precioHa,
        public bool $renovada,
    ) {}
}
