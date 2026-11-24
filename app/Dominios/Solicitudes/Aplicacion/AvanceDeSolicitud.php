<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaTrabajo;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudEjecutada;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Implementa {@see SolicitudParaTrabajo}: lo que la ejecución en el lote
 * (sincronización) hace avanzar en la solicitud. Todo cambio de estado pasa por
 * {@see MaquinaEstadosSolicitud} con origen `sistema`.
 */
final class AvanceDeSolicitud implements SolicitudParaTrabajo
{
    public function __construct(private readonly MaquinaEstadosSolicitud $estados) {}

    public function datos(int $solicitudId): ?DatosSolicitudParaTrabajo
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null) {
            return null;
        }

        return new DatosSolicitudParaTrabajo(
            id: $solicitud->id,
            estado: $solicitud->estado->value,
            admiteTrabajo: $solicitud->estado->tieneEquipo(),
            equipoId: $solicitud->equipo_id,
            hectareasSolicitadas: $solicitud->hectareas_solicitadas,
        );
    }

    public function iniciarEjecucion(int $solicitudId): void
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null) {
            return;
        }

        // Camino por la tabla de transiciones: una suspendida por clima se
        // reprograma como asignada, sale en camino y entra a ejecución.
        $destinos = match ($solicitud->estado) {
            EstadoSolicitud::Asignada => [EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion],
            EstadoSolicitud::EnCamino => [EstadoSolicitud::EnEjecucion],
            EstadoSolicitud::SuspendidaPorClima => [EstadoSolicitud::Asignada, EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion],
            default => [],
        };

        try {
            foreach ($destinos as $destino) {
                $solicitud = $this->estados->transicionar($solicitud, $destino, ActorSolicitud::Sistema, null);
            }
        } catch (TransicionSolicitudNoPermitida) {
            // Cambió de estado en paralelo (p. ej. la cancelaron): lo volado en el
            // lote se registra igual y la solicitud queda como la dejó ese cambio.
        }
    }

    public function registrarCobertura(int $solicitudId, string $hectareasCerradas): void
    {
        $solicitud = SolicitudServicio::query()->find($solicitudId);

        if ($solicitud === null || $solicitud->estado !== EstadoSolicitud::EnEjecucion) {
            return;
        }

        $minimo = BigDecimal::of($solicitud->hectareas_solicitadas)
            ->multipliedBy(BigDecimal::of((string) config('solicitudes.cobertura_minima_pct')))
            ->dividedBy(100, 4, RoundingMode::HalfUp);

        if (BigDecimal::of($hectareasCerradas)->isLessThan($minimo)) {
            return;
        }

        try {
            $ejecutada = $this->estados->transicionar($solicitud, EstadoSolicitud::Ejecutada, ActorSolicitud::Sistema, null);
        } catch (TransicionSolicitudNoPermitida) {
            return;
        }

        event(new SolicitudEjecutada($ejecutada->id, $ejecutada->cliente_id, $ejecutada->proveedor_id));
    }
}
