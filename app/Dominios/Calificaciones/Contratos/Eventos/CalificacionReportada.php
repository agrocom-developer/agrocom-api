<?php

namespace App\Dominios\Calificaciones\Contratos\Eventos;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Hecho de dominio: el proveedor reportó una calificación que considera abusiva y
 * quedó `en_moderacion` a la espera de un administrador (política de calificación).
 * DTO de primitivos.
 */
final readonly class CalificacionReportada implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $calificacionId,
        public int $solicitudId,
        public int $proveedorId,
        public string $proveedorNombre,
    ) {}
}
