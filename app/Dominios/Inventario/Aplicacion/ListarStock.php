<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Inventario\Dominio\NivelDeStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Caso de uso: listado del stock agregado por `(repuesto, base)`, con
 * búsqueda por código/descripción del repuesto y filtro por base (HU-36,
 * tarea 52), con el nivel de cada fila —«sin existencias» o «por debajo del
 * mínimo»— calculado por fila y nunca persistido (no hay tabla de alertas
 * propia, mismo criterio que `ListarBaterias`, tarea 51).
 *
 * El resultado se deja escrito como atributos NO persistidos (`alerta`,
 * `sin_existencias`) sobre cada `Stock` del paginador — así la vista no
 * calcula ninguna regla de negocio (checklist §8 de
 * `docs/diseno/guia_pantalla_panel.md`), solo lee un dato ya resuelto. La
 * regla vive en {@see NivelDeStock}.
 *
 * `resumen()` devuelve las cifras de la franja de KPI del listado sobre la
 * MISMA consulta filtrada que la tabla (plan de homogeneización §3.6, tarea
 * 117): lo que se ve arriba cuenta exactamente las filas que se ven abajo.
 *
 * Un repuesto dado de baja sigue teniendo filas de stock (ver
 * `EliminarRepuesto`): se trae igual, para que la fila muestre su código en
 * vez de dejar la tabla sin poder pintarla.
 */
final class ListarStock
{
    /** Ventana, en días, de la cifra de movimientos de la franja de KPI. */
    public const DIAS_DEL_PERIODO = 30;

    /** @return LengthAwarePaginator<int, Stock> */
    public function ejecutar(?string $busqueda = null, ?int $baseId = null, int $porPagina = 15): LengthAwarePaginator
    {
        $paginador = $this->consulta($busqueda, $baseId)
            ->with(['repuesto' => fn ($consulta) => $consulta->withTrashed()])
            ->orderBy('base_id')
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();

        $paginador->getCollection()->each(function (Stock $stock): void {
            $stock->sin_existencias = NivelDeStock::sinExistencias($stock->cantidad);
            $stock->alerta = NivelDeStock::bajoMinimo($stock->cantidad, $stock->stock_minimo);
        });

        return $paginador;
    }

    /**
     * Las cifras de la franja de KPI, con el filtro puesto:
     *
     * - `repuestos` / `bases`: cuántos repuestos distintos y en cuántas bases
     *   se reparten las filas que se ven.
     * - `bajoMinimo`: filas con stock que ya alcanzaron el punto de
     *   reposición.
     * - `sinExistencias`: filas que se quedaron en cero. Disjuntas de la
     *   anterior (ver {@see NivelDeStock}).
     * - `movimientos`: asientos de los últimos {@see self::DIAS_DEL_PERIODO}
     *   días de esos repuestos, en esa base si hay filtro de base (como origen
     *   o como destino de un traslado).
     *
     * @return array{repuestos: int, bases: int, bajoMinimo: int, sinExistencias: int, movimientos: int, periodoDias: int}
     */
    public function resumen(?string $busqueda = null, ?int $baseId = null): array
    {
        $filas = $this->consulta($busqueda, $baseId)
            ->get(['repuesto_id', 'base_id', 'cantidad', 'stock_minimo']);

        $bajoMinimo = 0;
        $sinExistencias = 0;

        foreach ($filas as $fila) {
            if (NivelDeStock::sinExistencias($fila->cantidad)) {
                $sinExistencias++;
            } elseif (NivelDeStock::bajoMinimo($fila->cantidad, $fila->stock_minimo)) {
                $bajoMinimo++;
            }
        }

        return [
            'repuestos' => $filas->pluck('repuesto_id')->unique()->count(),
            'bases' => $filas->pluck('base_id')->unique()->count(),
            'bajoMinimo' => $bajoMinimo,
            'sinExistencias' => $sinExistencias,
            'movimientos' => $this->movimientosDelPeriodo($busqueda, $baseId),
            'periodoDias' => self::DIAS_DEL_PERIODO,
        ];
    }

    /** @return Builder<Stock> */
    private function consulta(?string $busqueda, ?int $baseId): Builder
    {
        return Stock::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => $consulta->whereIn('repuesto_id', $this->repuestosQueCoinciden((string) $busqueda)),
            )
            ->when($baseId !== null, fn (Builder $consulta) => $consulta->where('base_id', $baseId));
    }

    /**
     * Los ids de los repuestos cuyo código o descripción coincide con la
     * búsqueda. Incluye los dados de baja: su stock sigue en la tabla.
     *
     * @return Builder<Repuesto>
     */
    private function repuestosQueCoinciden(string $busqueda): Builder
    {
        /** @var Builder<Repuesto> $consulta */
        $consulta = BusquedaTexto::aplicar(Repuesto::query()->withTrashed()->select('id'), ['codigo', 'descripcion'], $busqueda);

        return $consulta;
    }

    private function movimientosDelPeriodo(?string $busqueda, ?int $baseId): int
    {
        return MovimientoStock::query()
            ->where('created_at', '>=', Carbon::now()->subDays(self::DIAS_DEL_PERIODO))
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => $consulta->whereIn('repuesto_id', $this->repuestosQueCoinciden((string) $busqueda)),
            )
            ->when(
                $baseId !== null,
                fn (Builder $consulta) => $consulta->where(
                    fn (Builder $grupo) => $grupo->where('base_id', $baseId)->orWhere('base_destino_id', $baseId),
                ),
            )
            ->count();
    }
}
