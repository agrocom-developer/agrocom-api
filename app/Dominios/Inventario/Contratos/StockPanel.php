<?php

namespace App\Dominios\Inventario\Contratos;

use App\Dominios\Personal\Contratos\LecturaPanelPersonal;

/**
 * Forma primitiva de una fila de stock bajo mínimo para el dashboard (ADR
 * 0003, regla 2). Cantidades como string decimal (invariante 6): son
 * unidades de repuesto, no dinero, pero el criterio del repo es no bajar a
 * float en ningún dato que se muestre y después se compare.
 *
 * `baseId` viaja pelado — `per_bases` es de `Personal`; el consumidor lo
 * cruza con {@see LecturaPanelPersonal}.
 */
final readonly class StockPanel
{
    public function __construct(
        public int $repuestoId,
        public string $codigo,
        public string $descripcion,
        public string $cantidad,
        public string $stockMinimo,
        public ?int $baseId,
    ) {}
}
