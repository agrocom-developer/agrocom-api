<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda de `AsignarEquiposOrden` (HU-70, tarea 85; rediseñada por lote en
 * HU-92, tarea 107): el reparto de una orden entre equipos divide las
 * hectáreas de CADA LOTE de esa orden — la suma de lo ya asignado a ese lote
 * más lo nuevo nunca puede superar `ope_orden_lotes.hectareas_solicitadas`
 * (lo que la orden pidió de ESE lote, no `com_lotes.hectareas` completo).
 * Mismo criterio (comparación con `Brick\Math\BigDecimal`, invariante 6 de
 * CLAUDE.md) que `HectareasSembradasSuperanLote` en `Comercial`.
 */
final class HectareasAsignadasSuperanLote extends RuntimeException
{
    public static function porOrdenYLote(int $ordenId, int $loteId, string $totalAsignado, string $hectareasSolicitadas): self
    {
        return new self(Texto::de('operaciones.errores.hectareas_asignadas_superan_lote', [
            'lote' => $loteId,
            'orden' => $ordenId,
            'asignado' => $totalAsignado,
            'solicitadas' => $hectareasSolicitadas,
        ]));
    }
}
