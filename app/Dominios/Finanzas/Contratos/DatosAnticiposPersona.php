<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Lo que Finanzas sabe de los anticipos de una persona, para el resumen
 * relacionado de su ficha en `Personal` (ADR 0003, regla 2).
 */
final readonly class DatosAnticiposPersona
{
    /** @param string $montoTotal DECIMAL como `string`, con dos decimales (invariante 6 de CLAUDE.md). */
    public function __construct(
        public int $cantidad,
        public string $montoTotal,
    ) {}
}
