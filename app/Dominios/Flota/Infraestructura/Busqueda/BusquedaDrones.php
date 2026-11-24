<?php

namespace App\Dominios\Flota\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use Illuminate\Database\Eloquent\Model;

/**
 * Drones por marca, modelo y número de serie.
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
        return 'flota.dron.ver';
    }

    public function prioridad(): int
    {
        return 30;
    }

    protected function modelo(): string
    {
        return Dron::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['marca', 'modelo', 'numero_serie'];
    }

    protected function icono(): string
    {
        return 'airplanemode_active';
    }

    protected function rutaListado(): string
    {
        return 'panel.drones-flota.index';
    }

    /** @param Dron $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: trim("{$modelo->marca} {$modelo->modelo}"),
            detalle: $modelo->numero_serie,
            href: route('panel.drones-flota.show', $modelo),
        );
    }
}
