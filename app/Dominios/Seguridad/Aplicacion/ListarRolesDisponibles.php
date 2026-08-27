<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use Illuminate\Support\Collection;

/**
 * Roles vivos y activos en catálogo de un usuario, listos para ofrecer como
 * opciones de rol activo (ADR 0004, extensión 27/8/2026): vivos en
 * `sec_user_role` (`deleted_at IS NULL`) y no desactivados en el catálogo
 * (`sec_role.state = true`) — la misma noción que
 * {@see SecUser::idsDeRolesActivos()}, pero devolviendo los roles completos
 * (`id`, `name`, `description`) en vez de solo los IDs, que es lo que
 * necesita cualquier pantalla que arme un selector.
 *
 * Único punto que arma esta consulta (antes duplicada, literal, en
 * {@see ResolverRolActivo} y en el controlador GET del selector de rol) —
 * reusarlo evita que las dos copias diverjan con el tiempo (p. ej. si mañana
 * se agrega una columna al `get()` en un solo lugar y no en el otro).
 *
 * No decide nada sobre el rol activo de la sesión (eso es
 * {@see ElegirRolActivo}/{@see ResolverRolActivo}) — solo resuelve el
 * universo de opciones válidas para ese usuario en este momento.
 */
final class ListarRolesDisponibles
{
    /**
     * @return Collection<int, SecRole>
     */
    public function ejecutar(SecUser $usuario): Collection
    {
        $idsRolesVivos = $usuario->idsDeRolesActivos();

        return SecRole::query()
            ->whereIn('id', $idsRolesVivos)
            ->where('state', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
    }
}
