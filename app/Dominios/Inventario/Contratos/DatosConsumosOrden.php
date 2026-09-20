<?php

namespace App\Dominios\Inventario\Contratos;

/**
 * Lo que Inventario sabe de una orden de mantenimiento, en números, para el
 * resumen relacionado de su ficha en `Mantenimiento` (ADR 0003, regla 2):
 * cuántas líneas de repuesto consumió, cuántas unidades suman y cuánto
 * costaron. Hermano de {@see DatosStockBase}.
 *
 * `unidades` y `costoTotal` son DECIMAL como string (invariante 6 de
 * CLAUDE.md), sumados del lado del dueño de los datos.
 */
final readonly class DatosConsumosOrden
{
    public function __construct(
        public int $lineas,
        public string $unidades,
        public string $costoTotal,
    ) {}
}
