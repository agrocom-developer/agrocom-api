<?php

namespace App\Dominios\Personal\Infraestructura\Busqueda;

use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Database\Eloquent\Model;

/**
 * La gente que trabaja con nosotros, por nombre. Pedido explícito del dueño
 * al fijar el alcance del buscador. El rol operativo va como detalle: buscar
 * "juan" y ver de un vistazo cuál es el piloto.
 *
 * @extends BusquedaEloquent<PerPersona>
 */
final class BusquedaPersonas extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'personas';
    }

    public function permiso(): string
    {
        return 'personal.persona.ver';
    }

    public function prioridad(): int
    {
        return 15;
    }

    protected function modelo(): string
    {
        return PerPersona::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['nombre', 'rol'];
    }

    protected function icono(): string
    {
        return 'badge';
    }

    protected function rutaListado(): string
    {
        return 'panel.personas.index';
    }

    /** @param PerPersona $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            // `rol` es un enum (RolOperativoPersona); al resultado va su
            // rótulo legible, el mismo que muestra el listado de personas.
            detalle: __('personal.roles.'.$modelo->rol->value),
            href: route('panel.personas.edit', $modelo),
        );
    }
}
