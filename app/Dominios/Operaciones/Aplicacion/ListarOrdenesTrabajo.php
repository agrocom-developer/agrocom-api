<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de Órdenes de Trabajo (tandas), reforma 18/9/2026 —
 * reemplaza al viejo `ListarTrabajos` (listaba `Trabajo` plano, sin
 * cabecera). Solo lectura — el encargado sigue el avance desde la ciudad sin
 * mutar nada. Mismo patrón paginado/filtrado que `ListarOrdenesAplicacion`.
 *
 * Filtra por `orden_id` (para el vínculo "Ver tandas" desde el detalle de
 * una Orden de aplicación, mismo criterio que ya usaba `orden_id` en el
 * `ListarTrabajos` viejo) y por `q` (nro. de aplicación, búsqueda simple).
 */
final class ListarOrdenesTrabajo
{
    /** @return LengthAwarePaginator<int, OrdenTrabajo> */
    public function ejecutar(
        ?int $ordenId = null,
        ?int $nroAplicacion = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return OrdenTrabajo::query()
            ->with('trabajos')
            ->when($ordenId !== null, fn ($consulta) => $consulta->where('orden_id', $ordenId))
            ->when($nroAplicacion !== null, fn ($consulta) => $consulta->where('nro_aplicacion', $nroAplicacion))
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
