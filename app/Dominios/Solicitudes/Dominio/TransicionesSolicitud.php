<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Tabla de transiciones permitidas de la solicitud de servicio (invariante 7
 * de CLAUDE.md; especificación del marketplace §5.1): nunca un `estado = ...`
 * suelto en un controlador. Pura, sin Eloquent ni base.
 */
final class TransicionesSolicitud
{
    /** @var array<string, list<EstadoSolicitud>> */
    private const array PERMITIDAS = [
        'borrador' => [EstadoSolicitud::Publicada, EstadoSolicitud::Cancelada],
        'publicada' => [EstadoSolicitud::EnAsignacion, EstadoSolicitud::Cancelada],
        'en_asignacion' => [EstadoSolicitud::Asignada, EstadoSolicitud::SinProveedor, EstadoSolicitud::Cancelada],
        'sin_proveedor' => [EstadoSolicitud::Publicada, EstadoSolicitud::Cancelada],
        'asignada' => [EstadoSolicitud::EnCamino, EstadoSolicitud::EnAsignacion, EstadoSolicitud::Cancelada],
        'en_camino' => [EstadoSolicitud::EnEjecucion, EstadoSolicitud::EnAsignacion, EstadoSolicitud::Cancelada],
        'en_ejecucion' => [EstadoSolicitud::Ejecutada, EstadoSolicitud::SuspendidaPorClima],
        'suspendida_por_clima' => [EstadoSolicitud::Asignada, EstadoSolicitud::Cancelada],
        'ejecutada' => [EstadoSolicitud::Completada, EstadoSolicitud::EnDisputa],
        'en_disputa' => [EstadoSolicitud::Completada, EstadoSolicitud::Cancelada],
        'completada' => [],
        'cancelada' => [],
    ];

    public static function permitida(EstadoSolicitud $desde, EstadoSolicitud $hacia): bool
    {
        return in_array($hacia, self::PERMITIDAS[$desde->value], true);
    }

    /**
     * Estados a los que puede pasar una solicitud, en el orden de la tabla.
     *
     * @return list<EstadoSolicitud>
     */
    public static function destinosDesde(EstadoSolicitud $desde): array
    {
        return self::PERMITIDAS[$desde->value];
    }
}
