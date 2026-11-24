<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): una solicitud pasó a `cancelada`. La
 * asignación retira sus ofertas vivas y avisa a la contraparte. `estadoPrevio`
 * dice en qué punto del ciclo estaba, porque la política de cancelación
 * distingue antes y después de aceptar (ADR 0033). DTO de primitivos.
 */
final readonly class SolicitudCancelada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteId,
        public ?int $proveedorId,
        public string $estadoPrevio,
        public string $canceladaPor,
        public string $motivo,
    ) {}
}
