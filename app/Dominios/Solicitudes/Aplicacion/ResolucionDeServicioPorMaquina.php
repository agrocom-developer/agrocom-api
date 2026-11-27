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
use App\Dominios\Solicitudes\Infraestructura\Eloquent\HistorialSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;

/**
 * Implementa los pasos que un reclamo o una calificación deciden sobre la
 * solicitud, siempre por {@see MaquinaEstadosSolicitud} (invariante 7 de
 * CLAUDE.md): abrir el reclamo mueve `finalizada` o `calificada` a `en_reclamo`;
 * cerrarla devuelve la solicitud al estado que tenía o, con reembolso total, la
 * cancela; calificar mueve `finalizada` a `calificada`.
 */
final class ResolucionDeServicioPorMaquina implements ResolucionDeServicio
{
    public function __construct(
        private readonly MaquinaEstadosSolicitud $estados,
        private readonly LecturaServicios $servicios,
    ) {}

    public function abrirReclamo(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): bool
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null || ! in_array($solicitud->estado, [EstadoSolicitud::Finalizada, EstadoSolicitud::Calificada], true)) {
            return false;
        }

        try {
            $this->estados->transicionar($solicitud, EstadoSolicitud::EnReclamo, ActorSolicitud::from($origen), $actorUsuarioId, $motivo);
        } catch (TransicionSolicitudNoPermitida) {
            // Cambió de estado entre la lectura y el paso: la solicitud ya no admite el reclamo.
            return false;
        }

        return true;
    }

    public function cerrarReclamo(int $solicitudId, bool $reembolsoTotal, ?int $actorUsuarioId, string $motivo): ?DatosServicio
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud !== null && $solicitud->estado === EstadoSolicitud::EnReclamo) {
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
                    EstadoSolicitud::EnReclamo->value,
                    ActorSolicitud::Administrador->value,
                    $motivo,
                ));
            } else {
                $this->estados->transicionar($solicitud, $this->estadoAntesDelReclamo($solicitud), ActorSolicitud::Administrador, $actorUsuarioId, $motivo);
            }
        }

        return $this->servicios->datos($solicitudId);
    }

    public function marcarCalificada(int $solicitudId, ?int $actorUsuarioId): void
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null || $solicitud->estado !== EstadoSolicitud::Finalizada) {
            return;
        }

        try {
            $this->estados->transicionar($solicitud, EstadoSolicitud::Calificada, ActorSolicitud::Sistema, $actorUsuarioId);
        } catch (TransicionSolicitudNoPermitida) {
            // Otro actor lo movió entre la lectura y el paso (un reclamo): nada que hacer.
        }
    }

    private function estadoAntesDelReclamo(SolicitudServicio $solicitud): EstadoSolicitud
    {
        $paso = HistorialSolicitud::query()
            ->where('solicitud_id', $solicitud->id)
            ->where('estado_nuevo', EstadoSolicitud::EnReclamo->value)
            ->orderByDesc('id')
            ->first();

        return $paso?->estado_anterior === EstadoSolicitud::Calificada ? EstadoSolicitud::Calificada : EstadoSolicitud::Finalizada;
    }
}
