<?php

namespace App\Dominios\Personal\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Database\Eloquent\Model;

/** Bases operativas por nombre y ubicación. *
 * @extends BusquedaEloquent<PerBase>
 */
final class BusquedaBases extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'bases';
    }

    public function permiso(): string
    {
        return 'personal.base.ver';
    }

    public function prioridad(): int
    {
        return 70;
    }

    protected function modelo(): string
    {
        return PerBase::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['nombre', 'ubicacion'];
    }

    protected function icono(): string
    {
        return 'warehouse';
    }

    protected function rutaListado(): string
    {
        return 'panel.bases.index';
    }

    /** @param PerBase $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            detalle: $modelo->ubicacion,
            href: route('panel.bases.edit', $modelo),
        );
    }
}
