<?php

namespace App\Dominios\Mezclas\Infraestructura;

use App\Dominios\Mezclas\Contratos\LecturaMezclas;
use App\Dominios\Mezclas\Contratos\ProductoCargado;
use App\Dominios\Mezclas\Infraestructura\Eloquent\MezclaDetalle;

/**
 * Implementación Eloquent de {@see LecturaMezclas} (ADR 0003, regla 2).
 * Vive fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `EscrituraMezclasEloquent`: esa subcarpeta está reservada a modelos que
 * extienden `ModeloDominio`.
 */
final class LecturaMezclasEloquent implements LecturaMezclas
{
    public function listarPorTrabajoId(int $trabajoId): array
    {
        return MezclaDetalle::query()
            ->whereHas('mezcla', fn ($query) => $query->where('trabajo_id', $trabajoId))
            ->with('producto')
            ->orderBy('id')
            ->get()
            ->map(fn (MezclaDetalle $detalle): ProductoCargado => new ProductoCargado(
                producto: $detalle->producto->nombre,
                cantidad: (string) $detalle->cantidad,
                unidad: $detalle->unidad,
            ))
            ->all();
    }
}
