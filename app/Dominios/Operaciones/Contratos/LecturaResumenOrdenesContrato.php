<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Frontera de lectura de Operaciones hacia Comercial (ADR 0003, regla 2):
 * el resumen de Órdenes de Aplicación de los contratos de un cliente, para
 * el aside de `ClientesController::resumenRelacionado()` — nunca la tabla
 * `ope_ordenes_aplicacion` ni el modelo Eloquent `OrdenAplicacion` cruzando
 * la frontera hacia Comercial.
 */
interface LecturaResumenOrdenesContrato
{
    /**
     * @param  list<int>  $contratoIds
     * @return array{total: int, vigentes: int}
     */
    public function resumen(array $contratoIds): array;
}
