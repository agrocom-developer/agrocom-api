<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\DatosServicio;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudCancelada;
use App\Dominios\Solicitudes\Contratos\LecturaServicios;
use App\Dominios\Solicitudes\Contratos\ResolucionDeServicio;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/**
 * Implementa los pasos que una disputa decide sobre la solicitud, siempre por
 * {@see MaquinaEstadosSolicitud} (invariante 7 de CLAUDE.md): abrirla mueve
 * `ejecutada` a `en_disputa`; cerrarla completa la solicitud (con monto final y
 * comisión) o, con reembolso total, la cancela.
 */
final class ResolucionDeServicioPorMaquina implements ResolucionDeServicio
{
    public function __construct(
        private readonly MaquinaEstadosSolicitud $estados,
        private readonly CompletarServicio $completar,
        private readonly LecturaServicios $servicios,
    ) {}

    public function abrirDisputa(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): bool
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null || $solicitud->estado !== EstadoSolicitud::Ejecutada) {
            return false;
        }

        try {
            $this->estados->transicionar($solicitud, EstadoSolicitud::EnDisputa, ActorSolicitud::from($origen), $actorUsuarioId, $motivo);
        } catch (TransicionSolicitudNoPermitida) {
            // Se completó o se disputó entre la lectura y el paso: la solicitud ya no está `ejecutada`.
            return false;
        }

        return true;
    }

    public function cerrarDisputa(int $solicitudId, bool $reembolsoTotal, ?int $actorUsuarioId, string $motivo): ?DatosServicio
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud !== null && $solicitud->estado === EstadoSolicitud::EnDisputa) {
            if ($reembolsoTotal) {
                $cancelada = $this->estados->transicionar(
                    $solicitud,
                    EstadoSolicitud::Cancelada,
                    ActorSolicitud::Administrador,
                    $actorUsuarioId,
                    $motivo,
                    ['motivo_cancelacion' => $motivo, 'cancelada_por' => ActorSolicitud::Administrador],
                );

                event(new SolicitudCancelada(
                    $cancelada->id,
                    $cancelada->cliente_id,
                    $cancelada->proveedor_id,
                    EstadoSolicitud::EnDisputa->value,
                    ActorSolicitud::Administrador->value,
                    $motivo,
                ));
            } else {
                $this->completar->ejecutar($solicitud, ActorSolicitud::Administrador, $actorUsuarioId, $motivo);
            }
        }

        return $this->servicios->datos($solicitudId);
    }
}
