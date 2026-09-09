<?php

namespace App\Dominios\Campania\Contratos;

/**
 * Forma de dato primitiva de una campaña para quien necesita decidir algo
 * sobre ella sin importar el modelo Eloquent `Campania` (ADR 0003, regla 2):
 * hoy, `Comercial` y `Finanzas` (HU-46, tarea 69) para validar a qué campaña
 * puede imputarse un contrato o un gasto nuevo. Solo los tres campos que esa
 * guarda necesita — no es un duplicado de `Campania`, es el recorte que le
 * corresponde a este contrato.
 */
final readonly class DatosCampania
{
    public function __construct(
        public int $id,
        public string $codigo,
        public int $clienteId,
        public bool $cerrada,
    ) {}
}
