<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: una oferta viva se retiró porque otro proveedor aceptó antes, el cliente canceló o un administrador asignó a mano. DTO de primitivos.
 */
final readonly class OfertaRetirada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa.
     */
    public function __construct(
        public int $ofertaId,
        public int $solicitudId,
        public int $proveedorId,
        public array $usuariosDestinatarios,
        public string $motivo,
    ) {}
}
