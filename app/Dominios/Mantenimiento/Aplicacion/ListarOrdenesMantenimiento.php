<?php

namespace App\Dominios\Mantenimiento\Aplicacion;

use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado de órdenes de mantenimiento con filtro por estado y
 * tipo de equipo (HU-37, tarea 53). Solo lectura, mismo patrón de paginación
 * que `ListarVehiculos`.
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado sobre la
 * MISMA consulta filtrada que la tabla (plan de homogeneización §3.6, tarea
 * 116): lo que se ve arriba cuenta exactamente las filas que se ven abajo.
 *
 * La búsqueda libre corre sobre lo que la orden escribe de su puño
 * —descripción de apertura y de cierre— más los ids de equipo que el
 * controlador ya resolvió contra `ope_drones`/`man_vehiculos`: el
 * identificador del equipo es de otro módulo (y de otra tabla), así que
 * llega resuelto, nunca por un join (ADR 0003 regla 3, mismo criterio que
 * `ListarEstadiasHacienda`).
 */
final class ListarOrdenesMantenimiento
{
    /**
     * @param  array<string, list<int>>  $equipoIdsCoincidentes  `dron`/`vehiculo` => ids cuyo identificador coincide con la búsqueda.
     * @return LengthAwarePaginator<int, OrdenMantenimiento>
     */
    public function ejecutar(
        ?string $estado = null,
        ?string $equipoTipo = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return $this->consulta($estado, $equipoTipo, $q, $equipoIdsCoincidentes)
            ->orderByDesc('fecha_apertura')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Las cuatro cifras de la franja de KPI, con el filtro puesto:
     *
     * - `abiertas` / `cerradas`: cuánto trabajo queda y cuánto se terminó.
     * - `correctivas`: cuánto de todo esto fue reacción y no plan.
     * - `demora_maxima`: días que lleva abierta la orden más vieja — la que
     *   se está quedando atrás. `0` si no hay ninguna abierta.
     *
     * @param  array<string, list<int>>  $equipoIdsCoincidentes
     * @return array{abiertas: int, cerradas: int, correctivas: int, demora_maxima: int}
     */
    public function resumen(
        ?string $estado = null,
        ?string $equipoTipo = null,
        ?string $q = null,
        array $equipoIdsCoincidentes = [],
    ): array {
        $ordenes = $this->consulta($estado, $equipoTipo, $q, $equipoIdsCoincidentes)
            ->get(['id', 'tipo', 'estado', 'fecha_apertura']);

        $abiertas = $ordenes->where('estado', EstadoOrdenMantenimiento::Abierta);
        $aperturaMasVieja = $abiertas->min('fecha_apertura');

        return [
            'abiertas' => $abiertas->count(),
            'cerradas' => $ordenes->where('estado', EstadoOrdenMantenimiento::Cerrada)->count(),
            'correctivas' => $ordenes->where('tipo', 'correctivo')->count(),
            'demora_maxima' => $aperturaMasVieja instanceof Carbon
                ? (int) $aperturaMasVieja->diffInDays(Carbon::now())
                : 0,
        ];
    }

    /**
     * @param  array<string, list<int>>  $equipoIdsCoincidentes
     * @return Builder<OrdenMantenimiento>
     */
    private function consulta(
        ?string $estado,
        ?string $equipoTipo,
        ?string $q,
        array $equipoIdsCoincidentes,
    ): Builder {
        return OrdenMantenimiento::query()
            ->when($estado !== null, fn (Builder $consulta) => $consulta->where('estado', $estado))
            ->when($equipoTipo !== null, fn (Builder $consulta) => $consulta->where('equipo_tipo', $equipoTipo))
            ->when(
                $q !== null && $q !== '',
                fn (Builder $consulta) => $consulta->where(
                    fn (Builder $grupo) => $grupo
                        ->whereRaw('LOWER(descripcion) LIKE ?', ['%'.mb_strtolower((string) $q).'%'])
                        ->orWhereRaw('LOWER(descripcion_final) LIKE ?', ['%'.mb_strtolower((string) $q).'%'])
                        ->orWhere(fn (Builder $equipos) => $this->equiposCoincidentes($equipos, $equipoIdsCoincidentes)),
                ),
            );
    }

    /**
     * Las órdenes cuyo equipo coincide con la búsqueda, por tipo: los ids ya
     * vienen resueltos del controlador, acá solo se arma el `OR` que los
     * une. Sin ids no agrega nada (un `where` vacío dejaría pasar todo).
     *
     * @param  array<string, list<int>>  $equipoIdsCoincidentes
     * @param  Builder<OrdenMantenimiento>  $consulta
     */
    private function equiposCoincidentes(Builder $consulta, array $equipoIdsCoincidentes): void
    {
        foreach ($equipoIdsCoincidentes as $tipo => $ids) {
            if ($ids === []) {
                continue;
            }

            $consulta->orWhere(
                fn (Builder $grupo) => $grupo->where('equipo_tipo', $tipo)->whereIn('equipo_id', $ids),
            );
        }
    }
}
