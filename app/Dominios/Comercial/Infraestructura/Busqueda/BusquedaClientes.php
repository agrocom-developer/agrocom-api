<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * Clientes en el buscador global. Por razón social y por NIT: el NIT es como
 * los pide contabilidad, y la razón social es el caso que motivó la búsqueda
 * multi-palabra ("esperanza sa" → "Estancia La Esperanza S.A.").
 *
 * @extends BusquedaEloquent<Cliente>
 */
final class BusquedaClientes extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'clientes';
    }

    public function permiso(): string
    {
        return 'comercial.cliente.ver';
    }

    public function prioridad(): int
    {
        return 10;
    }

    protected function modelo(): string
    {
        return Cliente::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['razon_social', 'nit'];
    }

    protected function icono(): string
    {
        return 'apartment';
    }

    protected function rutaListado(): string
    {
        return 'panel.clientes.index';
    }

    /** @param Cliente $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->razon_social,
            detalle: $modelo->nit !== null ? __('busqueda.detalle.nit', ['nit' => $modelo->nit]) : null,
            href: route('panel.clientes.edit', $modelo),
        );
    }
}
