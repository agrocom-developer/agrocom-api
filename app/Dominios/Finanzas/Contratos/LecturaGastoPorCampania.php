<?php

namespace App\Dominios\Finanzas\Contratos;

/**
 * Frontera de lectura de Finanzas hacia Campania (ADR 0003, regla 2): el
 * total gastado (`Gasto` + `Combustible`) de una campaña, para el aside de
 * `CampaniasController::resumenCampania()` — nunca las tablas
 * `fin_gastos`/`fin_combustibles` ni sus modelos Eloquent cruzando la
 * frontera hacia Campania.
 */
interface LecturaGastoPorCampania
{
    /** DECIMAL como `string` (invariante 6 de CLAUDE.md), suma de Gasto + Combustible. */
    public function totalGastado(int $campaniaId): string;
}
