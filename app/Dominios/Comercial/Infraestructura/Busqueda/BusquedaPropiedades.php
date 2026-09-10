<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * Propiedades por nombre y ubicación en el buscador global (ADR 0018): es la
 * entidad que el dueño nombró al pedir la búsqueda original — "el nombre de
 * una estancia" — y ese vocabulario (`nombre`/`ubicacion`) vivía en `Campo`
 * hasta que este ADR separó los dos niveles; ahora vive acá (ver
 * `BusquedaCampos`, que perdió `ubicacion`).
 *
 * @extends BusquedaEloquent<Propiedad>
 */
final class BusquedaPropiedades extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'propiedades';
    }

    public function permiso(): string
    {
        return 'comercial.propiedad.ver';
    }

    public function prioridad(): int
    {
        return 20;
    }

    protected function modelo(): string
    {
        return Propiedad::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['nombre', 'ubicacion'];
    }

    protected function icono(): string
    {
        return 'domain';
    }

    protected function rutaListado(): string
    {
        return 'panel.propiedades.index';
    }

    /** @param Propiedad $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            detalle: $modelo->ubicacion,
            href: route('panel.propiedades.edit', $modelo),
        );
    }
}
