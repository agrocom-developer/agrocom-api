<?php

namespace App\Dominios\Proveedores\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Illuminate\Database\Eloquent\Model;

/**
 * Proveedores por razón social y documento fiscal.
 *
 * @extends BusquedaEloquent<Proveedor>
 */
final class BusquedaProveedores extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'proveedores';
    }

    public function permiso(): string
    {
        return 'proveedores.proveedor.ver';
    }

    public function prioridad(): int
    {
        return 20;
    }

    protected function modelo(): string
    {
        return Proveedor::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['razon_social', 'documento_fiscal'];
    }

    protected function icono(): string
    {
        return 'local_shipping';
    }

    protected function rutaListado(): string
    {
        return 'panel.proveedores.index';
    }

    /** @param Proveedor $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: $modelo->razon_social,
            detalle: $modelo->documento_fiscal,
            href: route('panel.proveedores.show', $modelo),
        );
    }
}
