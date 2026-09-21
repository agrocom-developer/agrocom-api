<?php

namespace App\Dominios\Finanzas\Infraestructura;

use App\Dominios\Finanzas\Contratos\LecturaGastoPorCampania;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Brick\Math\BigDecimal;

/**
 * Implementación Eloquent del total gastado por campaña. Vive fuera de
 * `Infraestructura/Eloquent/` por el mismo motivo que
 * {@see LecturaGastoMantenimientoEloquent}: no es un modelo, es el
 * adaptador que el `ServiceProvider` liga al contrato.
 */
final class LecturaGastoPorCampaniaEloquent implements LecturaGastoPorCampania
{
    public function totalGastado(int $campaniaId): string
    {
        $gastos = Gasto::query()->where('campania_id', $campaniaId)->get('monto')
            ->reduce(fn (BigDecimal $acumulado, Gasto $gasto) => $acumulado->plus($gasto->monto), BigDecimal::zero());

        $combustibles = Combustible::query()->where('campania_id', $campaniaId)->get('monto')
            ->reduce(fn (BigDecimal $acumulado, Combustible $combustible) => $acumulado->plus($combustible->monto), BigDecimal::zero());

        return (string) $gastos->plus($combustibles);
    }
}
