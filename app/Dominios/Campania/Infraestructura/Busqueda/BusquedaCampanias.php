<?php

namespace App\Dominios\Campania\Infraestructura\Busqueda;

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Compartido\Contratos\ResultadoBusqueda;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaEloquent;
use Illuminate\Database\Eloquent\Model;

/**
 * Campañas por código ("2025-2026") y por nombre. Muchas comparten código
 * —cada cliente abre la suya—, así que el estado va en la fila para poder
 * distinguirlas de un vistazo.
 *
 * @extends BusquedaEloquent<Campania>
 */
final class BusquedaCampanias extends BusquedaEloquent
{
    public function clave(): string
    {
        return 'campanias';
    }

    public function permiso(): string
    {
        return 'campania.campania.ver';
    }

    public function prioridad(): int
    {
        return 40;
    }

    protected function modelo(): string
    {
        return Campania::class;
    }

    /** @return list<string> */
    protected function columnas(): array
    {
        return ['codigo', 'nombre'];
    }

    protected function icono(): string
    {
        return 'calendar_month';
    }

    protected function rutaListado(): string
    {
        return 'panel.campanias.index';
    }

    /** @param Campania $modelo */
    protected function fila(Model $modelo): ResultadoBusqueda
    {
        return new ResultadoBusqueda(
            titulo: (string) $modelo->codigo,
            detalle: $modelo->nombre,
            href: route('panel.campanias.edit', $modelo),
            estado: __('campania.campania.estado.'.$modelo->estado->value),
        );
    }
}
