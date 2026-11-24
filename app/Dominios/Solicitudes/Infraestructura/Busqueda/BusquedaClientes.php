<?php

namespace App\Dominios\Solicitudes\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Database\Eloquent\Model;

/**
 * Clientes por nombre, documento y teléfono.
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
        return 'solicitudes.cliente.ver';
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
        return ['razon_social', 'documento_fiscal', 'telefono'];
    }

    protected function icono(): string
    {
        return 'agriculture';
    }

    protected function rutaListado(): string
    {
        return 'panel.clientes.index';
    }

    /** @param Cliente $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: $modelo->razon_social,
            detalle: $modelo->documento_fiscal,
            href: route('panel.clientes.show', $modelo),
        );
    }
}
