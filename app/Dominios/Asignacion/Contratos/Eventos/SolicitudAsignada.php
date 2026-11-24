<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): un proveedor aceptó o un administrador asignó la solicitud, y quedó fijado el equipo. Avisa al cliente y al proveedor. DTO de primitivos.
 */
final readonly class SolicitudAsignada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa.
     */
    public function __construct(
        public int $solicitudId,
        public int $ciclo,
        public int $clienteUsuarioId,
        public int $proveedorId,
        public string $proveedorNombre,
        public int $equipoId,
        public array $usuariosDestinatarios,
        public string $origen,
    ) {}
}
