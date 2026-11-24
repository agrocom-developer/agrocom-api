<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/**
 * El cliente da su conformidad a un servicio `ejecutada`
 * (`POST /solicitudes/{id}/conformidad`): la solicitud pasa a `completada`, se
 * congelan el monto final y la comisión y se abre la calificación. Idempotente:
 * dar la conformidad a una solicitud ya completada devuelve la misma solicitud
 * sin repetir avisos.
 */
final class ConformarSolicitud
{
    public function __construct(
        private readonly ObtenerSolicitudDelCliente $solicitudes,
        private readonly CompletarServicio $completar,
    ) {}

    /**
     * @throws SolicitudInvalida si la solicitud no es del cliente (404).
     * @throws TransicionSolicitudNoPermitida si aún no está ejecutada o ya terminó de otra forma.
     */
    public function ejecutar(int $usuarioId, int $solicitudId): SolicitudServicio
    {
        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);

        if ($solicitud->estado->value === 'completada') {
            return $solicitud;
        }

        return $this->completar->ejecutar($solicitud, ActorSolicitud::Cliente, $usuarioId, null)->load(['terreno', 'historial']);
    }
}
