<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use Illuminate\Database\Eloquent\Model;

/** Vehículos por identificador (placa o interno). *
 * @extends BusquedaEloquent<Vehiculo>
 */
final class BusquedaVehiculos extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'vehiculos';
    }

    public function permiso(): string
    {
        return 'mantenimiento.vehiculo.ver';
    }

    public function prioridad(): int
    {
        return 80;
    }

    protected function modelo(): string
    {
        return Vehiculo::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['identificador'];
    }

    protected function icono(): string
    {
        return 'local_shipping';
    }

    protected function rutaListado(): string
    {
        return 'panel.vehiculos.index';
    }

    /** @param Vehiculo $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->identificador,
            href: route('panel.vehiculos.edit', $modelo),
        );
    }
}
