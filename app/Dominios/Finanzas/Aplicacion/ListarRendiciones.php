<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de rendiciones de campo, filtrable por base y estado
 * (HU-34, tarea 48). Solo lectura, mismo patrón de paginación que
 * `ListarGastos`/`ListarAnticipos`.
 *
 * `ejecutar()` trae además `gastos_count` (tarea 119): el listado lo muestra
 * como columna y lo usa para no ofrecer «Presentar» en una rendición sin
 * gastos, que `PresentarRendicion` rechazaría — es la misma condición que ya
 * gateaba el botón del detalle.
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado (plan de
 * homogeneización §3.6, tarea 119) sobre la MISMA consulta filtrada que la
 * tabla: lo que se ve arriba cuenta exactamente las rendiciones que se ven
 * abajo.
 */
final class ListarRendiciones
{
    /** @return LengthAwarePaginator<int, Rendicion> */
    public function ejecutar(
        ?int $baseId = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($baseId, $estado)
            ->withCount('gastos')
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Las cifras de la franja de KPI, con el filtro puesto:
     *
     * - `total`: suma de los `monto` de las rendiciones que cumplen el filtro.
     * - `abiertas` / `presentadas` / `aprobadas`: cuántas hay de cada estado.
     *
     * La suma se hace con `Brick\Math\BigDecimal` en PHP, NUNCA con `SUM()`
     * de SQL: la agregación numérica del motor pasa por REAL/float, lo que
     * violaría la invariante 6 de CLAUDE.md — mismo criterio que
     * `ListarGastos::resumen()`. Y no recalcula nada: suma los `monto` ya
     * persistidos, así que la cifra de arriba cuadra a centavo con la columna
     * de abajo.
     *
     * @return array{total: string, abiertas: int, presentadas: int, aprobadas: int}
     */
    public function resumen(?int $baseId = null, ?string $estado = null): array
    {
        $rendiciones = $this->consulta($baseId, $estado)->get(['estado', 'monto']);

        $total = BigDecimal::of('0.00');
        $porEstado = ['abierta' => 0, 'presentada' => 0, 'aprobada' => 0];

        foreach ($rendiciones as $rendicion) {
            $total = $total->plus($rendicion->monto);
            $porEstado[$rendicion->estado->value]++;
        }

        return [
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
            'abiertas' => $porEstado['abierta'],
            'presentadas' => $porEstado['presentada'],
            'aprobadas' => $porEstado['aprobada'],
        ];
    }

    /** @return Builder<Rendicion> */
    private function consulta(?int $baseId, ?string $estado): Builder
    {
        return Rendicion::query()
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($estado !== null && $estado !== '', fn ($consulta) => $consulta->where('estado', $estado));
    }
}
