<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): el equipo cerró el trabajo y el
 * servidor lo dio por ejecutado; falta la conformidad del cliente. DTO de
 * primitivos.
 */
final readonly class SolicitudEjecutada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public int $clienteUsuarioId,
        public ?int $proveedorId,
    ) {}
}
