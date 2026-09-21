<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Lo que Operaciones sabe de un vehículo, en números, para el resumen
 * relacionado de su ficha en `Mantenimiento` (ADR 0003, regla 2): en cuántas
 * estadías en hacienda se usó y cuántas siguen en curso (sin salida).
 */
final readonly class DatosEstadiasVehiculo
{
    public function __construct(
        public int $total,
        public int $enCurso,
    ) {}
}
