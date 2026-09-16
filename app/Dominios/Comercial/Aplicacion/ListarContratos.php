<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de contratos con búsqueda opcional por razón social
 * del cliente y filtro opcional por campaña (ADR 0015 punto 1, tarea 69,
 * HU-23 tarea 34). Solo lectura — mismo patrón de paginación que
 * `ListarClientes`. `cliente` viene precargada para que la vista pinte la
 * razón social sin una consulta N+1.
 *
 * Sin `ventanas` (retirada el 16/9/2026 junto con `com_contrato_ventanas`,
 * reemplazo completo por horario a nivel de lote — ver el docblock de
 * `Aplicacion/CrearContrato`): la relación ya no existe en {@see Contrato}.
 */
final class ListarContratos
{
    /** @return LengthAwarePaginator<int, Contrato> */
    public function ejecutar(?string $busqueda = null, ?int $campaniaId = null, int $porPagina = 15): LengthAwarePaginator
    {
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
            ->orderByDesc('fecha_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
