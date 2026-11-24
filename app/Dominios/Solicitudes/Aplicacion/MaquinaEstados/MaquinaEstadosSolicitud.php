<?php

namespace App\Dominios\Solicitudes\Aplicacion\MaquinaEstados;

use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Dominio\TransicionesSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\HistorialSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Facades\DB;

/**
 * Única clase que crea y cambia el `estado` de una solicitud de servicio
 * (invariante 7 de CLAUDE.md): consulta {@see TransicionesSolicitud} antes de
 * escribir y deja una fila de historial por cada cambio (ADR 0027, punto 4).
 * Todo ocurre en una transacción con la fila bloqueada: dos actores decidiendo
 * a la vez no se pisan.
 *
 * `cambios` son los demás campos que la transición fija en la misma escritura
 * (tarifa congelada al publicar, proveedor y equipo al aceptar, motivo al
 * cancelar): así nunca queda una solicitud en un estado sin sus datos.
 */
final class MaquinaEstadosSolicitud
{
    /**
     * Crea la solicitud en `borrador` con su fila inicial de historial.
     *
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos, ?int $actorUsuarioId): SolicitudServicio
    {
        return DB::transaction(function () use ($atributos, $actorUsuarioId): SolicitudServicio {
            $solicitud = new SolicitudServicio($atributos);
            $solicitud->estado = EstadoSolicitud::Borrador;
            $solicitud->save();

            $this->registrar($solicitud, null, EstadoSolicitud::Borrador, ActorSolicitud::Cliente, $actorUsuarioId, null);

            return $solicitud;
        });
    }

    /**
     * @param  array<string, mixed>  $cambios  atributos que se fijan junto con el estado.
     *
     * @throws TransicionSolicitudNoPermitida si la tabla no admite el paso.
     */
    public function transicionar(
        SolicitudServicio $solicitud,
        EstadoSolicitud $hacia,
        ActorSolicitud $origen,
        ?int $actorUsuarioId,
        ?string $motivo = null,
        array $cambios = [],
    ): SolicitudServicio {
        return DB::transaction(function () use ($solicitud, $hacia, $origen, $actorUsuarioId, $motivo, $cambios): SolicitudServicio {
            /** @var SolicitudServicio $actual */
            $actual = SolicitudServicio::query()->lockForUpdate()->findOrFail($solicitud->id);
            $desde = $actual->estado;

            if (! TransicionesSolicitud::permitida($desde, $hacia)) {
                throw TransicionSolicitudNoPermitida::entre($desde, $hacia);
            }

            $actual->forceFill([...$cambios, 'estado' => $hacia])->save();
            $this->registrar($actual, $desde, $hacia, $origen, $actorUsuarioId, $motivo);

            return $actual;
        });
    }

    private function registrar(
        SolicitudServicio $solicitud,
        ?EstadoSolicitud $desde,
        EstadoSolicitud $hacia,
        ActorSolicitud $origen,
        ?int $actorUsuarioId,
        ?string $motivo,
    ): void {
        HistorialSolicitud::query()->create([
            'solicitud_id' => $solicitud->id,
            'estado_anterior' => $desde,
            'estado_nuevo' => $hacia,
            'origen' => $origen,
            'actor_usuario_id' => $actorUsuarioId,
            'motivo' => $motivo === null ? null : trim($motivo),
        ]);
    }
}
