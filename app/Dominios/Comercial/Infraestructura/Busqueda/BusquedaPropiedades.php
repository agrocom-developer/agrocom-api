<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Propiedades por nombre y ubicación en el buscador global (ADR 0020): es la
 * entidad que el dueño nombró al pedir la búsqueda original — "el nombre de
 * una estancia".
 *
 * Ubicación estructurada (adenda 16/9/2026 a ADR 0018 punto 1): `ubicacion`
 * (texto libre) se eliminó de `com_propiedades` — se busca por `nombre` y
 * `localidad` (la única columna de texto libre que queda; departamento/
 * municipio son catálogo cerrado, no columnas propias de esta tabla, así
 * que `columnas()` no puede tocarlas: ver el docblock de `BusquedaEloquent`).
 * El detalle de cada fila sí combina los tres niveles (departamento/
 * municipio/localidad) vía las relaciones, precargadas en `consultaBase()`
 * para no N+1.
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
        return ['nombre', 'localidad'];
    }

    protected function icono(): string
    {
        return 'domain';
    }

    protected function rutaListado(): string
    {
        return 'panel.propiedades.index';
    }

    /** @return Builder<Propiedad> */
    protected function consultaBase(): Builder
    {
        return parent::consultaBase()->with(['departamento', 'municipio']);
    }

    /** @param Propiedad $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        $detalle = collect([$modelo->departamento?->nombre, $modelo->municipio?->nombre, $modelo->localidad])
            ->filter()
            ->implode(' · ');

        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            detalle: $detalle !== '' ? $detalle : null,
            href: route('panel.propiedades.edit', $modelo),
        );
    }
}
