<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de lotes con filtro por cliente, por propiedad y
 * búsqueda por código (tarea 77, HU-54, etapa 2; ADR 0020 — el lote cuelga
 * directo de `Propiedad`, un salto menos que bajo ADR 0018).
 *
 * El código de un lote es único por PROPIEDAD, no globalmente (índice
 * parcial `com_lotes_codigo_unico`): la búsqueda por código puede traer
 * varios lotes de distintas propiedades con el mismo código.
 */
final class ListarLotes
{
    /** @return LengthAwarePaginator<int, Lote> */
    public function ejecutar(
        ?int $clienteId = null,
        ?int $propiedadId = null,
        ?string $busqueda = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Lote::query()
            ->with('propiedad.cliente')
            ->when(
                $propiedadId !== null,
                fn (Builder $consulta) => $consulta->where('propiedad_id', $propiedadId),
            )
            ->when(
                $clienteId !== null,
                fn (Builder $consulta) => $consulta->whereHas('propiedad', fn (Builder $propiedad) => $propiedad->where('cliente_id', $clienteId)),
            )
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => $consulta->where('codigo', 'like', "%{$busqueda}%"),
            )
            ->orderBy('codigo')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
