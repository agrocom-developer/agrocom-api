<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\SolicitudNoAsignable;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Dominio\Excepciones\TransicionSolicitudNoPermitida;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Support\Facades\DB;

/**
 * Implementa los pasos de asignación sobre {@see MaquinaEstadosSolicitud}
 * (invariante 7 de CLAUDE.md): ningún `estado = ...` suelto, y cada paso deja
 * su fila de historial con origen, autor y motivo.
 */
final class AsignacionDeSolicitudPorMaquina implements AsignacionDeSolicitud
{
    public function __construct(private readonly MaquinaEstadosSolicitud $estados) {}

    public function iniciar(int $solicitudId): bool
    {
        return DB::transaction(function () use ($solicitudId): bool {
            $solicitud = SolicitudServicio::query()->lockForUpdate()->find($solicitudId);

            if ($solicitud === null || $solicitud->estado !== EstadoSolicitud::Publicada) {
                return false;
            }

            $this->estados->transicionar($solicitud, EstadoSolicitud::EnAsignacion, ActorSolicitud::Sistema, null);

            return true;
        });
    }

    public function asignar(int $solicitudId, int $proveedorId, int $equipoId, string $origen, ?int $actorUsuarioId, ?string $motivo): void
    {
        $this->paso($solicitudId, EstadoSolicitud::Asignada, ActorSolicitud::from($origen), $actorUsuarioId, $motivo, [
            'proveedor_id' => $proveedorId,
            'equipo_id' => $equipoId,
        ]);
    }

    public function marcarSinProveedor(int $solicitudId): void
    {
        $this->paso($solicitudId, EstadoSolicitud::SinProveedor, ActorSolicitud::Sistema, null, null);
    }

    public function devolver(int $solicitudId, string $origen, ?int $actorUsuarioId, string $motivo): void
    {
        $this->paso($solicitudId, EstadoSolicitud::EnAsignacion, ActorSolicitud::from($origen), $actorUsuarioId, $motivo, ['proveedor_id' => null]);
    }

    public function salirEnCamino(int $solicitudId, ?int $actorUsuarioId): void
    {
        $this->paso($solicitudId, EstadoSolicitud::EnCamino, ActorSolicitud::Proveedor, $actorUsuarioId, null);
    }

    public function reabrir(int $solicitudId, ?int $actorUsuarioId, string $motivo): void
    {
        DB::transaction(function () use ($solicitudId, $actorUsuarioId, $motivo): void {
            $this->paso($solicitudId, EstadoSolicitud::Publicada, ActorSolicitud::Administrador, $actorUsuarioId, $motivo);
            $this->paso($solicitudId, EstadoSolicitud::EnAsignacion, ActorSolicitud::Administrador, $actorUsuarioId, $motivo);
        });
    }

    /** @param  array<string, mixed>  $cambios */
    private function paso(int $solicitudId, EstadoSolicitud $hacia, ActorSolicitud $origen, ?int $actorUsuarioId, ?string $motivo, array $cambios = []): void
    {
        $solicitud = SolicitudServicio::query()->findOrFail($solicitudId);

        try {
            $this->estados->transicionar($solicitud, $hacia, $origen, $actorUsuarioId, $motivo, $cambios);
        } catch (TransicionSolicitudNoPermitida) {
            throw SolicitudNoAsignable::enEstado($solicitud->fresh()?->estado->value ?? $solicitud->estado->value, $hacia->value);
        }
    }
}
