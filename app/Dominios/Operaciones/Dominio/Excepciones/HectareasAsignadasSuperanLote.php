<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use RuntimeException;

/**
 * Guarda de `AsignarEquiposOrden` (HU-70, tarea 85): el reparto de una orden
 * entre equipos divide las hectáreas del LOTE de esa orden — la suma de lo ya
 * asignado más lo nuevo nunca puede superar `com_lotes.hectareas`. Mismo
 * criterio (comparación con `Brick\Math\BigDecimal`, invariante 6 de
 * CLAUDE.md) que `HectareasSembradasSuperanLote` en `Comercial`.
 */
final class HectareasAsignadasSuperanLote extends RuntimeException
{
    public static function porOrden(int $ordenId, string $totalAsignado, string $hectareasLote): self
    {
        return new self(
            "Las hectáreas asignadas a la orden #{$ordenId} ({$totalAsignado}) superan las hectáreas del lote ({$hectareasLote})."
        );
    }
}
