<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de clientes con búsqueda y filtros opcionales
 * (HU-22, tarea 33; filtros agregados en la ronda de homogeneización del
 * 17/9/2026). Solo lectura — mismo patrón de paginación que `ListarTrabajos`.
 *
 * `campaniaId` es indirecto (`whereHas` sobre `contratos`): Cliente no tiene
 * `campania_id` propio, el vínculo es Cliente → Contrato → Campania (ADR
 * 0015, la campaña es catálogo compartido). `tipoContacto` filtra por el tipo
 * de AL MENOS un contacto del cliente (`ClienteContacto.tipo`, mismo enum
 * `TipoContactoCliente` del formulario).
 */
final class ListarClientes
{
    /** @return LengthAwarePaginator<int, Cliente> */
    public function ejecutar(
        ?string $busqueda = null,
        ?string $tipoPersona = null,
        ?int $campaniaId = null,
        ?string $tipoContacto = null,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return Cliente::query()
            ->withCount('contactos')
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'nit'], $busqueda),
            )
            ->when(
                $tipoPersona !== null,
                fn ($consulta) => $consulta->where('tipo_persona', $tipoPersona),
            )
            ->when(
                $campaniaId !== null,
                fn ($consulta) => $consulta->whereHas('contratos', fn ($q) => $q->where('campania_id', $campaniaId)),
            )
            ->when(
                $tipoContacto !== null,
                fn ($consulta) => $consulta->whereHas('contactos', fn ($q) => $q->where('tipo', $tipoContacto)),
            )
            ->orderBy('razon_social')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
