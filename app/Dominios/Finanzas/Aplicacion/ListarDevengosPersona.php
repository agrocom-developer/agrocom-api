<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Caso de uso "ver mis devengos por período" (HU-28, tarea 40; espec Sprint
 * 8 §191): filtra `DevengoPersonal` por `persona_id` y por mes calendario.
 *
 * `$total` se suma con `Brick\Math\BigDecimal`, nunca `array_sum()` sobre
 * floats ni sobre strings decimales sin pasar por él (invariante 6 de
 * CLAUDE.md, literal) — mismo criterio que
 * `GenerarDevengosSesion::calcularMonto()`.
 */
final class ListarDevengosPersona
{
    /**
     * `$periodo` en formato `YYYY-MM`; `null` o con formato inválido cae al
     * mes calendario actual.
     *
     * @return array{devengos: Collection<int, DevengoPersonal>, total: string, periodo: string}
     */
    public function ejecutar(int $personaId, ?string $periodo = null): array
    {
        $periodoResuelto = ($periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1)
            ? $periodo
            : Carbon::now()->format('Y-m');

        $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodoResuelto}-01")->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $devengos = DevengoPersonal::query()
            ->where('persona_id', $personaId)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->orderBy('fecha')
            ->get();

        // El listado muestra también los absorbidos por un jornal (ADR 0023),
        // pero el total solo suma los que se pagan.
        $total = $devengos
            ->reject(fn (DevengoPersonal $devengo): bool => $devengo->estaAbsorbido())
            ->reduce(
                fn (BigDecimal $acumulado, DevengoPersonal $devengo) => $acumulado->plus($devengo->monto),
                BigDecimal::of('0.00'),
            );

        return [
            'devengos' => $devengos,
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            'periodo' => $periodoResuelto,
        ];
    }
}
