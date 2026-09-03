<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/**
 * "Cuánto adelantó una persona en un mes calendario dado" — extraído de
 * `CalcularDisponibleAnticipo::sumarAnticiposDelMes()` (HU-29, tarea 41)
 * para que `Finanzas/Aplicacion/GenerarPlanilla.php` (HU-30, tarea 44) lo
 * reuse tal cual, en vez de duplicar a mano el mismo cálculo de rango de mes
 * y la misma suma con `Brick\Math\BigDecimal` (invariante 6 de CLAUDE.md).
 */
final class SumarAnticiposDelPeriodo
{
    /** `$periodo` en formato `YYYY-MM`. */
    public function ejecutar(int $personaId, string $periodo): BigDecimal
    {
        $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        return Anticipo::query()
            ->where('persona_id', $personaId)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->reduce(
                fn (BigDecimal $acumulado, Anticipo $anticipo) => $acumulado->plus($anticipo->monto),
                BigDecimal::of('0.00'),
            );
    }
}
