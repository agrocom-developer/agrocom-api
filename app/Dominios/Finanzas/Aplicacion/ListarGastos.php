<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado de gastos, filtrable por rubro, base, trabajo y
 * período (HU-33, tarea 47). Solo lectura — mismo patrón de paginación que
 * `ListarAnticipos`. `$periodo` con formato inválido se ignora (sin
 * filtrar), mismo criterio permisivo que esa clase.
 */
final class ListarGastos
{
    /** @return LengthAwarePaginator<int, Gasto> */
    public function ejecutar(
        ?int $rubroId = null,
        ?int $baseId = null,
        ?int $trabajoId = null,
        ?string $periodo = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        $periodoValido = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1;

        return Gasto::query()
            ->when($rubroId !== null, fn ($consulta) => $consulta->where('rubro_id', $rubroId))
            ->when($baseId !== null, fn ($consulta) => $consulta->where('base_id', $baseId))
            ->when($trabajoId !== null, fn ($consulta) => $consulta->where('trabajo_id', $trabajoId))
            ->when($periodoValido, function ($consulta) use ($periodo) {
                $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
                $finMes = $inicioMes->copy()->endOfMonth();

                $consulta->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()]);
            })
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
