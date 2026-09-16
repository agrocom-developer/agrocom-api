<?php

namespace App\Dominios\Campania\Aplicacion;

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de campañas con búsqueda opcional por código o
 * nombre. Solo lectura — mismo patrón de paginación que `ListarBases`. Orden
 * por `fecha_inicio` descendente: la campaña más reciente encabeza el
 * listado, mismo criterio de "lo último primero" que `ListarGastos` con
 * `fecha`.
 *
 * Sin filtro por cliente (ADR 0015, corregido el 15/9/2026): la campaña es
 * un catálogo compartido, no de un cliente — quién la usa se ve desde el
 * cliente o el contrato, no al revés.
 */
final class ListarCampanias
{
    /** @return LengthAwarePaginator<int, Campania> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return Campania::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['codigo', 'nombre'], $busqueda),
            )
            ->orderByDesc('fecha_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
