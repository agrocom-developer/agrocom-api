<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Lo que Mantenimiento sabe de una base, en números, para el resumen
 * relacionado de su ficha en `Personal` (ADR 0003, regla 2): cuántos
 * vehículos, generadores y baterías la tienen asignada.
 */
final readonly class DatosEquipamientoBase
{
    public function __construct(
        public int $vehiculos,
        public int $generadores,
        public int $baterias,
    ) {}
}
