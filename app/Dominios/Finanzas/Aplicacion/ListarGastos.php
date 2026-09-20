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
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado (plan de
 * homogeneización §3.6, tarea 118) sobre la MISMA consulta filtrada que la
 * tabla: lo que se ve arriba cuenta exactamente los gastos que se ven abajo.
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
     * Las cifras de la franja de KPI, con el filtro puesto:
     *
     * - `total`: suma de `monto` de todos los gastos que cumplen el filtro
     *   (antes solo se mostraba al elegir una cuadrilla, tarea 73 punto 5: el
     *   total por equipo es este mismo cálculo con el filtro de equipo).
     * - `cantidad`: cuántos gastos son.
     * - `sinComprobante`: cuántos no tienen archivo adjunto.
     * - `internos`: suma de los que no se atribuyen a ninguna campaña (el
     *   costo interno de Agrocom, ADR 0015 punto 6).
     *
     * Las sumas se hacen con `Brick\Math\BigDecimal` en PHP, NUNCA con `SUM()`
     * de SQL: en SQLite (motor de los tests) la agregación numérica pasa por
     * REAL/float, lo que violaría la invariante 6 de CLAUDE.md — mismo
     * criterio que `ObtenerAvanceComercial`/`SumarAnticiposDelPeriodo`.
     *
     * @return array{total: string, cantidad: int, sinComprobante: int, internos: string}
     */
    public function resumen(
        ?int $rubroId = null,
        ?int $baseId = null,
        ?int $trabajoId = null,
        ?string $periodo = null,
        ?int $equipoTrabajoId = null,
        ?int $campaniaId = null,
    ): array {
        $gastos = $this->consulta($rubroId, $baseId, $trabajoId, $periodo, $equipoTrabajoId, $campaniaId)
            ->get(['monto', 'comprobante_url', 'campania_id']);

        $total = BigDecimal::of('0.00');
        $internos = BigDecimal::of('0.00');
        $sinComprobante = 0;

        foreach ($gastos as $gasto) {
            $total = $total->plus($gasto->monto);

            if ($gasto->campania_id === null) {
                $internos = $internos->plus($gasto->monto);
            }

            if ($gasto->comprobante_url === null) {
                $sinComprobante++;
            }
        }

        return [
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            'cantidad' => $gastos->count(),
            'sinComprobante' => $sinComprobante,
            'internos' => (string) $internos->toScale(2, RoundingMode::HalfUp),
        ];
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
