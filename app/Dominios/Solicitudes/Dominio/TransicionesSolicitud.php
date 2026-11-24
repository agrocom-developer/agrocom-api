<?php

namespace App\Dominios\Solicitudes\Dominio;

/**
 * Tabla de transiciones permitidas de la solicitud de servicio (invariante 7
 * de CLAUDE.md; `negociacion-contrato.md` §1): nunca un `estado = ...` suelto en
 * un controlador. Pura, sin Eloquent ni base. Las guardas (precio, equipo apto,
 * plazos) las aplica el caso de uso que pide la transición.
 */
final class TransicionesSolicitud
{
    /** @var array<string, list<EstadoSolicitud>> */
    private const array PERMITIDAS = [
        'borrador' => [EstadoSolicitud::Abierta, EstadoSolicitud::Cancelada],
        'abierta' => [EstadoSolicitud::ConOfertas, EstadoSolicitud::Adjudicada, EstadoSolicitud::Expirada, EstadoSolicitud::Cancelada],
        'con_ofertas' => [EstadoSolicitud::Abierta, EstadoSolicitud::Adjudicada, EstadoSolicitud::Expirada, EstadoSolicitud::Cancelada],
        'adjudicada' => [EstadoSolicitud::EnEjecucion, EstadoSolicitud::Abierta, EstadoSolicitud::Cancelada],
        'en_ejecucion' => [EstadoSolicitud::Finalizada],
        'finalizada' => [EstadoSolicitud::Calificada, EstadoSolicitud::Disputada],
        'calificada' => [EstadoSolicitud::Disputada],
        'disputada' => [EstadoSolicitud::Finalizada, EstadoSolicitud::Calificada, EstadoSolicitud::Cancelada],
        'expirada' => [EstadoSolicitud::Abierta],
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
