<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: el promedio de los proveedores que calificaron al cliente
 * quedó bajo el umbral y se le avisa (política de calificación). DTO de primitivos.
 */
final readonly class ClienteAvisadoPorCalificacion implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $clienteId,
        public int $clienteUsuarioId,
        public string $promedio,
    ) {}
}
