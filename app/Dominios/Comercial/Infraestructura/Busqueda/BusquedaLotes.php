<?php

namespace App\Dominios\Comercial\Infraestructura\Busqueda;

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * Lotes por código. El detalle trae las hectáreas, que es lo que distingue a
 * dos lotes de código parecido cuando aparecen juntos en el resultado.
 *
 * @extends BusquedaEloquent<Lote>
 */
final class BusquedaLotes extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'lotes';
    }

    public function permiso(): string
    {
        return 'comercial.lote.ver';
    }

    public function prioridad(): int
    {
        return 30;
    }

    protected function modelo(): string
    {
        return Lote::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['codigo'];
    }

    protected function icono(): string
    {
        return 'grid_view';
    }

    protected function rutaListado(): string
    {
        return 'panel.lotes.index';
    }

    /** @param Lote $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->codigo,
            detalle: __('busqueda.detalle.hectareas', ['hectareas' => $modelo->hectareas]),
            href: route('panel.lotes.edit', $modelo),
        );
    }
}
