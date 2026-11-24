<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\SolicitudInvalida;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/**
 * Cancela una solicitud con motivo (`POST /solicitudes/{id}/cancelar`,
 * especificación §10.1 y §8.1): la guarda quién la canceló y por qué. La
 * puede cancelar el cliente sobre lo suyo o un administrador desde el panel
 * (soporte). La penalidad por cancelar tarde la resuelve la política de
 * cancelación (ADR 0033), fuera de la app en la versión 1.
 */
final class CancelarSolicitud
{
    public function __construct(
        private readonly ObtenerSolicitudDelCliente $solicitudes,
        private readonly MaquinaEstadosSolicitud $estados,
    ) {}

    /**
     * @throws SolicitudInvalida
     * @throws TransicionSolicitudNoPermitida
     */
    public function porCliente(int $usuarioId, int $solicitudId, string $motivo): SolicitudServicio
    {
        $solicitud = $this->solicitudes->ejecutar($usuarioId, $solicitudId);

        return $this->cancelar($solicitud, ActorSolicitud::Cliente, $usuarioId, $motivo)->load(['terreno', 'historial']);
    }

    /**
     * @throws SolicitudInvalida
     * @throws TransicionSolicitudNoPermitida
     */
    public function porAdministrador(int $actorUsuarioId, int $solicitudId, string $motivo): SolicitudServicio
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        return $this->cancelar($solicitud, ActorSolicitud::Administrador, $actorUsuarioId, $motivo);
    }

    private function cancelar(SolicitudServicio $solicitud, ActorSolicitud $quien, int $actorUsuarioId, string $motivo): SolicitudServicio
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw SolicitudInvalida::motivoRequerido();
        }

        $estadoPrevio = $solicitud->estado;

        $cancelada = $this->estados->transicionar(
            $solicitud,
            EstadoSolicitud::Cancelada,
            $quien,
            $actorUsuarioId,
            $motivo,
            ['motivo_cancelacion' => $motivo, 'cancelada_por' => $quien],
        );

        event(new SolicitudCancelada(
            $cancelada->id,
            $cancelada->cliente_id,
            $cancelada->proveedor_id,
            $estadoPrevio->value,
            $quien->value,
            $motivo,
        ));

        return $cancelada;
    }
}
