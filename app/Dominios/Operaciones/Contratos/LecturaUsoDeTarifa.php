<?php

namespace App\Dominios\Operaciones\Contratos;

/**
 * Cuántas órdenes de trabajo eligieron una tarifa del catálogo de Finanzas
 * (ADR 0023). Finanzas lo muestra en la ficha de la tarifa; nunca lee
 * `ope_orden_trabajo_equipos` por su cuenta (ADR 0003).
 */
interface LecturaUsoDeTarifa
{
    /** @return array{equipos: int, ordenes: int, negociados: int} */
    public function resumenDe(int $tarifaId): array;
}
