<?php

namespace App\Dominios\Mantenimiento\Contratos;

/**
 * Lo que Mantenimiento sabe de un conjunto de órdenes, en datos primitivos,
 * para el resumen relacionado de la ficha de un repuesto en `Inventario` (ADR
 * 0003, regla 2): cuántas son, cuántas fueron correctivas —o sea, no previstas
 * por un plan— y cuándo se cerró la última.
 *
 * `ultimoCierre` viaja como instante ISO 8601 (la base guarda UTC) y no como
 * objeto: quien lo pinta lo pasa a la zona horaria del usuario y decide el
 * formato, y el contrato no arrastra ninguna clase de fecha.
 */
final readonly class DatosResumenOrdenesMantenimiento
{
    public function __construct(
        public int $total,
        public int $correctivas,
        public ?string $ultimoCierre,
    ) {}
}
