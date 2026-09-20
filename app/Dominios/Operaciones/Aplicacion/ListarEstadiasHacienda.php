<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Caso de uso: consulta de estadías del equipo en cada hacienda (HU-51, tarea
 * 74; reforma 19/9/2026). Ya NO es de solo lectura — el panel también
 * registra, edita, finaliza y da de baja estadías (ver `Aplicacion/RegistrarEstadiaHacienda`
 * y compañía) — pero esta clase sigue siendo puro filtro/paginado, mismo
 * molde que `ListarTrabajos`/`ListarGastos`.
 *
 * El rango de fechas filtra sobre `entrada` (cuándo ocurrió el hecho), no
 * sobre `salida`: una estadía en curso (`salida` nula) sigue apareciendo si
 * su entrada cae dentro del rango, igual que un trabajo abierto sigue
 * apareciendo en su tablero.
 *
 * `$q` busca sobre `observacion` (texto libre de esta tabla) y sobre
 * `$equipoIdsCoincidentes`/`$propiedadIdsCoincidentes`: los ids de cuadrillas
 * y propiedades cuyo código/nombre coincide con el texto buscado. Esta clase
 * NUNCA resuelve esos ids por su cuenta (cruzaría a `Personal`/`Comercial`,
 * ADR 0003 regla 2) — los recibe ya resueltos del controlador, que los pide
 * por `Contratos/`.
 */
final class ListarEstadiasHacienda
{
    /** @param  list<int>  $equipoIdsCoincidentes
     * @param  list<int>  $propiedadIdsCoincidentes
     * @return LengthAwarePaginator<int, EstadiaHacienda>
     */
    public function ejecutar(
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $propiedadId = null,
        ?string $estado = null,
        ?string $tipoAlojamiento = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
        array $propiedadIdsCoincidentes = [],
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes)
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
     * @param  list<int>  $equipoIdsCoincidentes
     * @param  list<int>  $propiedadIdsCoincidentes
     * @return array<int, float>
     */
    public function diasEfectivosPorEquipo(
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $propiedadId = null,
        ?string $estado = null,
        ?string $tipoAlojamiento = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
        array $propiedadIdsCoincidentes = [],
    ): array {
        return $this->diasEfectivosAgrupadoPor('equipo_trabajo_id', $desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes);
    }

    /**
     * @param  list<int>  $equipoIdsCoincidentes
     * @param  list<int>  $propiedadIdsCoincidentes
     * @return array<int, float>
     */
    public function diasEfectivosPorPropiedad(
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $propiedadId = null,
        ?string $estado = null,
        ?string $tipoAlojamiento = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
        array $propiedadIdsCoincidentes = [],
    ): array {
        return $this->diasEfectivosAgrupadoPor('propiedad_id', $desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes);
    }

    /**
     * Cifras de cabecera del listado (KPI), dentro del MISMO filtro que
     * `ejecutar()`: cuántas estadías hay en curso y cuántas finalizadas,
     * cuántos días efectivos suman las finalizadas y cuántas cuadrillas
     * distintas están hoy en una hacienda.
     *
     * @param  list<int>  $equipoIdsCoincidentes
     * @param  list<int>  $propiedadIdsCoincidentes
     * @return array{en_curso: int, finalizadas: int, dias_efectivos: float, cuadrillas_en_campo: int}
     */
    public function resumen(
        ?string $desde = null,
        ?string $hasta = null,
        ?int $equipoTrabajoId = null,
        ?int $propiedadId = null,
        ?string $estado = null,
        ?string $tipoAlojamiento = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
        array $propiedadIdsCoincidentes = [],
    ): array {
        $estadias = $this->consulta($desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes)
            ->get(['id', 'equipo_trabajo_id', 'entrada', 'salida']);

        $enCurso = $estadias->whereNull('salida');
        $finalizadas = $estadias->whereNotNull('salida');

        return [
            'en_curso' => $enCurso->count(),
            'finalizadas' => $finalizadas->count(),
            'dias_efectivos' => round((float) $finalizadas->sum(
                fn (EstadiaHacienda $estadia): float => $estadia->entrada->diffInSeconds($estadia->salida) / 86400,
            ), 1),
            'cuadrillas_en_campo' => $enCurso->pluck('equipo_trabajo_id')->unique()->count(),
        ];
    }

    /**
     * @param  list<int>  $equipoIdsCoincidentes
     * @param  list<int>  $propiedadIdsCoincidentes
     * @return array<int, float>
     */
    private function diasEfectivosAgrupadoPor(
        string $columna,
        ?string $desde,
        ?string $hasta,
        ?int $equipoTrabajoId,
        ?int $propiedadId,
        ?string $estado,
        ?string $tipoAlojamiento,
        ?string $q,
        array $equipoIdsCoincidentes,
        array $propiedadIdsCoincidentes,
    ): array {
        return $this->consulta($desde, $hasta, $equipoTrabajoId, $propiedadId, $estado, $tipoAlojamiento, $q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes)
            ->whereNotNull('salida')
            ->get(['equipo_trabajo_id', 'propiedad_id', 'entrada', 'salida'])
            ->groupBy($columna)
            ->map(fn (Collection $estadias): float => $estadias->sum(
                fn (EstadiaHacienda $estadia): float => $estadia->entrada->diffInSeconds($estadia->salida) / 86400,
            ))
            ->all();
    }

    /**
     * @param  list<int>  $equipoIdsCoincidentes
     * @param  list<int>  $propiedadIdsCoincidentes
     * @return Builder<EstadiaHacienda>
     */
    private function consulta(
        ?string $desde,
        ?string $hasta,
        ?int $equipoTrabajoId,
        ?int $propiedadId,
        ?string $estado = null,
        ?string $tipoAlojamiento = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
        array $propiedadIdsCoincidentes = [],
    ): Builder {
        return EstadiaHacienda::query()
            ->when($desde !== null, fn (Builder $consulta) => $consulta->whereDate('entrada', '>=', $desde))
            ->when($hasta !== null, fn (Builder $consulta) => $consulta->whereDate('entrada', '<=', $hasta))
            ->when($equipoTrabajoId !== null, fn (Builder $consulta) => $consulta->where('equipo_trabajo_id', $equipoTrabajoId))
            ->when($propiedadId !== null, fn (Builder $consulta) => $consulta->where('propiedad_id', $propiedadId))
            ->when($estado === 'en_curso', fn (Builder $consulta) => $consulta->whereNull('salida'))
            ->when($estado === 'finalizada', fn (Builder $consulta) => $consulta->whereNotNull('salida'))
            ->when($tipoAlojamiento !== null, fn (Builder $consulta) => $consulta->where('tipo_alojamiento', $tipoAlojamiento))
            ->when($q !== null && $q !== '', function (Builder $consulta) use ($q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes): void {
                $consulta->where(function (Builder $consulta) use ($q, $equipoIdsCoincidentes, $propiedadIdsCoincidentes): void {
                    $consulta->whereRaw('LOWER(observacion) LIKE ?', ['%'.mb_strtolower($q).'%']);

                    if ($equipoIdsCoincidentes !== []) {
                        $consulta->orWhereIn('equipo_trabajo_id', $equipoIdsCoincidentes);
                    }

                    if ($propiedadIdsCoincidentes !== []) {
                        $consulta->orWhereIn('propiedad_id', $propiedadIdsCoincidentes);
                    }
                });
            });
    }
}
