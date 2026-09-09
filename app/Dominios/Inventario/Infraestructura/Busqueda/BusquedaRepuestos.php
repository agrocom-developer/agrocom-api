<?php

namespace App\Dominios\Inventario\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use Illuminate\Database\Eloquent\Model;

/**
 * Repuestos por código y descripción. Es el caso donde el infix más rinde:
 * nadie recuerda el código entero, pero sí una palabra de la descripción.
 *
 * @extends BusquedaEloquent<Repuesto>
 */
final class BusquedaRepuestos extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'repuestos';
    }

    public function permiso(): string
    {
        return 'inventario.repuesto.ver';
    }

    public function prioridad(): int
    {
        return 90;
    }

    protected function modelo(): string
    {
        return Repuesto::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['codigo', 'descripcion'];
    }

    protected function icono(): string
    {
        return 'inventory_2';
    }

    protected function rutaListado(): string
    {
        return 'panel.repuestos.index';
    }

    /** @param Repuesto $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->codigo,
            detalle: $modelo->descripcion,
            href: route('panel.repuestos.edit', $modelo),
        );
    }
}
