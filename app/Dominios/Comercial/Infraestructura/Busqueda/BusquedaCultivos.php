<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Model;

/** Catálogo de cultivos por nombre. *
 * @extends BusquedaEloquent<Cultivo>
 */
final class BusquedaCultivos extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'cultivos';
    }

    public function permiso(): string
    {
        return 'comercial.cultivo.ver';
    }

    public function prioridad(): int
    {
        return 60;
    }

    protected function modelo(): string
    {
        return Cultivo::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['nombre'];
    }

    protected function icono(): string
    {
        return 'grass';
    }

    protected function rutaListado(): string
    {
        return 'panel.cultivos.index';
    }

    /** @param Cultivo $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            href: route('panel.cultivos.edit', $modelo),
        );
    }
}
