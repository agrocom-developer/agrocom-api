<?php

namespace App\Dominios\Campania\Contratos;

/**
 * Forma de dato primitiva de una campaña para quien necesita decidir algo
 * sobre ella sin importar el modelo Eloquent `Campania` (ADR 0003, regla 2):
 * hoy, `Comercial` y `Finanzas` (HU-46, tarea 69) para validar si admite
 * imputaciones nuevas. Solo los campos que esa guarda necesita — no es un
 * duplicado de `Campania`.
 *
 * Sin `clienteId` (ADR 0015, corregido el 15/9/2026): la campaña ya no tiene
 * cliente propio.
 */
final readonly class DatosCampania
{
    public function __construct(
        public int $id,
        public string $codigo,
        public bool $cerrada,
    ) {}
}
