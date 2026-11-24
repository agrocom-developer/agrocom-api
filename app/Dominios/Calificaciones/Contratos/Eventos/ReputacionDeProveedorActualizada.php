<?php

namespace App\Dominios\Calificaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: la calificación promedio de un proveedor cambió (llegó una
 * calificación o un administrador moderó una). Otros módulos pueden escucharlo
 * para refrescar lo que muestran de la reputación. DTO de primitivos.
 */
final readonly class ReputacionDeProveedorActualizada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $proveedorId,
        public string $promedio,
        public int $conteo,
    ) {}
}
