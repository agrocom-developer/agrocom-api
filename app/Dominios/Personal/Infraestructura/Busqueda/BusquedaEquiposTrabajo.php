<?php

namespace App\Dominios\Personal\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Database\Eloquent\Model;

/** Equipos de trabajo por código y nombre. *
 * @extends BusquedaEloquent<EquipoTrabajo>
 */
final class BusquedaEquiposTrabajo extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'equipos_trabajo';
    }

    public function permiso(): string
    {
        return 'personal.equipo_trabajo.ver';
    }

    public function prioridad(): int
    {
        return 65;
    }

    protected function modelo(): string
    {
        return EquipoTrabajo::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['codigo', 'nombre'];
    }

    protected function icono(): string
    {
        return 'groups';
    }

    protected function rutaListado(): string
    {
        return 'panel.equipos-trabajo.index';
    }

    /** @param EquipoTrabajo $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->codigo,
            detalle: $modelo->nombre,
            href: route('panel.equipos-trabajo.show', $modelo),
        );
    }
}
