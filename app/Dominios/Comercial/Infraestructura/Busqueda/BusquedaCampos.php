<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * Propiedades (campos, estancias) por nombre y ubicación. Es la entidad que
 * el dueño nombró al pedir la búsqueda — "el nombre de una estancia".
 *
 * @extends BusquedaEloquent<Campo>
 */
final class BusquedaCampos extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'campos';
    }

    public function permiso(): string
    {
        return 'comercial.campo.ver';
    }

    public function prioridad(): int
    {
        return 20;
    }

    protected function modelo(): string
    {
        return Campo::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['nombre', 'ubicacion'];
    }

    protected function icono(): string
    {
        return 'map';
    }

    protected function rutaListado(): string
    {
        return 'panel.campos.index';
    }

    /** @param Campo $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            detalle: $modelo->ubicacion,
            href: route('panel.campos.edit', $modelo),
        );
    }
}
