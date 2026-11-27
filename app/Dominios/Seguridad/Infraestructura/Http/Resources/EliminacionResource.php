<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Resources;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecEliminacion;

/**
 * El cuerpo de una solicitud de eliminación de cuenta (ADR 0047): el mismo en `/api/cuenta/eliminacion*` y en la sesión
 * (`/api/auth/token`, `/api/auth/sesion` y `/api/auth/renovar`), para que la app muestre «Conservar mi cuenta» sin otra consulta.
 */
final class EliminacionResource
{
    /** @return array{estado: string, solicitada_en: string, ejecutar_desde: string, cancelada_en: string|null, motivo_posposicion: string|null}|null */
    public static function de(?SecEliminacion $eliminacion): ?array
    {
        if ($eliminacion === null) {
            return null;
        }

        return [
            'estado' => $eliminacion->estado->value,
            'solicitada_en' => $eliminacion->solicitada_en->toIso8601String(),
            'ejecutar_desde' => $eliminacion->ejecutar_desde->toIso8601String(),
            'cancelada_en' => $eliminacion->cancelada_en?->toIso8601String(),
            'motivo_posposicion' => $eliminacion->motivo_posposicion,
        ];
    }
}
