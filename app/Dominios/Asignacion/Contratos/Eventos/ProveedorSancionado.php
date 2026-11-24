<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: el motor aplicó una sanción a un proveedor por sus cancelaciones, su calificación o su aceptación (ADR 0038). Avisa al proveedor y, si deja una revisión pendiente, al equipo de operaciones. DTO de primitivos.
 */
final readonly class ProveedorSancionado implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $usuariosDestinatarios  cuentas a las que se avisa.
     */
    public function __construct(
        public int $sancionId,
        public int $proveedorId,
        public string $proveedorNombre,
        public string $tipo,
        public string $causa,
        public array $usuariosDestinatarios,
    ) {}
}
