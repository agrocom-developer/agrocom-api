<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): el motor le ofreció una solicitud a un proveedor. Lleva lo que el aviso muestra; los avisos van a las cuentas del proveedor. DTO de primitivos.
 */
final readonly class OfertaCreada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa.
     */
    public function __construct(
        public int $ofertaId,
        public int $solicitudId,
        public int $proveedorId,
        public array $usuariosDestinatarios,
        public string $hectareas,
        public string $venceEn,
        public bool $urgente,
        public int $ronda,
        public string $grupo,
    ) {}
}
