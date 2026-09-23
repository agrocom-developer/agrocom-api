<?php

namespace App\Dominios\Seguridad\Infraestructura;

use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;

/**
 * Implementación Eloquent de {@see LecturaUsuariosPorRol}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * {@see LecturaUsuarioDePersonaEloquent}: esa subcarpeta es solo para modelos.
 *
 * El soft delete de `ModeloDominio` filtra solo: la cuenta dada de baja, la
 * asignación de rol revocada y el rol borrado del catálogo quedan fuera sin
 * que esta consulta lo pida.
 */
final class LecturaUsuariosPorRolEloquent implements LecturaUsuariosPorRol
{
    public function idsConRol(string $claveRol): array
    {
        $asignados = SecUserRole::query()
            ->select('id_user')
            ->whereIn('id_role', SecRole::query()
                ->select('id')
                ->where('name', $claveRol)
                ->where('state', true));

        return SecUser::query()
            ->where('type', TipoUsuario::Interno->value)
            ->where('state', true)
            ->whereIn('id', $asignados)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }
}
