<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia Campania (ADR 0003, regla 2): el
 * total de trabajos ejecutados de un conjunto de contratos, para el aside de
 * `CampaniasController::resumenCampania()` — nunca las tablas
 * `ope_trabajos`/`ope_ordenes_aplicacion` ni sus modelos Eloquent cruzando
 * la frontera hacia Campania.
 */
interface LecturaTrabajosPorContrato
{
    /** @param list<int> $contratoIds */
    public function total(array $contratoIds): int;
}
