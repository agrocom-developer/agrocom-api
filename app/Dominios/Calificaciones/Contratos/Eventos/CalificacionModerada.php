<?php

namespace App\Dominios\Calificaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): un administrador ocultó o restableció una
 * calificación, con causa y motivo. La reputación ya se recalculó. DTO de
 * primitivos.
 */
final readonly class CalificacionModerada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $calificacionId,
        public int $solicitudId,
        public string $estadoNuevo,
        public ?string $causa,
        public int $autorUsuarioId,
    ) {}
}
