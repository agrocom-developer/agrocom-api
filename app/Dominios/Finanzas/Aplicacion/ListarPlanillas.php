<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de planillas del período, más recientes primero
 * (HU-30, tarea 44), filtrable por estado (tarea 119). Solo lectura: el
 * listado completo de planillas generadas no crece al ritmo de un ABM
 * operativo —una por mes calendario—, pero separar los borradores de las
 * aprobadas es justo lo que se mira al entrar. Mismo patrón de paginación
 * que `ListarAnticipos`/`ListarGastos`.
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado (plan de
 * homogeneización §3.6, tarea 119) sobre la MISMA consulta filtrada que la
 * tabla: lo que se ve arriba cuenta exactamente las planillas que se ven
 * abajo.
 */
final class ListarPlanillas
{
    /** @return LengthAwarePaginator<int, Planilla> */
    public function ejecutar(?string $estado = null, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->consulta($estado)
            ->orderByDesc('periodo')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Las cifras de la franja de KPI, con el filtro puesto:
     *
     * - `total`: suma de los `total` de las planillas que cumplen el filtro.
     * - `cantidad`: cuántas planillas son.
     * - `borradores` / `aprobadas`: cuántas hay de cada estado.
     *
     * La suma se hace con `Brick\Math\BigDecimal` en PHP, NUNCA con `SUM()`
     * de SQL: la agregación numérica del motor pasa por REAL/float, lo que
     * violaría la invariante 6 de CLAUDE.md — mismo criterio que
     * `ListarGastos::resumen()`/`SumarAnticiposDelPeriodo`. Y no recalcula
     * nada: suma los `total` ya persistidos por `GenerarPlanilla`, así que
     * la cifra de arriba cuadra a centavo con la columna de abajo.
     *
     * @return array{total: string, cantidad: int, borradores: int, aprobadas: int}
     */
    public function resumen(?string $estado = null): array
    {
        $planillas = $this->consulta($estado)->get(['estado', 'total']);

        $total = BigDecimal::of('0.00');
        $borradores = 0;
        $aprobadas = 0;

        foreach ($planillas as $planilla) {
            $total = $total->plus($planilla->total);

            $planilla->estado->value === 'aprobada' ? $aprobadas++ : $borradores++;
        }

        return [
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            'cantidad' => $planillas->count(),
            'borradores' => $borradores,
            'aprobadas' => $aprobadas,
        ];
    }

    /** @return Builder<Planilla> */
    private function consulta(?string $estado): Builder
    {
        return Planilla::query()
            ->when($estado !== null && $estado !== '', fn ($consulta) => $consulta->where('estado', $estado));
    }
}
