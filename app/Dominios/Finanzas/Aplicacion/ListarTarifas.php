<?php

namespace App\Dominios\Finanzas\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListarTarifas
{
    /** @return LengthAwarePaginator<int, Tarifa> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 20): LengthAwarePaginator
    {
        return Tarifa::query()
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['nombre', 'descripcion'], $busqueda),
            )
            ->orderByDesc('predeterminada')
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
