<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de planillas del período, más recientes primero
 * (HU-30, tarea 44). Solo lectura, sin filtros (a diferencia de
 * `ListarAnticipos`): el listado completo de planillas generadas no crece al
 * ritmo de un ABM operativo, una por mes calendario. Mismo patrón de
 * paginación que `ListarAnticipos`/`ListarBases`.
 */
final class ListarPlanillas
{
    /** @return LengthAwarePaginator<int, Planilla> */
    public function ejecutar(int $porPagina = 15): LengthAwarePaginator
    {
        return Planilla::query()
            ->orderByDesc('periodo')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
