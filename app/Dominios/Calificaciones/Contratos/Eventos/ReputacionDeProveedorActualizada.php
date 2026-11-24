<?php

namespace App\Dominios\Calificaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: la calificación promedio de un proveedor cambió (llegó una
 * calificación o un administrador moderó una). La asignación lo escucha para
 * aplicar la política de sanciones al instante y no esperar a la evaluación diaria.
 * DTO de primitivos.
 */
final readonly class ReputacionDeProveedorActualizada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $proveedorId,
        public string $promedio,
        public int $conteo,
    ) {}
}
