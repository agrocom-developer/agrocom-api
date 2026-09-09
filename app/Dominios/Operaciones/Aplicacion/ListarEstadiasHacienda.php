<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Caso de uso: consulta de estadías del equipo en cada hacienda (HU-51, tarea
 * 74). Solo lectura — la estadía se carga desde la app de campo, nunca desde
 * el panel (ver "Qué NO hacer" del prompt de la tarea). Mismo molde que
 * `ListarTrabajos`/`ListarGastos`: filtros por `when()`, paginado.
 *
 * El rango de fechas filtra sobre `entrada` (cuándo ocurrió el hecho), no
 * sobre `salida`: una estadía en curso (`salida` nula) sigue apareciendo si
 * su entrada cae dentro del rango, igual que un trabajo abierto sigue
 * apareciendo en su tablero.
 */
final class ListarEstadiasHacienda
{
    /** @return LengthAwarePaginator<int, EstadiaHacienda> */
    public function ejecutar(
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $campoId = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($desde, $hasta, $equipoTrabajoId, $campoId)
            ->orderByDesc('entrada')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Total de días efectivos por equipo, dentro del mismo filtro que
     * `ejecutar()` (criterio de aceptación de la tarea: "una estadía de 3
     * días cuenta 3, no 1" — la duración real, no un conteo de filas). Solo
     * estadías YA CERRADAS: una en curso todavía no tiene una duración
     * definida que sumar.
     *
     * @return array<int, float>
     */
    public function diasEfectivosPorEquipo(?string $desde = null, ?string $hasta = null, ?int $equipoTrabajoId = null, ?int $campoId = null): array
    {
        return $this->diasEfectivosAgrupadoPor('equipo_trabajo_id', $desde, $hasta, $equipoTrabajoId, $campoId);
    }

    /** @return array<int, float> */
    public function diasEfectivosPorCampo(?string $desde = null, ?string $hasta = null, ?int $equipoTrabajoId = null, ?int $campoId = null): array
    {
        return $this->diasEfectivosAgrupadoPor('campo_id', $desde, $hasta, $equipoTrabajoId, $campoId);
    }

    /** @return array<int, float> */
    private function diasEfectivosAgrupadoPor(string $columna, ?string $desde, ?string $hasta, ?int $equipoTrabajoId, ?int $campoId): array
    {
        return $this->consulta($desde, $hasta, $equipoTrabajoId, $campoId)
            ->whereNotNull('salida')
            ->get(['equipo_trabajo_id', 'campo_id', 'entrada', 'salida'])
            ->groupBy($columna)
            ->map(fn (Collection $estadias): float => $estadias->sum(
                fn (EstadiaHacienda $estadia): float => $estadia->entrada->diffInSeconds($estadia->salida) / 86400,
            ))
            ->all();
    }

    /** @return Builder<EstadiaHacienda> */
    private function consulta(?string $desde, ?string $hasta, ?int $equipoTrabajoId, ?int $campoId): Builder
    {
        return EstadiaHacienda::query()
            ->when($desde !== null, fn (Builder $consulta) => $consulta->whereDate('entrada', '>=', $desde))
            ->when($hasta !== null, fn (Builder $consulta) => $consulta->whereDate('entrada', '<=', $hasta))
            ->when($equipoTrabajoId !== null, fn (Builder $consulta) => $consulta->where('equipo_trabajo_id', $equipoTrabajoId))
            ->when($campoId !== null, fn (Builder $consulta) => $consulta->where('campo_id', $campoId));
    }
}
