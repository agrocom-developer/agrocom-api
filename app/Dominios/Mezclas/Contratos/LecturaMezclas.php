<?php

namespace App\Dominios\Mezclas\Contratos;

/**
 * Frontera de lectura de `Mezclas` hacia otros módulos (ADR 0003, regla 2).
 * Sin consumidores desde que se retiró el reporte técnico de lote: sale con
 * el módulo `Mezclas`. Lista los productos cargados en TODAS las mezclas del
 * trabajo — puede haber más de un evento `mezcla` por trabajo, mismo criterio
 * que `ope_recepciones_caldo`.
 */
interface LecturaMezclas
{
    /** @return list<ProductoCargado> */
    public function listarPorTrabajoId(int $trabajoId): array;
}
