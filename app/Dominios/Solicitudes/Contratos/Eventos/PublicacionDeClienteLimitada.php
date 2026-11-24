<?php

namespace App\Dominios\Solicitudes\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: el promedio del cliente sigue bajo el umbral pasado el plazo
 * del aviso y se limita su publicación hasta una revisión (política de
 * calificación). Avisa al cliente y a quien revisa. DTO de primitivos.
 */
final readonly class PublicacionDeClienteLimitada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $clienteId,
        public int $clienteUsuarioId,
        public string $clienteNombre,
        public string $promedio,
    ) {}
}
