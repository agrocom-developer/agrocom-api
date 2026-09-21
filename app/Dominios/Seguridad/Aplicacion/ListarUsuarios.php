<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cuentas del panel (internas Y de portal), con
 * búsqueda opcional por nombre o username y filtro opcional por tipo
 * (HU-45, tarea 39; tarea 65 le agrega las cuentas `cliente`). Solo lectura
 * — mismo patrón de paginación que `ListarPersonas`/`ListarBases`.
 *
 * Ya NO filtra `type: Interno` a propósito (tarea 65, HU-41): sin ABM del
 * panel, una cuenta de portal solo podía crearse por seeder/factory — el
 * `$tipo` es un filtro más, no un scope fijo.
 */
final class ListarUsuarios
{
    /** @return LengthAwarePaginator<int, SecUser> */
    public function ejecutar(?string $busqueda = null, ?TipoUsuario $tipo = null, int $porPagina = 15): LengthAwarePaginator
    {
        return SecUser::query()
            ->when($tipo !== null, fn ($consulta) => $consulta->where('type', $tipo))
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => BusquedaTexto::aplicar($consulta, ['name', 'username'], $busqueda),
            )
            ->orderBy('name')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
