<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\TipoAlerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: bandeja de alertas con filtros (HU-19, tarea 26). Solo
 * lectura, mismo patrón que `ListarTrabajos` (paginado, filtros por
 * `when()`) — el encargado revisa solo lo anómalo, sin mutar nada acá.
 */
final class ListarAlertas
{
    /** @return LengthAwarePaginator<int, Alerta> */
    public function ejecutar(
        ?EstadoAlerta $estado = null,
        ?TipoAlerta $tipo = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Alerta::query()
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo))
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
