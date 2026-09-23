<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecTokenDispositivo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Todos los dispositivos con sesión viva, para la pantalla de revocación del
 * panel (HU-03, CA "revocable desde el panel").
 *
 * A diferencia de {@see ListarDispositivosDeUsuario} no está acotado a un
 * usuario: acá el actor es alguien del panel con el permiso
 * `seguridad.dispositivo.ver` mirando la flota entera — quién tiene sesión
 * abierta y desde qué teléfono. Ese permiso lo verifica el controlador contra
 * el rol activo antes de llegar hasta acá.
 *
 * Con `$incluirRevocados` se agregan los borrados lógicos, que es el
 * histórico de auditoría (ADR 0007): qué dispositivo perdió el acceso y
 * cuándo.
 *
 * `$busqueda` mira el nombre y el identificador del equipo y el nombre o el
 * usuario de su dueño; `$rolId`, el rol con el que opera el dispositivo. La
 * flota crece con cada teléfono, así que el listado se pagina. `$pagina` fija
 * la página; sin ella se toma el `?page=` de la request, que es lo que quiere
 * la pantalla y NO lo que quiere el tablero (tarea 139), que siempre pide la
 * primera para no heredar el de su propia URL.
 */
final class ListarDispositivosRegistrados
{
    /** @return LengthAwarePaginator<int, SecTokenDispositivo> */
    public function ejecutar(?string $busqueda = null, ?int $rolId = null, bool $incluirRevocados = false, int $porPagina = 15, ?int $pagina = null): LengthAwarePaginator
    {
        return SecTokenDispositivo::query()
            ->when($incluirRevocados, fn (Builder $consulta) => $consulta->withTrashed())
            ->when($rolId !== null, fn (Builder $consulta) => $consulta->where('role_id', $rolId))
            ->when(
                $busqueda !== null && $busqueda !== '',
                fn (Builder $consulta) => $consulta->where(function (Builder $grupo) use ($busqueda): void {
                    $grupo
                        ->where(function (Builder $propios) use ($busqueda): void {
                            BusquedaTexto::aplicar($propios, ['nombre_dispositivo', 'uuid_dispositivo'], (string) $busqueda);
                        })
                        ->orWhereHas('tokenable', fn (Builder $dueno) => BusquedaTexto::aplicar($dueno, ['name', 'username'], (string) $busqueda));
                }),
            )
            ->with(['rol', 'tokenable'])
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->paginate($porPagina, page: $pagina)
            ->withQueryString();
    }
}
