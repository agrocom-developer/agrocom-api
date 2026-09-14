<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Contratos\LecturaGastoMantenimiento;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;

/**
 * Implementación de {@see LecturaGastoMantenimiento} (HU-88, tarea 103):
 * lee `fin_gastos.monto` tal cual lo dejó `EscrituraGastoMantenimientoEloquent`
 * al cerrar la orden — sin recalcular nada del lado de `Mantenimiento`
 * (invariante 6 de CLAUDE.md).
 */
final class LecturaGastoMantenimientoEloquent implements LecturaGastoMantenimiento
{
    public function montoDe(int $gastoId): ?string
    {
        return Gasto::query()->find($gastoId)?->monto;
    }
}
