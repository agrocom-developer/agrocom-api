<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: una solicitud quedó `abierta` y es visible para
 * los pilotos. La negociación avisa a los pilotos cuyas preferencias coinciden con
 * la zona y las hectáreas. DTO de primitivos.
 */
final readonly class SolicitudAbierta implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public int $clienteUsuarioId,
        public ?int $zonaId,
        public ?string $zona,
        public ?string $macrorregion,
        public string $hectareas,
        public string $precioHa,
    ) {}
}
