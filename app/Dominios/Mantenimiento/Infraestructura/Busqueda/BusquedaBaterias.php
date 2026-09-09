<?php

namespace App\Dominios\Mantenimiento\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use Illuminate\Database\Eloquent\Model;

/** Baterías por identificador; el detalle trae los ciclos acumulados. *
 * @extends BusquedaEloquent<Bateria>
 */
final class BusquedaBaterias extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'baterias';
    }

    public function permiso(): string
    {
        return 'mantenimiento.bateria.ver';
    }

    public function prioridad(): int
    {
        return 75;
    }

    protected function modelo(): string
    {
        return Bateria::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['identificador'];
    }

    protected function icono(): string
    {
        return 'battery_charging_full';
    }

    protected function rutaListado(): string
    {
        return 'panel.baterias.index';
    }

    /** @param Bateria $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->identificador,
            detalle: __('busqueda.detalle.ciclos', ['ciclos' => $modelo->ciclos_acumulados]),
            href: route('panel.baterias.edit', $modelo),
        );
    }
}
