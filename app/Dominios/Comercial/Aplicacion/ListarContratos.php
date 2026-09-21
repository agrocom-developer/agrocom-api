<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de contratos con búsqueda opcional por razón social
 * del cliente y filtros opcionales por campaña, cliente y propiedad (ADR
 * 0015 punto 1, tarea 69, HU-23 tarea 34; filtros de cliente/propiedad
 * agregados en la tarea "listado-contratos-acciones", 16/9/2026, mismo
 * criterio que `ListarLotes`). Solo lectura — mismo patrón de paginación que
 * `ListarClientes`. `cliente` viene precargada para que la vista pinte la
 * razón social sin una consulta N+1.
 *
 * `propiedadId` filtra por los contratos que tienen AL MENOS un lote de esa
 * propiedad (`lotes.lote.propiedad_id`) — un contrato no tiene `propiedad_id`
 * propio, la propiedad es un dato de cada lote que cubre.
 *
 * Sin `ventanas` (retirada el 16/9/2026 junto con `com_contrato_ventanas`,
 * reemplazo completo por horario a nivel de lote — ver el docblock de
 * `Aplicacion/CrearContrato`): la relación ya no existe en {@see Contrato}.
 */
final class ListarContratos
{
    /** @return LengthAwarePaginator<int, Contrato> */
    public function ejecutar(
        ?string $busqueda = null,
        ?int $campaniaId = null,
        ?int $clienteId = null,
        ?int $propiedadId = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Contrato::query()
            ->with(['cliente'])
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->whereHas(
                    'cliente',
                    fn ($sub) => BusquedaTexto::aplicar($sub, ['razon_social'], $busqueda),
                ),
            )
            ->when(
                $campaniaId !== null,
                fn ($consulta) => $consulta->where('campania_id', $campaniaId),
            )
            ->when(
                $clienteId !== null,
                fn ($consulta) => $consulta->where('cliente_id', $clienteId),
            )
            ->when(
                $propiedadId !== null,
                fn ($consulta) => $consulta->whereHas(
                    'lotes.lote',
                    fn ($sub) => $sub->where('propiedad_id', $propiedadId),
                ),
            )
            ->orderByDesc('fecha_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
