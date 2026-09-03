<?php

namespace App\Dominios\Finanzas\Aplicacion;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * "Cuánto puede adelantar todavía una persona este mes" (HU-29, tarea 41;
 * espec Sprint 8 §192): tope de 3.000 Bs/mes Y 70% del devengado del mes, el
 * que sea menor, menos lo ya adelantado en el mismo mes.
 *
 * El devengado del mes se reusa de `ListarDevengosPersona` (tarea 40) en vez
 * de sumar `fin_devengos_personal` de nuevo — evita duplicar "sumar los
 * devengos de una persona en un mes calendario" en dos ramas. La suma de
 * anticipos del mes vive en `SumarAnticiposDelPeriodo` (extraída en la tarea
 * 44 para que `GenerarPlanilla` la reuse tal cual).
 *
 * Todo con `Brick\Math\BigDecimal`, nunca floats (invariante 6 de
 * CLAUDE.md) — mismo criterio que `GenerarDevengosSesion::calcularMonto()`.
 * El 70% del devengado redondea hacia abajo (`RoundingMode::HalfDown`) a
 * propósito: es un tope, y un tope redondea a favor del sistema, nunca de un
 * anticipo más grande.
 */
final class CalcularDisponibleAnticipo
{
    private const TOPE_ABSOLUTO_MES = '3000.00';

    private const PORCENTAJE_DEVENGADO = '0.70';

    public function __construct(
        private readonly ListarDevengosPersona $listarDevengos,
        private readonly SumarAnticiposDelPeriodo $sumarAnticipos,
    ) {}

    /** `$fecha` en formato `YYYY-MM-DD`: el mes calendario del tope se deriva de ella. */
    public function ejecutar(int $personaId, string $fecha): string
    {
        $periodo = Carbon::parse($fecha)->format('Y-m');

        $devengadoMes = BigDecimal::of($this->listarDevengos->ejecutar($personaId, $periodo)['total']);
        $anticiposMes = $this->sumarAnticipos->ejecutar($personaId, $periodo);

        $topeDevengado = $devengadoMes
            ->multipliedBy(self::PORCENTAJE_DEVENGADO)
            ->toScale(2, RoundingMode::HalfDown);

        $topePeriodo = $topeDevengado->isLessThan(self::TOPE_ABSOLUTO_MES)
            ? $topeDevengado
            : BigDecimal::of(self::TOPE_ABSOLUTO_MES);

        $disponible = $topePeriodo->minus($anticiposMes);

        return (string) ($disponible->isNegative() ? BigDecimal::of('0.00') : $disponible);
    }
}
