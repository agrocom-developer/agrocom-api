<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campos físicos por nombre. Es la entidad que el dueño nombró al pedir la
 * búsqueda — "el nombre de una estancia" — aunque desde ADR 0018 esa palabra
 * se reserva para `Propiedad` (ver `BusquedaPropiedades`); este proveedor
 * sigue existiendo para el campo delimitado dentro de una propiedad.
 *
 * `ubicacion` se mudó a `com_propiedades` (ADR 0018): ya no es columna de
 * `Campo`, así que sale de `columnas()` (evita un `WHERE` contra una columna
 * que no existe) y `fila()` muestra el nombre de la propiedad como detalle
 * en su lugar — `consultaBase()` precarga la relación para no armar una
 * query nueva por fila.
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
        return ['nombre'];
    }

    protected function icono(): string
    {
        return 'map';
    }

    protected function rutaListado(): string
    {
        return 'panel.campos.index';
    }

    /** @return Builder<Campo> */
    protected function consultaBase(): Builder
    {
        return Campo::query()->with('propiedad:id,nombre');
    }

    /** @param Campo $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->nombre,
            detalle: $modelo->propiedad?->nombre,
            href: route('panel.campos.edit', $modelo),
        );
    }
}
