<?php

namespace App\Dominios\Seguridad\Aplicacion;

use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Dominio\VistaComoActiva;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioCliente;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUsuarioInterno;

/**
 * Revalida, en cada request, que la vista "como otro usuario" de la sesión
 * siga siendo legítima, y carga a quién se mira (tarea 140). Es el equivalente
 * para esta capacidad de lo que `ResolverRolActivo` hace con el rol activo:
 * la bandera de sesión NUNCA se cree a ciegas, porque entre un request y el
 * siguiente puede haber cambiado cualquiera de sus cuatro premisas.
 *
 * Devuelve `null` — "ya no vale" — si falla cualquiera de ellas:
 * 1. El administrador real sigue siendo quien abrió la vista, sigue habilitado
 *    y el rol con el que entró sigue siendo uno de sus roles vivos.
 * 2. Ese rol sigue teniendo `seguridad.usuario.ver_como` (evaluado contra ESE
 *    rol, no la unión de sus roles: invariante 10 de CLAUDE.md).
 * 3. La cuenta observada existe, no está dada de baja ni bloqueada y no es la
 *    del propio administrador.
 * 4. Una cuenta interna conserva el rol con el que se la veía; una de portal,
 *    su contrato.
 *
 * Quien recibe `null` cierra la vista con {@see TerminarVistaComo}: el request
 * nunca sigue con una identidad a medias.
 */
final class ResolverVistaComo
{
    private const PERMISO = 'seguridad.usuario.ver_como';

    public function ejecutar(VistaComoActiva $vista, SecUser $adminReal): ?VistaComoResuelta
    {
        if ($adminReal->id !== $vista->adminId || ! $adminReal->state) {
            return null;
        }

        if (! in_array($vista->adminRolId, $adminReal->idsDeRolesActivos(), true)) {
            return null;
        }

        if (! $adminReal->tienePermisoEnRol(self::PERMISO, $vista->adminRolId)) {
            return null;
        }

        $observado = $vista->tipo === TipoUsuario::Interno
            ? SecUsuarioInterno::query()->find($vista->usuarioId)
            : SecUsuarioCliente::query()->find($vista->usuarioId);

        if ($observado === null || ! $observado->state || $observado->id === $adminReal->id) {
            return null;
        }

        if ($vista->tipo === TipoUsuario::Cliente) {
            return $observado->contrato_id !== null
                ? new VistaComoResuelta($adminReal, $observado, null)
                : null;
        }

        if ($vista->rolId === null || ! in_array($vista->rolId, $observado->idsDeRolesActivos(), true)) {
            return null;
        }

        $rol = SecRole::query()->find($vista->rolId);

        return $rol !== null ? new VistaComoResuelta($adminReal, $observado, $rol) : null;
    }
}
