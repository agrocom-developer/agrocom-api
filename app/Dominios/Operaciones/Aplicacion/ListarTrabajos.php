<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoTableroTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: tablero de trabajos con filtros (HU-15, tarea 15). Solo
 * lectura — el jefe de campo sigue la campaña desde la ciudad sin mutar
 * nada. Mismo patrón que `ListarOrdenesAplicacion` (paginado, filtros por
 * `when()`).
 *
 * `$estado` filtra por el ESTADO DE TABLERO (`Trabajo::scopeConEstadoTablero()`),
 * no por la columna cruda `ope_trabajos.estado` (que solo conoce
 * abierto/cerrado) — ver `EstadoTableroTrabajo`.
 */
final class ListarTrabajos
{
    /** @return LengthAwarePaginator<int, Trabajo> */
    public function ejecutar(
        ?EstadoTableroTrabajo $estado = null,
        ?int $loteId = null,
        ?int $ordenId = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Trabajo::query()
            ->with('sesiones')
            ->when($estado !== null, fn ($consulta) => $consulta->conEstadoTablero($estado))
            ->when($loteId !== null, fn ($consulta) => $consulta->where('lote_id', $loteId))
            ->when($ordenId !== null, fn ($consulta) => $consulta->where('orden_id', $ordenId))
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
