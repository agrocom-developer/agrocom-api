<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Infraestructura\Eloquent\Factura;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listado de facturas emitidas, paginado (HU-31, tarea 45). Solo lectura,
 * sin filtros propios todavía — mismo criterio inicial que
 * `ListarContratos` sin búsqueda: se agrega cuando haga falta, no antes.
 * `contrato.cliente` va precargado: la vista pinta el cliente sin una
 * consulta extra por fila (checklist de `guia_pantalla_panel.md` §8).
 */
final class ListarFacturas
{
    /** @return LengthAwarePaginator<int, Factura> */
    public function ejecutar(int $porPagina = 15): LengthAwarePaginator
    {
        return Factura::query()
            ->with('contrato.cliente')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
