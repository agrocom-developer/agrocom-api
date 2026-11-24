<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: el proveedor canceló un servicio ya aceptado y la solicitud vuelve a asignación con prioridad. Avisa al cliente. DTO de primitivos.
 */
final readonly class SolicitudReasignada implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa.
     */
    public function __construct(
        public int $solicitudId,
        public int $ciclo,
        public int $clienteUsuarioId,
        public int $proveedorId,
        public array $usuariosDestinatarios,
    ) {}
}
