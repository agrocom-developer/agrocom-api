<?php

namespace App\Dominios\Operaciones\Dominio;

/**
 * Tipo de insumo de una categoría (HU-79, tarea 110): determina si la orden
 * pide kilos por vuelo (sólido) o litros por hectárea (líquido) — mismo
 * criterio de enum de dominio que {@see TipoAplicacion}. Vive también como
 * columna `tipo_insumo` de `ope_categorias_insumo` (CHECK en la migración).
 */
enum TipoInsumo: string
{
    case Solido = 'solido';
    case Liquido = 'liquido';
}
