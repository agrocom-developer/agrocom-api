<?php

namespace App\Dominios\Asignacion\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio (especificación §7): se agotaron las rondas sin que nadie
 * aceptara y la solicitud pasó a `sin_proveedor`. Avisa al cliente, que elige
 * ampliar la ventana, mejorar la tarifa o cancelar sin penalidad (ADR 0030,
 * punto 4), y al equipo de operaciones. DTO de primitivos.
 */
final readonly class SolicitudSinProveedor implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $solicitudId,
        public int $ciclo,
        public int $clienteUsuarioId,
    ) {}
}
