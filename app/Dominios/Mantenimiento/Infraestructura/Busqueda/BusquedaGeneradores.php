<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use Illuminate\Database\Eloquent\Model;

/** Generadores por identificador y modelo. *
 * @extends BusquedaEloquent<Generador>
 */
final class BusquedaGeneradores extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'generadores';
    }

    public function permiso(): string
    {
        return 'mantenimiento.generador.ver';
    }

    public function prioridad(): int
    {
        return 85;
    }

    protected function modelo(): string
    {
        return Generador::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['identificador', 'modelo'];
    }

    protected function icono(): string
    {
        return 'bolt';
    }

    protected function rutaListado(): string
    {
        return 'panel.generadores.index';
    }

    /** @param Generador $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->identificador,
            detalle: $modelo->modelo,
            href: route('panel.generadores.edit', $modelo),
        );
    }
}
