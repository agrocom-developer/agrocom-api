<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\TipoAlerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: bandeja de alertas con filtros (HU-19, tarea 26). Solo
 * lectura, mismo patrón que `ListarTrabajos` (paginado, filtros por
 * `when()`) — el encargado revisa solo lo anómalo, sin mutar nada acá.
 *
 * `resumen()` (tarea 113) alimenta la franja de KPI del listado con el MISMO
 * filtro que la tabla: si el filtro deja solo las atendidas, las pendientes
 * dan cero.
 */
final class ListarAlertas
{
    /** @return LengthAwarePaginator<int, Alerta> */
    public function ejecutar(
        ?EstadoAlerta $estado = null,
        ?TipoAlerta $tipo = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->filtradas($estado, $tipo)
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /** @return array{pendientes: int, atendidas: int} */
    public function resumen(?EstadoAlerta $estado = null, ?TipoAlerta $tipo = null): array
    {
        $porEstado = $this->filtradas($estado, $tipo)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'pendientes' => (int) $porEstado->get(EstadoAlerta::Pendiente->value, 0),
            'atendidas' => (int) $porEstado->get(EstadoAlerta::Atendida->value, 0),
        ];
    }

    /** @return Builder<Alerta> */
    private function filtradas(?EstadoAlerta $estado, ?TipoAlerta $tipo): Builder
    {
        return Alerta::query()
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo));
    }
}
