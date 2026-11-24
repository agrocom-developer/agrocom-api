<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): el cliente publicó una solicitud con su
 * tarifa congelada. El motor de asignación (ADR 0030) la toma de aquí. DTO de
 * primitivos, sin modelos Eloquent (ADR 0003).
 */
final readonly class SolicitudPublicada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public string $hectareas,
        public bool $urgente,
        public bool $republicada,
    ) {}
}
