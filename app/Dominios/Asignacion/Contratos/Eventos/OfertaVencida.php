<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): una oferta venció sin respuesta del proveedor. DTO de primitivos.
 */
final readonly class OfertaVencida implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa.
     */
    public function __construct(
        public int $ofertaId,
        public int $solicitudId,
        public int $proveedorId,
        public array $usuariosDestinatarios,
    ) {}
}
