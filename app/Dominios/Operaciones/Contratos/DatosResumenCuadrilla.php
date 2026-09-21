<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Lo que Operaciones sabe de una cuadrilla, en números, para el resumen
 * relacionado de su ficha en `Personal` (ADR 0003, regla 2): cuántas
 * estadías en hacienda registró y cuántos trabajos se le asignaron.
 */
final readonly class DatosResumenCuadrilla
{
    public function __construct(
        public int $estadiasTotal,
        public int $estadiasEnCurso,
        public int $trabajosTotal,
        public int $trabajosAbiertos,
    ) {}
}
