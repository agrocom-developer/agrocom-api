<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado de gastos, filtrable por rubro, base, trabajo,
 * equipo de trabajo, campaña y período (HU-33, tarea 47; equipo/campaña
 * agregados por la tarea 73, HU-50). Solo lectura — mismo patrón de
 * paginación que `ListarAnticipos`. `$periodo` con formato inválido se
 * ignora (sin filtrar), mismo criterio permisivo que esa clase.
 */
final class ListarGastos
{
    /** @return LengthAwarePaginator<int, Gasto> */
    public function ejecutar(
        ?int $rubroId = null,
        ?int $baseId = null,
        ?int $trabajoId = null,
        ?string $periodo = null,
        ?int $equipoTrabajoId = null,
        ?int $campaniaId = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($rubroId, $baseId, $trabajoId, $periodo, $equipoTrabajoId, $campaniaId)
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Total de `monto` de los gastos que cumplen el filtro (tarea 73, punto
     * 5) — con `Brick\Math\BigDecimal` en PHP, NUNCA `SUM()` de SQL: en
     * SQLite (motor de los tests) la agregación numérica pasa por
     * REAL/float, lo que violaría la invariante 6 de CLAUDE.md — mismo
     * criterio que `ObtenerAvanceComercial`/`SumarAnticiposDelPeriodo`.
     */
    public function total(
        ?int $rubroId = null,
        ?int $baseId = null,
        ?int $trabajoId = null,
        ?string $periodo = null,
        ?int $equipoTrabajoId = null,
        ?int $campaniaId = null,
    ): string {
        $total = $this->consulta($rubroId, $baseId, $trabajoId, $periodo, $equipoTrabajoId, $campaniaId)
            ->get(['monto'])
            ->reduce(
                fn (BigDecimal $acumulado, Gasto $gasto) => $acumulado->plus($gasto->monto),
                BigDecimal::of('0.00'),
            );

        return (string) $total->toScale(2, RoundingMode::HalfUp);
    }

    /** @return Builder<Gasto> */
    private function consulta(
        ?int $rubroId,
        ?int $baseId,
        ?int $trabajoId,
        ?string $periodo,
        ?int $equipoTrabajoId,
        ?int $campaniaId,
    ): Builder {
        $periodoValido = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1;

        return Gasto::query()
            ->when($rubroId !== null, fn ($consulta) => $consulta->where('rubro_id', $rubroId))
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($trabajoId !== null, fn ($consulta) => $consulta->where('trabajo_id', $trabajoId))
            ->when($equipoTrabajoId !== null, fn ($consulta) => $consulta->where('equipo_trabajo_id', $equipoTrabajoId))
            ->when($campaniaId !== null, fn ($consulta) => $consulta->where('campania_id', $campaniaId))
            ->when($periodoValido, function ($consulta) use ($periodo) {
                $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
                $finMes = $inicioMes->copy()->endOfMonth();

                $consulta->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()]);
            });
    }
}
