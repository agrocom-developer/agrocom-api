<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de cuentas internas del panel, con búsqueda opcional
 * por nombre o username (HU-45, tarea 39). Solo lectura — mismo patrón de
 * paginación que `ListarPersonas`/`ListarBases`.
 *
 * Filtra `type: Interno` a propósito: las cuentas de portal (`Cliente`) son
 * de otra HU (HU-41), con otro flujo de alta — esta pantalla no las toca ni
 * las muestra.
 */
final class ListarUsuarios
{
    /** @return LengthAwarePaginator<int, SecUser> */
    public function ejecutar(?string $busqueda = null, int $porPagina = 15): LengthAwarePaginator
    {
        return SecUser::query()
            ->where('type', TipoUsuario::Interno)
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn ($consulta) => $consulta->where(
                    fn ($sub) => $sub->where('name', 'like', "%{$busqueda}%")
                        ->orWhere('username', 'like', "%{$busqueda}%")
                ),
            )
            ->orderBy('name')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
