<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado de anticipos, filtrable por persona y por período
 * (HU-29, tarea 41). Solo lectura — mismo patrón de paginación que
 * `ListarBases`. `$periodo` con formato inválido se ignora (sin filtrar),
 * mismo criterio permisivo que `ListarDevengosPersona` cuando no hay
 * período: acá, a diferencia de esa pantalla, "sin período" es un filtro
 * legítimo (ver todo el historial), no un fallback al mes actual.
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado (plan de
 * homogeneización §3.6, tarea 118) sobre la MISMA consulta filtrada que la
 * tabla: lo que se ve arriba cuenta exactamente los anticipos que se ven
 * abajo.
 */
final class ListarAnticipos
{
    /** @return LengthAwarePaginator<int, Anticipo> */
    public function ejecutar(?int $personaId = null, ?string $periodo = null, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->consulta($personaId, $periodo)
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Las cifras de la franja de KPI, con el filtro puesto:
     *
     * - `total`: suma de `monto` de todos los anticipos que cumplen el filtro.
     * - `cantidad`: cuántos anticipos son.
     * - `personas`: a cuántas personas distintas se les adelantó.
     * - `mayor`: el monto del anticipo más grande.
     *
     * La suma se hace con `Brick\Math\BigDecimal` en PHP, NUNCA con `SUM()` de
     * SQL: en SQLite (motor de los tests) la agregación numérica pasa por
     * REAL/float, lo que violaría la invariante 6 de CLAUDE.md — mismo
     * criterio que `SumarAnticiposDelPeriodo`.
     *
     * @return array{total: string, cantidad: int, personas: int, mayor: string}
     */
    public function resumen(?int $personaId = null, ?string $periodo = null): array
    {
        $anticipos = $this->consulta($personaId, $periodo)->get(['persona_id', 'monto']);

        $total = BigDecimal::of('0.00');
        $mayor = BigDecimal::of('0.00');

        foreach ($anticipos as $anticipo) {
            $monto = BigDecimal::of($anticipo->monto);
            $total = $total->plus($monto);

            if ($monto->isGreaterThan($mayor)) {
                $mayor = $monto;
            }
        }

        return [
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            'cantidad' => $anticipos->count(),
            'personas' => $anticipos->pluck('persona_id')->unique()->count(),
            'mayor' => (string) $mayor->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /** @return Builder<Anticipo> */
    private function consulta(?int $personaId, ?string $periodo): Builder
    {
        $periodoValido = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1;

        return Anticipo::query()
            ->when($personaId !== null, fn ($consulta) => $consulta->where('persona_id', $personaId))
            ->when($periodoValido, function ($consulta) use ($periodo) {
                $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
                $finMes = $inicioMes->copy()->endOfMonth();

                $consulta->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()]);
            });
    }
}
