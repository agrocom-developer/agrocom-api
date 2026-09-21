<?php

namespace App\Dominios\Mantenimiento\Infraestructura;

use App\Dominios\Mantenimiento\Contratos\DatosResumenOrdenesMantenimiento;
use App\Dominios\Mantenimiento\Contratos\LecturaResumenOrdenesMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Illuminate\Support\Carbon;

/**
 * Implementación Eloquent de {@see LecturaResumenOrdenesMantenimiento}. Vive
 * fuera de `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `LecturaMantenimientoPorDronEloquent`: esa subcarpeta es solo para modelos.
 *
 * Solo cuenta filas: el soft delete de `ModeloDominio` ya deja afuera lo que
 * se dio de baja. Trae un puñado de columnas de cada orden y cuenta en memoria
 * —mismo criterio que `ListarOrdenesMantenimiento::resumen()`—, para no
 * repetir el conjunto de ids en tres consultas.
 */
final class LecturaResumenOrdenesMantenimientoEloquent implements LecturaResumenOrdenesMantenimiento
{
    private const TIPO_CORRECTIVO = 'correctivo';

    public function deIds(array $ordenIds): DatosResumenOrdenesMantenimiento
    {
        if ($ordenIds === []) {
            return new DatosResumenOrdenesMantenimiento(total: 0, correctivas: 0, ultimoCierre: null);
        }

        $ordenes = OrdenMantenimiento::query()
            ->whereIn('id', $ordenIds)
            ->get(['id', 'tipo', 'fecha_cierre']);

        $ultimoCierre = $ordenes->max('fecha_cierre');

        return new DatosResumenOrdenesMantenimiento(
            total: $ordenes->count(),
            correctivas: $ordenes->where('tipo', self::TIPO_CORRECTIVO)->count(),
            ultimoCierre: $ultimoCierre instanceof Carbon ? $ultimoCierre->toIso8601String() : null,
        );
    }
}
