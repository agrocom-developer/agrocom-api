<?php

namespace App\Dominios\Operaciones\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Database\Eloquent\Model;

/**
 * Drones por identificador y modelo — lo que el placeholder del buscador
 * viene prometiendo desde que existe el header ("piloto o dron").
 *
 * @extends BusquedaEloquent<Dron>
 */
final class BusquedaDrones extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'drones';
    }

    public function permiso(): string
    {
        return 'operaciones.dron.ver';
    }

    public function prioridad(): int
    {
        return 25;
    }

    protected function modelo(): string
    {
        return Dron::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['identificador', 'modelo'];
    }

    protected function icono(): string
    {
        return 'airplanemode_active';
    }

    protected function rutaListado(): string
    {
        return 'panel.drones.index';
    }

    /** @param Dron $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->identificador,
            detalle: $modelo->modelo,
            href: route('panel.drones.edit', $modelo),
        );
    }
}
