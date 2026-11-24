<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaTrabajo;
use App\Dominios\Solicitudes\Contratos\SolicitudParaTrabajo;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Carbon;

/**
 * Implementa {@see SolicitudParaTrabajo}: lo que el trabajo operativo detallado
 * (sesión de vuelo, mezcla, recarga) hace avanzar en la solicitud. Ese registro
 * no condiciona ningún estado más allá de iniciar la ejecución
 * (`negociacion-contrato.md` §4, «Compatibilidad»): el cierre lo da el registro
 * `cierre` de la negociación. Todo cambio de estado pasa por
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

        if ($solicitud === null || $solicitud->estado !== EstadoSolicitud::Adjudicada) {
            return;
        }

        try {
            $this->estados->transicionar($solicitud, EstadoSolicitud::EnEjecucion, ActorSolicitud::Sistema, null, null, ['iniciada_en' => Carbon::now()]);
        } catch (TransicionSolicitudNoPermitida) {
            // Cambió de estado en paralelo (p. ej. la cancelaron): lo volado en el
            // lote se registra igual y la solicitud queda como la dejó ese cambio.
        }
    }

    public function registrarCobertura(int $solicitudId, string $hectareasCerradas): void
    {
        // El cierre del servicio es el registro `cierre` de la negociación, no la cobertura
        // de las sesiones de vuelo: acá no cambia ningún estado.
    }
}
