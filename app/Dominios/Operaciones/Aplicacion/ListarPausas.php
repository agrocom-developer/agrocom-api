<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado de pausas, filtrable por período (HU-44, tarea 58).
 * Solo lectura — mismo patrón de paginación y de período (`YYYY-MM`, formato
 * inválido se ignora sin filtrar) que `Finanzas/Aplicacion/ListarGastos`.
 */
final class ListarPausas
{
    /** @return LengthAwarePaginator<int, Pausa> */
    public function ejecutar(?string $periodo = null, int $porPagina = 15): LengthAwarePaginator
    {
        $periodoValido = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1;

        return Pausa::query()
            ->with('sesion')
            ->when($periodoValido, function ($consulta) use ($periodo) {
                $inicioMes = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
                $finMes = $inicioMes->copy()->endOfMonth();

                $consulta->whereBetween('inicio', [$inicioMes, $finMes]);
            })
            ->orderByDesc('inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
