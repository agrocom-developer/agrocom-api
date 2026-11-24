<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §12): el proveedor avisó que el equipo salió
 * hacia el lote. Avisa al cliente. DTO de primitivos.
 */
final readonly class EquipoEnCamino implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $clienteUsuarioId,
        public int $proveedorId,
        public int $equipoId,
    ) {}
}
