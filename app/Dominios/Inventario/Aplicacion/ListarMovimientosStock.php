<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado paginado de los asientos de `inv_movimientos` (tarea
 * 133), del más reciente al más viejo, con filtro por repuesto y por base —
 * mismo criterio que {@see ListarStock}, pero de solo LECTURA sobre el
 * ASIENTO en vez del agregado: un movimiento no se edita ni se borra
 * (invariante 2 de CLAUDE.md), así que este caso de uso nunca escribe.
 *
 * Sin filtro de texto libre: `inv_movimientos` no tiene una columna de texto
 * natural para buscar (`motivo` es libre y a menudo vacío, no un código ni
 * una descripción) — los filtros son los mismos dos `<select>` que ya usa
 * `ListarStock` (repuesto y base), nunca un `q`.
 *
 * El filtro de base matchea tanto `base_id` como `base_destino_id` (mismo
 * criterio que `ListarStock::movimientosDelPeriodo()`): un traslado hacia o
 * desde la base filtrada tiene que aparecer igual.
 *
 * `instante` queda como atributo NO persistido sobre cada fila: `created_at`
 * ya convertido a la zona horaria de quien mira, así la vista no calcula
 * ninguna zona (checklist §8 de `docs/diseno/guia_pantalla_panel.md`).
 */
final class ListarMovimientosStock
{
    /** @return LengthAwarePaginator<int, MovimientoStock> */
    public function ejecutar(
        ?int $repuestoId,
        ?int $baseId,
        string $zonaHoraria,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        $paginador = $this->consulta($repuestoId, $baseId)
            ->with(['repuesto' => fn ($consulta) => $consulta->withTrashed()])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();

        $paginador->getCollection()->each(function (MovimientoStock $movimiento) use ($zonaHoraria): void {
            $movimiento->instante = $movimiento->created_at->copy()->setTimezone($zonaHoraria);
        });

        return $paginador;
    }

    /** @return Builder<MovimientoStock> */
    private function consulta(?int $repuestoId, ?int $baseId): Builder
    {
        return MovimientoStock::query()
            ->when($repuestoId !== null, fn (Builder $consulta) => $consulta->where('repuesto_id', $repuestoId))
            ->when(
                $baseId !== null,
                fn (Builder $consulta) => $consulta->where(
                    fn (Builder $grupo) => $grupo->where('base_id', $baseId)->orWhere('base_destino_id', $baseId),
                ),
            );
    }
}
