<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Lo que Operaciones sabe de una persona, en números, para el resumen
 * relacionado de su ficha en `Personal` (ADR 0003, regla 2): en cuántas
 * sesiones participó y cuántas de ellas ya se validaron. No trae rechazos ni
 * incidencias: eso es la ficha de desempeño, con su propio permiso.
 */
final readonly class DatosSesionesPersona
{
    public function __construct(
        public int $total,
        public int $validadas,
    ) {}
}
